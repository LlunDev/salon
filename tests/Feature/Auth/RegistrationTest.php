<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_central_registration_allows_an_email_used_by_a_tenant_user(): void
    {
        $tenant = Tenant::create([
            'name' => 'Aurora Salon',
            'domain' => 'aurora.salon.test',
            'tenant_id' => 1,
            'status' => 'active',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ana',
            'last_name' => 'Client',
            'name' => 'Ana Client',
            'email' => 'shared@example.com',
            'password' => 'password',
            'role' => UserRole::CLIENT,
        ]);

        $response = $this->post('/register', [
            'name' => 'Central User',
            'email' => 'shared@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'tenant_id' => null,
            'email' => 'shared@example.com',
        ]);
    }
}
