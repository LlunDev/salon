<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_rendered_for_the_resolved_salon(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Salón Aurora',
            'domain' => 'aurora.salon.test',
        ]);

        $this->get('http://aurora.salon.test/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('salon.id', $tenant->id)
                ->where('salon.name', 'Salón Aurora'));
    }

    public function test_localhost_login_uses_the_latest_tenant_when_the_browser_has_no_tenant_session(): void
    {
        Tenant::factory()->create(['name' => 'Salón anterior', 'created_at' => now()->subDay()]);
        $latestTenant = Tenant::factory()->create(['name' => 'Salón local', 'created_at' => now()]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('salon.id', $latestTenant->id)
                ->where('salon.name', 'Salón local'));
    }

    public function test_same_login_authenticates_clients_staff_and_owners_within_the_tenant(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);

        foreach ([UserRole::CLIENT, UserRole::STAFF, UserRole::OWNER] as $role) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'email' => strtolower($role->value).'@example.com',
                'role' => $role,
            ]);

            $response = $this->post('http://aurora.salon.test/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $this->assertAuthenticatedAs($user);
            $response->assertRedirect($role === UserRole::CLIENT ? '/reservar/cuenta' : '/admin/dashboard');
            $this->post('http://aurora.salon.test/logout');
        }
    }

    public function test_login_selects_the_account_matching_both_tenant_and_email(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $otherTenant = Tenant::factory()->create(['domain' => 'brisa.salon.test']);
        $email = 'cliente@example.com';
        $expected = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'password' => 'aurora-password',
        ]);
        User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => $email,
            'password' => 'brisa-password',
        ]);

        $this->post('http://aurora.salon.test/login', [
            'email' => $email,
            'password' => 'aurora-password',
        ])->assertRedirect('/reservar/cuenta');

        $this->assertAuthenticatedAs($expected);
    }

    public function test_account_from_another_tenant_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'password',
        ]);
        Tenant::factory()->create(['domain' => 'other.salon.test']);

        $this->post('http://other.salon.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_invalid_password_does_not_authenticate_the_user(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->post('http://aurora.salon.test/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_guest_is_returned_to_the_protected_booking_step_after_login(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->get('http://aurora.salon.test/reservar/cuenta')->assertRedirect('/login');

        $this->post('http://aurora.salon.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('http://aurora.salon.test/reservar/cuenta');
    }

    public function test_staff_and_owners_are_sent_to_admin_even_when_booking_was_intended(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::STAFF,
        ]);

        $this->get('http://aurora.salon.test/reservar/cuenta')->assertRedirect('/login');

        $this->post('http://aurora.salon.test/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_users_can_logout(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->post('http://aurora.salon.test/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
