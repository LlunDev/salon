<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_scoped_to_the_resolved_salon(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Salón Aurora',
            'domain' => 'aurora.salon.test',
        ]);

        $this->get('http://aurora.salon.test/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('salon.id', $tenant->id)
                ->where('salon.name', 'Salón Aurora'));
    }

    public function test_guest_can_register_as_a_client_of_the_resolved_salon(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);

        $response = $this->post('http://aurora.salon.test/register', [
            'name' => 'Ana López',
            'phone' => '+57 300 123 4567',
            'email' => 'ANA@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('ana@example.com', $user->email);
        $this->assertSame('+57 300 123 4567', $user->phone);
        $this->assertSame(UserRole::CLIENT, $user->role);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/reservar/cuenta');
    }

    public function test_same_email_can_register_independent_accounts_in_different_salons(): void
    {
        $firstTenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        $secondTenant = Tenant::factory()->create(['domain' => 'brisa.salon.test']);
        User::factory()->create([
            'tenant_id' => $firstTenant->id,
            'email' => 'cliente@example.com',
        ]);

        $this->post('http://brisa.salon.test/register', [
            'name' => 'Ana López',
            'phone' => '+57 300 123 4567',
            'email' => 'cliente@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/reservar/cuenta');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $secondTenant->id,
            'email' => 'cliente@example.com',
            'role' => UserRole::CLIENT->value,
        ]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_email_must_be_unique_within_the_same_salon(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'aurora.salon.test']);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'cliente@example.com',
        ]);

        $this->from('http://aurora.salon.test/register')
            ->post('http://aurora.salon.test/register', [
                'name' => 'Ana López',
                'phone' => '+57 300 123 4567',
                'email' => 'cliente@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect('http://aurora.salon.test/register')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_valid_contact_data(): void
    {
        Tenant::factory()->create(['domain' => 'aurora.salon.test']);

        $this->post('http://aurora.salon.test/register', [
            'name' => '',
            'phone' => 'invalid',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'phone', 'email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
