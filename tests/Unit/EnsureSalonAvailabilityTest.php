<?php

namespace Tests\Unit;

use App\Actions\EnsureSalonAvailability;
use App\Enums\UserRole;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnsureSalonAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_range_must_fit_one_local_open_interval_and_may_touch_boundaries(): void
    {
        $tenant = $this->tenantWithSchedule('America/Bogota');
        $action = app(EnsureSalonAvailability::class);
        $action->handle($tenant, $this->utc('2026-10-19 09:00', 'America/Bogota'), $this->utc('2026-10-19 18:00', 'America/Bogota'));
        $this->addToAssertionCount(1);

        foreach ([['2026-10-19 08:59', '2026-10-19 10:00'], ['2026-10-19 17:30', '2026-10-19 18:01'], ['2026-10-19 17:30', '2026-10-20 09:30']] as [$start, $end]) {
            $this->expectFailure($action, $tenant, $start, $end, 'America/Bogota');
        }
    }

    public function test_closed_days_and_half_open_block_intersections_are_rejected(): void
    {
        $tenant = $this->tenantWithSchedule('America/Bogota');
        $tenant->weeklyHours()->where('weekday', 2)->update(['closed' => true, 'opens_at' => null, 'closes_at' => null]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::OWNER]);
        $tenant->scheduleBlocks()->create([
            'starts_at' => $this->utc('2026-10-19 12:00', 'America/Bogota'),
            'ends_at' => $this->utc('2026-10-19 13:00', 'America/Bogota'),
            'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);
        $action = app(EnsureSalonAvailability::class);

        $action->handle($tenant, $this->utc('2026-10-19 11:00', 'America/Bogota'), $this->utc('2026-10-19 12:00', 'America/Bogota'));
        $action->handle($tenant, $this->utc('2026-10-19 13:00', 'America/Bogota'), $this->utc('2026-10-19 14:00', 'America/Bogota'));
        $this->addToAssertionCount(2);
        $this->expectFailure($action, $tenant, '2026-10-19 11:59', '2026-10-19 12:30', 'America/Bogota');
        $this->expectFailure($action, $tenant, '2026-10-20 10:00', '2026-10-20 11:00', 'America/Bogota');
    }

    public function test_dst_transition_uses_real_utc_instants_within_one_local_day(): void
    {
        $tenant = $this->tenantWithSchedule('America/New_York', '00:00', '23:59');
        app(EnsureSalonAvailability::class)->handle(
            $tenant, CarbonImmutable::parse('2026-03-08 06:30:00 UTC'), CarbonImmutable::parse('2026-03-08 07:30:00 UTC'),
        );
        $this->addToAssertionCount(1);
    }

    private function tenantWithSchedule(string $timezone, string $opensAt = '09:00', string $closesAt = '18:00'): Tenant
    {
        $tenant = Tenant::factory()->create();
        $settings = SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => $timezone]);
        $settings->weeklyHours()->update(['opens_at' => $opensAt, 'closes_at' => $closesAt]);

        return $tenant;
    }

    private function utc(string $value, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone)->utc();
    }

    private function expectFailure(EnsureSalonAvailability $action, Tenant $tenant, string $start, string $end, string $timezone): void
    {
        try {
            $action->handle($tenant, $this->utc($start, $timezone), $this->utc($end, $timezone));
            $this->fail('The unavailable range was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('startsAt', $exception->errors());
        }
    }
}
