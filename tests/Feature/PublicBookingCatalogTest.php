<?php

namespace Tests\Feature;

use App\Models\SalonService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBookingCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_public_catalog_for_the_resolved_salon(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Salón Aurora',
            'domain' => 'aurora.salon.test',
        ]);

        $this->get('http://aurora.salon.test/reservar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Services')
                ->where('salon.id', $tenant->id)
                ->where('salon.name', 'Salón Aurora'));
    }

    public function test_public_endpoint_only_returns_available_services_from_the_resolved_salon(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $otherTenant = Tenant::factory()->create();
        $creator = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherCreator = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $visible = $this->service($tenant, $creator, [
            'name' => 'Corte premium',
            'available' => true,
        ]);
        $this->service($tenant, $creator, [
            'name' => 'Servicio oculto',
            'available' => false,
        ]);
        $this->service($otherTenant, $otherCreator, [
            'name' => 'Servicio de otro salón',
            'available' => true,
        ]);

        $this->getJson('http://aurora.salon.test/api/public/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.name', 'Corte premium')
            ->assertJsonMissingPath('data.0.tenantId')
            ->assertJsonMissingPath('data.0.createdBy')
            ->assertJsonMissingPath('data.0.updatedBy');
    }

    public function test_localhost_public_catalog_uses_the_provisioned_tenant_session(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withSession(['provisioned_tenant_id' => $tenant->id])
            ->get('/reservar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Services')
                ->where('salon.id', $tenant->id));
    }

    public function test_stateless_localhost_public_api_returns_not_found_instead_of_crashing(): void
    {
        $this->getJson('/api/public/services')->assertNotFound();
    }

    public function test_guest_can_open_the_schedule_with_the_salon_timezone(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'calendar.salon.test']);
        $tenant->salonSetting()->create(['timezone' => 'America/Bogota']);

        $this->get('http://calendar.salon.test/reservar/fecha')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Schedule')
                ->where('salon.id', $tenant->id)
                ->where('salon.timezone', 'America/Bogota'));
    }

    public function test_authenticated_user_cannot_open_another_tenants_public_context(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($user)
            ->get('http://aurora.salon.test/reservar')
            ->assertForbidden();

        $this->assertNotSame($tenant->id, $otherTenant->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(Tenant $tenant, User $creator, array $attributes): SalonService
    {
        return SalonService::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
            ...$attributes,
        ]);
    }
}
