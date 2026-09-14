<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\SalonScheduleBlock;
use App\Models\SalonService;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_receives_slots_using_server_side_duration_and_salon_timezone(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 12:00:00 UTC');
        [$tenant, $creator] = $this->tenant('aurora.salon.test', 'America/Bogota', 30, 1, '09:00', '12:00');
        $first = $this->service($tenant, $creator, 30);
        $second = $this->service($tenant, $creator, 45);

        $response = $this->availability($tenant, '2026-10-20', [$first->id, $second->id]);

        $response->assertOk()
            ->assertJsonPath('data.timezone', 'America/Bogota')
            ->assertJsonPath('data.duration', 75)
            ->assertJsonPath('data.slotIntervalMinutes', 30)
            ->assertJsonCount(4, 'data.slots')
            ->assertJsonPath('data.slots.0.startsAt', '2026-10-20T14:00:00.000000Z')
            ->assertJsonPath('data.slots.0.startsAtLocal', '2026-10-20 09:00')
            ->assertJsonPath('data.slots.3.endsAtLocal', '2026-10-20 11:45');
    }

    public function test_slots_exclude_blocks_and_keep_half_open_boundaries(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 12:00:00 UTC');
        [$tenant, $creator] = $this->tenant('blocks.salon.test', 'UTC', 30, 1, '09:00', '12:00');
        $service = $this->service($tenant, $creator, 30);
        SalonScheduleBlock::query()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => '2026-10-20 10:00:00+00',
            'ends_at' => '2026-10-20 10:30:00+00',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $slots = $this->availability($tenant, '2026-10-20', [$service->id])
            ->assertOk()
            ->json('data.slots');

        $this->assertSame(
            ['09:00', '09:30', '10:30', '11:00', '11:30'],
            collect($slots)->pluck('startsAtLocal')->map(fn (string $value): string => substr($value, 11))->all(),
        );
    }

    public function test_confirmed_appointments_consume_capacity_but_terminal_appointments_do_not(): void
    {
        CarbonImmutable::setTestNow('2026-10-19 12:00:00 UTC');
        [$tenant, $creator] = $this->tenant('capacity.salon.test', 'UTC', 30, 1, '09:00', '12:00');
        $service = $this->service($tenant, $creator, 60);
        $this->appointment($tenant, $creator, '2026-10-20 10:00:00+00', '2026-10-20 11:00:00+00');
        $this->appointment($tenant, $creator, '2026-10-20 09:00:00+00', '2026-10-20 10:00:00+00', AppointmentStatus::CANCELLED);
        $this->appointment($tenant, $creator, '2026-10-20 11:00:00+00', '2026-10-20 12:00:00+00', AppointmentStatus::COMPLETED);

        $slots = $this->availability($tenant, '2026-10-20', [$service->id])
            ->assertOk()
            ->json('data.slots');

        $this->assertSame(
            ['09:00', '11:00'],
            collect($slots)->pluck('startsAtLocal')->map(fn (string $value): string => substr($value, 11))->all(),
        );
    }

    public function test_closed_days_and_past_starts_return_no_slots(): void
    {
        CarbonImmutable::setTestNow('2026-10-20 10:15:00 UTC');
        [$tenant, $creator, $settings] = $this->tenant('closed.salon.test', 'UTC', 30, 1, '09:00', '12:00');
        $service = $this->service($tenant, $creator, 30);

        $this->availability($tenant, '2026-10-20', [$service->id])
            ->assertOk()
            ->assertJsonCount(3, 'data.slots')
            ->assertJsonPath('data.slots.0.startsAtLocal', '2026-10-20 10:30');

        $settings->weeklyHours()->where('weekday', 3)->update([
            'closed' => true, 'opens_at' => null, 'closes_at' => null,
        ]);
        $this->availability($tenant, '2026-10-21', [$service->id])
            ->assertOk()
            ->assertJsonCount(0, 'data.slots');
    }

    public function test_endpoint_rejects_duplicate_unavailable_and_foreign_services(): void
    {
        [$tenant, $creator] = $this->tenant('validation.salon.test');
        [$foreignTenant, $foreignCreator] = $this->tenant('foreign.salon.test');
        $available = $this->service($tenant, $creator, 30);
        $unavailable = $this->service($tenant, $creator, 30, false);
        $foreign = $this->service($foreignTenant, $foreignCreator, 30);

        $this->availability($tenant, '2026-10-20', [$available->id, $available->id])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds.1');
        $this->availability($tenant, '2026-10-20', [$unavailable->id])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds');
        $this->availability($tenant, '2026-10-20', [$foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds');
    }

    /**
     * @return array{Tenant, User, SalonSetting}
     */
    private function tenant(string $domain, string $timezone = 'UTC', int $interval = 30, int $capacity = 1, string $opensAt = '09:00', string $closesAt = '12:00'): array
    {
        $tenant = Tenant::factory()->create(['domain' => $domain]);
        $creator = User::factory()->create(['tenant_id' => $tenant->id]);
        $settings = SalonSetting::query()->create([
            'tenant_id' => $tenant->id,
            'timezone' => $timezone,
            'slot_interval_minutes' => $interval,
            'appointment_capacity' => $capacity,
        ]);
        $settings->weeklyHours()->update([
            'closed' => false, 'opens_at' => $opensAt, 'closes_at' => $closesAt,
        ]);

        return [$tenant, $creator, $settings];
    }

    private function service(Tenant $tenant, User $creator, int $duration, bool $available = true): SalonService
    {
        return SalonService::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            'duration_minutes' => $duration,
            'available' => $available,
        ]);
    }

    private function appointment(Tenant $tenant, User $client, string $startsAt, string $endsAt, AppointmentStatus $status = AppointmentStatus::CONFIRMED): Appointment
    {
        return Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $client->id,
            'updated_by' => $client->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    /**
     * @param  list<string>  $serviceIds
     */
    private function availability(Tenant $tenant, string $date, array $serviceIds)
    {
        return $this->getJson('http://'.$tenant->domain.'/api/public/availability?'.http_build_query([
            'date' => $date,
            'serviceIds' => $serviceIds,
        ]));
    }
}
