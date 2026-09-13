<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SalonScheduleBlock;
use App\Models\SalonService;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class SalonScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_staff_can_fetch_and_fully_replace_schedule(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $staff] = $this->tenantUser(UserRole::STAFF, $tenant);

        $this->actingAs($owner)->getJson(route('api.admin.schedule.show'))
            ->assertOk()->assertJsonPath('data.timezone', 'America/Bogota')->assertJsonCount(7, 'data.weeklyHours');

        $payload = $this->schedulePayload();
        $payload['weeklyHours'][0] = ['weekday' => 0, 'closed' => true, 'opensAt' => null, 'closesAt' => null];
        $this->actingAs($staff)->putJson(route('api.admin.schedule.update'), $payload)
            ->assertOk()->assertJsonPath('data.appointmentCapacity', 3)->assertJsonPath('data.weeklyHours.0.closed', true);

        $this->assertDatabaseHas('salon_settings', ['tenant_id' => $tenant->id, 'timezone' => 'America/New_York', 'slot_interval_minutes' => 20]);
        $this->assertDatabaseCount('salon_weekly_hours', 7);
    }

    public function test_schedule_rejects_clients_and_invalid_full_replacements(): void
    {
        [, $client] = $this->tenantUser(UserRole::CLIENT);
        $this->actingAs($client)->getJson(route('api.admin.schedule.show'))->assertForbidden();
        $this->putJson(route('api.admin.schedule.update'), $this->schedulePayload())->assertForbidden();

        [, $owner] = $this->tenantUser(UserRole::OWNER);
        $payload = $this->schedulePayload();
        $payload['timezone'] = 'Bogota/Invalid';
        $payload['slotIntervalMinutes'] = 7;
        $payload['appointmentCapacity'] = 0;
        $payload['cancellationNoticeHours'] = -1;
        $payload['weeklyHours'][1]['weekday'] = 0;
        $payload['weeklyHours'][2]['opensAt'] = '18:00';
        $payload['weeklyHours'][2]['closesAt'] = '09:00';

        $this->actingAs($owner)->putJson(route('api.admin.schedule.update'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors([
                'timezone', 'slotIntervalMinutes', 'appointmentCapacity', 'cancellationNoticeHours',
                'weeklyHours.1.weekday', 'weeklyHours.2.opensAt',
            ]);

        $payload = $this->schedulePayload();
        array_pop($payload['weeklyHours']);
        $this->putJson(route('api.admin.schedule.update'), $payload)->assertUnprocessable()->assertJsonValidationErrors('weeklyHours');
    }

    public function test_blocks_convert_local_times_to_utc_and_keep_audit_fields(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/Bogota']);
        $this->actingAs($owner);

        $response = $this->postJson(route('api.admin.schedule.blocks.store'), [
            'startsAtLocal' => '2026-10-20 09:30', 'endsAtLocal' => '2026-10-20 11:00',
            'reason' => 'Mantenimiento', 'tenantId' => Tenant::factory()->create()->id,
        ])->assertCreated()->assertJsonPath('data.startsAt', '2026-10-20T14:30:00.000000Z')
            ->assertJsonPath('data.startsAtLocal', '2026-10-20 09:30')->assertJsonPath('data.createdBy', $owner->id);

        $id = $response->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertDatabaseHas('salon_schedule_blocks', ['id' => $id, 'tenant_id' => $tenant->id, 'created_by' => $owner->id]);
        $this->getJson(route('api.admin.schedule.blocks.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson(route('api.admin.schedule.blocks.destroy', $id))->assertNoContent();
    }

    public function test_blocks_are_tenant_safe_and_clients_cannot_manage_them(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [$otherTenant, $otherOwner] = $this->tenantUser(UserRole::OWNER);
        [, $client] = $this->tenantUser(UserRole::CLIENT, $tenant);
        $block = $this->block($tenant, $owner);
        $this->block($otherTenant, $otherOwner);

        $this->actingAs($owner)->getJson(route('api.admin.schedule.blocks.index'))->assertJsonCount(1, 'data');
        $this->actingAs($otherOwner)->deleteJson(route('api.admin.schedule.blocks.destroy', $block))->assertNotFound();
        $this->actingAs($client)->postJson(route('api.admin.schedule.blocks.store'), [])->assertForbidden();
        $this->deleteJson(route('api.admin.schedule.blocks.destroy', $block))->assertForbidden();
    }

    public function test_block_validation_rejects_reverse_nonexistent_and_ambiguous_local_times(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/New_York']);
        $this->actingAs($owner);

        $this->postJson(route('api.admin.schedule.blocks.store'), [
            'startsAtLocal' => '2026-03-08 02:30', 'endsAtLocal' => '2026-03-08 04:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('startsAtLocal');
        $this->postJson(route('api.admin.schedule.blocks.store'), [
            'startsAtLocal' => '2026-11-01 01:30', 'endsAtLocal' => '2026-11-01 03:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('startsAtLocal');
        $this->postJson(route('api.admin.schedule.blocks.store'), [
            'startsAtLocal' => '2026-10-20 11:00', 'endsAtLocal' => '2026-10-20 10:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('endsAtLocal');
    }

    public function test_block_tenant_is_immutable(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        $block = $this->block($tenant, $owner);

        $this->expectException(LogicException::class);
        $block->update(['tenant_id' => Tenant::factory()->create()->id]);
    }

    public function test_appointment_creation_rejects_ranges_outside_schedule_and_inside_blocks(): void
    {
        CarbonImmutable::setTestNow('2026-10-18 12:00:00 UTC');
        [$tenant, $client] = $this->tenantUser(UserRole::CLIENT);
        [, $owner] = $this->tenantUser(UserRole::OWNER, $tenant);
        $service = SalonService::factory()->create([
            'tenant_id' => $tenant->id, 'duration_minutes' => 60,
            'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);
        $settings = SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/Bogota']);
        $settings->weeklyHours()->update(['opens_at' => '09:00', 'closes_at' => '18:00']);
        $this->block($tenant, $owner);
        $this->actingAs($client);

        $payload = ['bookingKey' => (string) Str::uuid(), 'serviceIds' => [$service->id]];
        $this->postJson(route('api.appointments.store'), $payload + ['startsAt' => '2026-10-19T12:00:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors('startsAt');
        $this->postJson(route('api.appointments.store'), [
            ...$payload, 'bookingKey' => (string) Str::uuid(), 'startsAt' => '2026-10-20T14:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors('startsAt');
    }

    /** @return array{Tenant, User} */
    private function tenantUser(UserRole $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::factory()->create();

        return [$tenant, User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role])];
    }

    /** @return array<string, mixed> */
    private function schedulePayload(): array
    {
        return [
            'timezone' => 'America/New_York', 'slotIntervalMinutes' => 20,
            'appointmentCapacity' => 3, 'cancellationNoticeHours' => 12,
            'weeklyHours' => collect(range(0, 6))->map(fn (int $weekday): array => [
                'weekday' => $weekday, 'closed' => false, 'opensAt' => '09:00', 'closesAt' => '18:00',
            ])->all(),
        ];
    }

    private function block(Tenant $tenant, User $user): SalonScheduleBlock
    {
        return $tenant->scheduleBlocks()->create([
            'starts_at' => '2026-10-20 14:00:00+00', 'ends_at' => '2026-10-20 15:00:00+00',
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }
}
