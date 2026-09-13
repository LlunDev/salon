<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_page_can_be_rendered(): void
    {
        $response = $this->get('/demo');

        $response->assertOk();
    }

    public function test_domain_availability_endpoint_reports_free_and_taken_domains(): void
    {
        $baseDomain = config('provisioning.base_domain');

        Tenant::create([
            'name' => 'Existing Salon',
            'domain' => 'existing.'.$baseDomain,
            'status' => 'active',
        ]);

        $freeResponse = $this->getJson(route('demo.domain-availability', ['subdomain' => 'fresh']))
            ->assertOk()
            ->assertJson([
                'available' => true,
                'full_domain' => 'fresh.'.$baseDomain,
            ]);

        $takenResponse = $this->getJson(route('demo.domain-availability', ['subdomain' => 'existing']))
            ->assertOk()
            ->assertJson([
                'available' => false,
                'full_domain' => 'existing.'.$baseDomain,
            ]);

        $this->assertSame('Este dominio esta disponible.', $freeResponse->json('message'));
        $this->assertSame('Este dominio ya esta en uso.', $takenResponse->json('message'));
    }

    public function test_provisioning_creates_tenant_and_owner(): void
    {
        $baseDomain = config('provisioning.base_domain');

        $response = $this->post(route('demo.provision'), [
            'salon_name' => 'Salon de Belleza Aurora',
            'subdomain' => 'aurora',
            'owner_first_name' => 'Ana',
            'owner_last_name' => 'Lopez',
            'owner_email' => 'ana@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms_accepted' => true,
        ]);

        $response->assertRedirect('/admin/dashboard');
        $response->assertSessionHas('success', 'Tu espacio de trabajo para el salon ya esta listo.');
        $response->assertSessionHas('provisioned_tenant_id');

        $tenant = Tenant::query()->where('domain', 'aurora.'.$baseDomain)->firstOrFail();
        $owner = User::query()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertTrue(Str::isUuid($tenant->id));
        $this->assertTrue(Str::isUuid($owner->id));
        $this->assertAuthenticatedAs($owner);
        $this->assertSame(UserRole::OWNER, $owner->role);
        $this->assertSame('OWNER', $owner->getRawOriginal('role'));
    }

    public function test_admin_dashboard_shows_the_provisioned_salon(): void
    {
        $baseDomain = config('provisioning.base_domain');

        $tenant = Tenant::create([
            'name' => 'Salon de Belleza Aurora',
            'domain' => 'aurora.'.$baseDomain,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Salon de Belleza Aurora');
        $response->assertSee('aurora.'.$baseDomain);
        $response->assertSee('Citas de hoy');
    }

    public function test_admin_dashboard_returns_404_when_tenant_domain_does_not_exist(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this
            ->actingAs($this->ownerFor($tenant))
            ->get('http://missing.'.config('provisioning.base_domain').'/admin/dashboard');

        $response->assertNotFound();
    }

    public function test_admin_dashboard_uses_the_authenticated_users_tenant_on_localhost(): void
    {
        $tenant = Tenant::create([
            'name' => 'Salon de Belleza Uno',
            'domain' => 'uno.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $latestTenant = Tenant::create([
            'name' => 'Salon de Belleza Dos',
            'domain' => 'dos.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->ownerFor($tenant))->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee($tenant->name);
        $response->assertDontSee($latestTenant->name);
    }

    public function test_admin_sections_can_be_loaded_for_navigation_validation(): void
    {
        $tenant = Tenant::create([
            'name' => 'Salon de Belleza Navegacion',
            'domain' => 'navegacion.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $this->actingAs($this->ownerFor($tenant));

        $this->get('/admin/services')->assertOk()->assertSee('Servicios');
        $this->get('/admin/reminders')->assertOk()->assertSee('Recordatorios');
        $this->get('/admin/users')->assertOk()->assertSee('Usuarios');
        $this->get('/admin/clients')->assertOk()->assertSee('Clientes');
        $this->get('/admin/settings')->assertOk()->assertSee('Configuracion');
    }

    public function test_owner_email_must_be_unique_for_owner_accounts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Salon',
            'domain' => 'existing-owner.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ana',
            'last_name' => 'Owner',
            'name' => 'Ana Owner',
            'email' => 'owner@example.com',
            'password' => 'Secret123!',
            'role' => UserRole::OWNER,
        ]);

        $response = $this->from('/demo')->post(route('demo.provision'), [
            'salon_name' => 'Salon de Belleza Luna',
            'subdomain' => 'luna',
            'owner_first_name' => 'Luna',
            'owner_last_name' => 'Owner',
            'owner_email' => 'owner@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms_accepted' => true,
        ]);

        $response->assertRedirect('/demo');
        $response->assertSessionHasErrors([
            'owner_email' => 'Ya existe una cuenta administradora con este correo.',
        ]);
    }

    public function test_subdomain_must_be_available_when_provisioning(): void
    {
        Tenant::create([
            'name' => 'Existing Salon',
            'domain' => 'taken.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $response = $this->from('/demo')->post(route('demo.provision'), [
            'salon_name' => 'Salon de Belleza Sol',
            'subdomain' => 'taken',
            'owner_first_name' => 'Sol',
            'owner_last_name' => 'Perez',
            'owner_email' => 'sol@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms_accepted' => true,
        ]);

        $response->assertRedirect('/demo');
        $response->assertSessionHasErrors([
            'subdomain' => 'Este dominio ya esta en uso.',
        ]);
    }

    public function test_terms_must_be_accepted_to_provision(): void
    {
        $response = $this->from('/demo')->post(route('demo.provision'), [
            'salon_name' => 'Salon de Belleza Brisa',
            'subdomain' => 'brisa',
            'owner_first_name' => 'Brisa',
            'owner_last_name' => 'Gomez',
            'owner_email' => 'brisa@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms_accepted' => false,
        ]);

        $response->assertRedirect('/demo');
        $response->assertSessionHasErrors([
            'terms_accepted' => 'Debes aceptar los terminos para continuar.',
        ]);
    }

    public function test_non_owner_email_can_be_reused_across_tenants(): void
    {
        $firstTenant = Tenant::create([
            'name' => 'First Salon',
            'domain' => 'first.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $secondTenant = Tenant::create([
            'name' => 'Second Salon',
            'domain' => 'second.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $firstTenant->id,
            'first_name' => 'Mia',
            'last_name' => 'Client',
            'name' => 'Mia Client',
            'email' => 'shared@example.com',
            'password' => 'Secret123!',
            'role' => UserRole::CLIENT,
        ]);

        User::create([
            'tenant_id' => $secondTenant->id,
            'first_name' => 'Nora',
            'last_name' => 'Client',
            'name' => 'Nora Client',
            'email' => 'shared@example.com',
            'password' => 'Secret123!',
            'role' => UserRole::CLIENT,
        ]);

        $this->assertSame(2, User::query()->where('email', 'shared@example.com')->count());
    }

    public function test_owner_email_is_also_enforced_by_the_database(): void
    {
        $firstTenant = Tenant::create([
            'name' => 'Owner One',
            'domain' => 'owner-one.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        $secondTenant = Tenant::create([
            'name' => 'Owner Two',
            'domain' => 'owner-two.'.config('provisioning.base_domain'),
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $firstTenant->id,
            'first_name' => 'Ana',
            'last_name' => 'Owner',
            'name' => 'Ana Owner',
            'email' => 'owner-db@example.com',
            'password' => 'Secret123!',
            'role' => UserRole::OWNER,
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'tenant_id' => $secondTenant->id,
            'first_name' => 'Bea',
            'last_name' => 'Owner',
            'name' => 'Bea Owner',
            'email' => 'owner-db@example.com',
            'password' => 'Secret123!',
            'role' => UserRole::OWNER,
        ]);
    }

    private function ownerFor(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::OWNER,
        ]);
    }
}
