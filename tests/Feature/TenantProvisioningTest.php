<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'tenant_id' => 1,
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

        $response->assertRedirect('http://aurora.'.$baseDomain.':8000/admin/dashboard');
        $response->assertSessionHas('success', 'Tu espacio de trabajo para el salon ya esta listo.');

        $tenant = Tenant::query()->where('domain', 'aurora.'.$baseDomain)->firstOrFail();
        $owner = User::query()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame($tenant->id, $tenant->tenant_id);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame(UserRole::OWNER, $owner->role);
        $this->assertSame('OWNER', $owner->getRawOriginal('role'));
    }

    public function test_admin_dashboard_shows_the_provisioned_salon(): void
    {
        $baseDomain = config('provisioning.base_domain');

        $tenant = Tenant::create([
            'name' => 'Salon de Belleza Aurora',
            'domain' => 'aurora.'.$baseDomain,
            'tenant_id' => 1,
            'status' => 'active',
        ]);

        $response = $this->get('http://aurora.'.$baseDomain.'/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Salon de Belleza Aurora');
        $response->assertSee('aurora.'.$baseDomain);
        $response->assertSee('Citas de hoy');
    }

    public function test_admin_dashboard_returns_404_when_tenant_domain_does_not_exist(): void
    {
        $response = $this->get('http://missing.'.config('provisioning.base_domain').'/admin/dashboard');

        $response->assertNotFound();
    }

    public function test_owner_email_must_be_unique_for_owner_accounts(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Salon',
            'domain' => 'existing-owner.'.config('provisioning.base_domain'),
            'tenant_id' => 1,
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
            'tenant_id' => 1,
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
            'tenant_id' => 1,
            'status' => 'active',
        ]);

        $secondTenant = Tenant::create([
            'name' => 'Second Salon',
            'domain' => 'second.'.config('provisioning.base_domain'),
            'tenant_id' => 2,
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
            'tenant_id' => 1,
            'status' => 'active',
        ]);

        $secondTenant = Tenant::create([
            'name' => 'Owner Two',
            'domain' => 'owner-two.'.config('provisioning.base_domain'),
            'tenant_id' => 2,
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
}
