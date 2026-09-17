<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\SalonScheduleBlock;
use App\Models\SalonService;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAgendaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_a_chronological_salon_local_day_with_clients_and_blocks(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $client] = $this->tenantUser(UserRole::CLIENT, $tenant);
        SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/Bogota']);
        $late = $this->appointment($tenant, $client, '2026-10-21 03:30:00+00');
        $early = $this->appointment($tenant, $client, '2026-10-20 14:00:00+00');
        $this->appointment($tenant, $client, '2026-10-21 06:00:00+00');
        SalonScheduleBlock::query()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => '2026-10-20 17:00:00+00',
            'ends_at' => '2026-10-20 18:00:00+00',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)->getJson(route('api.admin.agenda.index', ['date' => '2026-10-20']))
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Bogota')
            ->assertJsonPath('data.appointments.0.id', $early->id)
            ->assertJsonPath('data.appointments.1.id', $late->id)
            ->assertJsonPath('data.appointments.0.client.email', $client->email)
            ->assertJsonCount(2, 'data.appointments')
            ->assertJsonCount(1, 'data.blocks');
    }

    public function test_admin_agenda_and_client_search_are_role_and_tenant_scoped(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $client] = $this->tenantUser(UserRole::CLIENT, $tenant);
        [$otherTenant, $otherClient] = $this->tenantUser(UserRole::CLIENT);

        $this->actingAs($client)->getJson(route('api.admin.agenda.index', ['date' => '2026-10-20']))->assertForbidden();
        $this->actingAs($client)->getJson(route('api.admin.clients.index'))->assertForbidden();
        $this->actingAs($owner)->getJson(route('api.admin.clients.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonMissing(['id' => $otherClient->id]);

        $this->assertNotSame($tenant->id, $otherTenant->id);
    }

    public function test_owner_can_create_a_manual_appointment_for_a_tenant_client(): void
    {
        CarbonImmutable::setTestNow('2026-10-18 12:00:00 UTC');
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $client] = $this->tenantUser(UserRole::CLIENT, $tenant);
        $service = SalonService::factory()->create([
            'tenant_id' => $tenant->id,
            'duration_minutes' => 60,
            'price' => '35.00',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $settings = SalonSetting::query()->create(['tenant_id' => $tenant->id, 'timezone' => 'America/Bogota']);
        $settings->weeklyHours()->update(['opens_at' => '09:00', 'closes_at' => '18:00']);

        $response = $this->actingAs($owner)->postJson(route('api.admin.appointments.store'), [
            'clientId' => $client->id,
            'startsAtLocal' => '2026-10-20 10:00',
            'serviceIds' => [$service->id],
        ])->assertCreated()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.createdBy', $owner->id)
            ->assertJsonPath('data.startsAt', '2026-10-20T15:00:00.000000Z');

        $this->assertDatabaseHas('appointments', [
            'id' => $response->json('data.id'),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'status' => AppointmentStatus::CONFIRMED->value,
        ]);
    }

    public function test_manual_creation_rejects_clients_from_other_tenants(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $otherClient] = $this->tenantUser(UserRole::CLIENT);
        $service = SalonService::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'updated_by' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.admin.appointments.store'), [
            'clientId' => $otherClient->id,
            'startsAtLocal' => '2026-10-20 10:00',
            'serviceIds' => [$service->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('clientId');
    }

    public function test_dashboard_renders_the_real_agenda_only_for_admin_roles(): void
    {
        [$tenant, $owner] = $this->tenantUser(UserRole::OWNER);
        [, $staff] = $this->tenantUser(UserRole::STAFF, $tenant);
        [, $client] = $this->tenantUser(UserRole::CLIENT, $tenant);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')->where('salon.name', $tenant->name));
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($client)->get(route('admin.dashboard'))->assertForbidden();
    }

    /** @return array{Tenant, User} */
    private function tenantUser(UserRole $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::factory()->create();

        return [$tenant, User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role])];
    }

    private function appointment(Tenant $tenant, User $client, string $startsAt): Appointment
    {
        $start = CarbonImmutable::parse($startsAt);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
        $appointment->services()->create([
            'name' => 'Corte',
            'duration_minutes' => 60,
            'price' => '25.00',
        ]);

        return $appointment;
    }
}
