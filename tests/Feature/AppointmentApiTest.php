<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\SalonService;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_creates_appointment_with_distinct_available_service_snapshots_and_server_totals(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        $first = $this->service($tenant, ['name' => 'Cut', 'duration_minutes' => 30, 'price' => '20.50']);
        $second = $this->service($tenant, ['name' => 'Color', 'duration_minutes' => 45, 'price' => '35.25']);
        $startsAt = now()->addDay()->startOfHour();
        $this->actingAs($client);

        $response = $this->postJson(route('api.appointments.store'), [
            'bookingKey' => (string) Str::uuid(),
            'startsAt' => $startsAt->toISOString(),
            'serviceIds' => [$first->id, $second->id],
            'duration' => 1,
            'total' => '0.01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.duration', 75)
            ->assertJsonPath('data.total', '55.75')
            ->assertJsonPath('data.createdBy', $client->id)
            ->assertJsonPath('data.updatedBy', $client->id)
            ->assertJsonPath('data.endsAt', $startsAt->copy()->addMinutes(75)->toISOString())
            ->assertJsonPath('data.services.0.name', 'Cut')
            ->assertJsonPath('data.services.1.price', '35.25');

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'total' => '55.75',
            'created_by' => $client->id,
            'updated_by' => $client->id,
        ]);
        $this->assertDatabaseHas('appointment_services', ['appointment_id' => Appointment::query()->sole()->id, 'name' => 'Cut', 'duration_minutes' => 30]);
    }

    public function test_creation_requires_a_client_and_rejects_duplicate_unavailable_or_foreign_services(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $owner] = $this->tenantUser(UserRole::OWNER, $tenant);
        [$foreignTenant] = $this->tenantUser(UserRole::CLIENT);
        $available = $this->service($tenant);
        $unavailable = $this->service($tenant, ['available' => false]);
        $foreign = $this->service($foreignTenant);
        $payload = ['bookingKey' => (string) Str::uuid(), 'startsAt' => now()->addDay()->toISOString()];

        $this->actingAs($owner)->postJson(route('api.appointments.store'), $payload + ['serviceIds' => [$available->id]])->assertForbidden();
        $this->actingAs($client)->postJson(route('api.appointments.store'), $payload + ['serviceIds' => [$available->id, $available->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds.1');
        $this->postJson(route('api.appointments.store'), $payload + ['serviceIds' => [$unavailable->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds');
        $this->postJson(route('api.appointments.store'), $payload + ['serviceIds' => [$foreign->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('serviceIds');
    }

    public function test_booking_key_is_idempotent_per_tenant_and_client(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        $service = $this->service($tenant);
        $this->actingAs($client);
        $payload = [
            'bookingKey' => (string) Str::uuid(),
            'startsAt' => now()->addDay()->toISOString(),
            'serviceIds' => [$service->id],
        ];

        $id = $this->postJson(route('api.appointments.store'), $payload)->assertCreated()->json('data.id');
        $service->update(['available' => false]);
        $this->postJson(route('api.appointments.store'), $payload)->assertOk()->assertJsonPath('data.id', $id);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_deleting_source_service_clears_reference_but_preserves_snapshot(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        $service = $this->service($tenant, ['name' => 'Historical cut', 'duration_minutes' => 40, 'price' => '30.00']);
        $this->actingAs($client);

        $appointmentId = $this->postJson(route('api.appointments.store'), [
            'bookingKey' => (string) Str::uuid(),
            'startsAt' => now()->addDay()->toISOString(),
            'serviceIds' => [$service->id],
        ])->assertCreated()->json('data.id');

        $service->delete();

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'salon_service_id' => null,
            'name' => 'Historical cut',
            'duration_minutes' => 40,
            'price' => '30.00',
        ]);
        $this->getJson(route('api.appointments.show', $appointmentId))
            ->assertOk()
            ->assertJsonPath('data.services.0.serviceId', null)
            ->assertJsonPath('data.services.0.name', 'Historical cut');
    }

    public function test_capacity_uses_peak_half_open_overlap_instead_of_intersection_count(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        SalonSetting::query()->create(['tenant_id' => $tenant->id, 'appointment_capacity' => 2]);
        $service = $this->service($tenant, ['duration_minutes' => 60]);
        $day = now()->addDay()->startOfDay();
        $this->appointment($tenant, $client, $day->copy()->setTime(10, 0), $day->copy()->setTime(11, 0));
        $this->appointment($tenant, $client, $day->copy()->setTime(11, 0), $day->copy()->setTime(12, 0));
        $this->actingAs($client);

        $this->postJson(route('api.appointments.store'), [
            'bookingKey' => (string) Str::uuid(),
            'startsAt' => $day->copy()->setTime(10, 30)->toISOString(),
            'serviceIds' => [$service->id],
        ])->assertCreated();

        $this->postJson(route('api.appointments.store'), [
            'bookingKey' => (string) Str::uuid(),
            'startsAt' => $day->copy()->setTime(10, 45)->toISOString(),
            'serviceIds' => [$service->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('startsAt');
    }

    public function test_cancelled_and_completed_appointments_do_not_consume_capacity(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        $service = $this->service($tenant);
        $startsAt = now()->addDay();
        $this->appointment($tenant, $client, $startsAt, $startsAt->copy()->addHour(), AppointmentStatus::CANCELLED);
        $this->appointment($tenant, $client, $startsAt, $startsAt->copy()->addHour(), AppointmentStatus::COMPLETED);
        $this->actingAs($client);

        $this->postJson(route('api.appointments.store'), [
            'bookingKey' => (string) Str::uuid(), 'startsAt' => $startsAt->toISOString(), 'serviceIds' => [$service->id],
        ])->assertCreated();
    }

    public function test_client_can_cancel_only_own_confirmed_appointment_at_or_before_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $otherClient] = $this->tenantUser(UserRole::CLIENT, $tenant);
        SalonSetting::query()->create(['tenant_id' => $tenant->id, 'cancellation_notice_hours' => 24]);
        $atCutoff = $this->appointment($tenant, $client, now()->addHours(24), now()->addHours(25));
        $tooLate = $this->appointment($tenant, $client, now()->addHours(23), now()->addHours(24));
        $foreignOwner = $this->appointment($tenant, $otherClient, now()->addDays(2), now()->addDays(2)->addHour());
        $this->actingAs($client);

        $this->postJson(route('api.appointments.cancel', $atCutoff))->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson(route('api.appointments.cancel', $tooLate))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(route('api.appointments.cancel', $foreignOwner))->assertNotFound();
    }

    public function test_owner_and_staff_can_cancel_any_tenant_appointment_anytime_with_notification_data(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $staff] = $this->tenantUser(UserRole::STAFF, $tenant);
        $appointment = $this->appointment($tenant, $client, now()->addMinute(), now()->addHour());
        $appointment->services()->create(['name' => 'Snapshot', 'duration_minutes' => 59, 'price' => '25.00']);
        $this->actingAs($staff);

        $this->postJson(route('api.appointments.cancel', $appointment), ['reason' => 'Salon closed', 'notifyClient' => true])
            ->assertOk()
            ->assertJsonPath('data.cancellationReason', 'Salon closed')
            ->assertJsonPath('data.notifyClient', true)
            ->assertJsonPath('data.updatedBy', $staff->id)
            ->assertJsonPath('data.services.0.name', 'Snapshot');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'created_by' => $client->id,
            'updated_by' => $staff->id,
            'cancelled_by' => $staff->id,
        ]);
    }

    public function test_only_owner_or_staff_can_complete_confirmed_appointment_from_start_time(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $owner] = $this->tenantUser(UserRole::OWNER, $tenant);
        $started = $this->appointment($tenant, $client, now(), now()->addHour());
        $future = $this->appointment($tenant, $client, now()->addMinute(), now()->addHour());

        $this->actingAs($client)->postJson(route('api.appointments.complete', $started))->assertForbidden();
        $this->actingAs($owner)->postJson(route('api.appointments.complete', $future))->assertUnprocessable();
        $this->postJson(route('api.appointments.complete', $started))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.createdBy', $client->id)
            ->assertJsonPath('data.updatedBy', $owner->id);

        $this->assertDatabaseHas('appointments', [
            'id' => $started->id,
            'created_by' => $client->id,
            'updated_by' => $owner->id,
            'completed_by' => $owner->id,
        ]);
    }

    public function test_terminal_appointments_reject_later_transitions_and_keep_first_terminal_state(): void
    {
        CarbonImmutable::setTestNow('2026-09-13 12:00:00 UTC');
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $owner] = $this->tenantUser(UserRole::OWNER, $tenant);
        $cancelled = $this->appointment($tenant, $client, now()->subHour(), now()->addHour());
        $completed = $this->appointment($tenant, $client, now()->subHour(), now()->addHour());
        $this->actingAs($owner);

        $this->postJson(route('api.appointments.cancel', $cancelled))->assertOk();
        $this->postJson(route('api.appointments.complete', $cancelled))
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Solo se pueden completar citas confirmadas.');

        $this->postJson(route('api.appointments.complete', $completed))->assertOk();
        $this->postJson(route('api.appointments.cancel', $completed))
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Solo se pueden cancelar citas confirmadas.');

        $this->assertSame(AppointmentStatus::CANCELLED, $cancelled->refresh()->status);
        $this->assertSame(AppointmentStatus::COMPLETED, $completed->refresh()->status);
    }

    public function test_appointment_access_is_tenant_safe_and_clients_only_see_their_own(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $otherClient] = $this->tenantUser(UserRole::CLIENT, $tenant);
        [$foreignTenant, $foreignClient] = $this->tenantUser(UserRole::CLIENT);
        $own = $this->appointment($tenant, $client, now()->addDay(), now()->addDay()->addHour());
        $other = $this->appointment($tenant, $otherClient, now()->addDays(2), now()->addDays(2)->addHour());
        $foreign = $this->appointment($foreignTenant, $foreignClient, now()->addDay(), now()->addDay()->addHour());
        $this->actingAs($client);

        $this->getJson(route('api.appointments.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson(route('api.appointments.show', $other))->assertNotFound();
        $this->getJson(route('api.appointments.show', $foreign))->assertNotFound();
        $this->postJson(route('api.appointments.cancel', $foreign))->assertNotFound();
    }

    public function test_booking_details_and_snapshots_cannot_be_updated_or_deleted(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        $appointment = $this->appointment($tenant, $client, now()->addDay(), now()->addDay()->addHour());
        $appointment->services()->create(['name' => 'Snapshot', 'duration_minutes' => 60, 'price' => '20.00']);

        $this->expectException(LogicException::class);
        $appointment->update(['total' => '1.00']);
    }

    public function test_audit_updater_can_change_but_creator_is_immutable(): void
    {
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $staff] = $this->tenantUser(UserRole::STAFF, $tenant);
        $appointment = $this->appointment($tenant, $client, now()->addDay(), now()->addDay()->addHour());

        $appointment->update(['updated_by' => $staff->id]);
        $this->assertSame($staff->id, $appointment->refresh()->updated_by);
        $this->assertSame($client->id, $appointment->created_by);

        $this->expectException(LogicException::class);
        $appointment->update(['created_by' => $staff->id]);
    }

    /** @return array{Tenant, User} */
    private function tenantUser(UserRole $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::factory()->create();

        return [$tenant, User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role])];
    }

    private function service(Tenant $tenant, array $attributes = []): SalonService
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::OWNER]);

        return SalonService::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'updated_by' => $owner->id, ...$attributes]);
    }

    private function appointment(Tenant $tenant, User $client, $startsAt, $endsAt, AppointmentStatus $status = AppointmentStatus::CONFIRMED): Appointment
    {
        return Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => (int) round($startsAt->diffInMinutes($endsAt)),
            'status' => $status,
        ]);
    }
}
