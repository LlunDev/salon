<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SalonService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalonServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_tenant_user_can_create_a_service(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $this->actingAs($user);

        $response = $this->postJson(route('api.admin.services.store'), [
            'name' => 'Haircut',
            'description' => 'Haircut and styling',
            'duration' => 90,
            'imageUrl' => 'https://example.com/haircut.jpg',
            'price' => '35.50',
            'available' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tenantId', $tenant->id)
            ->assertJsonPath('data.duration', 90)
            ->assertJsonPath('data.price', '35.50')
            ->assertJsonPath('data.createdBy', $user->id)
            ->assertJsonPath('data.updatedBy', $user->id);

        $service = SalonService::query()->sole();

        $this->assertTrue(Str::isUuid($service->id));
        $this->assertDatabaseHas('salon_services', [
            'id' => $service->id,
            'tenant_id' => $tenant->id,
            'duration_minutes' => 90,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_service_payload_cannot_override_tenant_or_audit_fields(): void
    {
        [$tenant, $user] = $this->tenantUser();
        [$otherTenant, $otherUser] = $this->tenantUser();
        $this->actingAs($user);

        $this->postJson(route('api.admin.services.store'), [
            'tenantId' => $otherTenant->id,
            'name' => 'Color',
            'duration' => 60,
            'price' => 45,
            'createdBy' => $otherUser->id,
            'updatedBy' => $otherUser->id,
        ])->assertCreated();

        $this->assertDatabaseHas('salon_services', [
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function test_index_only_returns_services_from_the_authenticated_tenant(): void
    {
        [$tenant, $user] = $this->tenantUser();
        [$otherTenant, $otherUser] = $this->tenantUser();
        $this->service($tenant, $user, ['name' => 'Visible service']);
        $this->service($otherTenant, $otherUser, ['name' => 'Foreign service']);
        $this->actingAs($user);

        $response = $this->getJson(route('api.admin.services.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible service');
    }

    public function test_tenant_cannot_access_or_mutate_another_tenants_service(): void
    {
        [$tenant, $user] = $this->tenantUser();
        [$otherTenant, $otherUser] = $this->tenantUser();
        $service = $this->service($tenant, $user);
        $this->actingAs($otherUser);

        $this->getJson(route('api.admin.services.show', $service))->assertNotFound();
        $this->patchJson(route('api.admin.services.update', $service), ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson(route('api.admin.services.destroy', $service))->assertNotFound();

        $this->assertDatabaseHas('salon_services', [
            'id' => $service->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertNotSame($tenant->id, $otherTenant->id);
    }

    public function test_service_can_be_updated_and_available_does_not_delete_it(): void
    {
        [$tenant, $creator] = $this->tenantUser();
        $updater = User::factory()->create(['tenant_id' => $tenant->id]);
        $service = $this->service($tenant, $creator);
        $this->actingAs($updater);

        $this->patchJson(route('api.admin.services.update', $service), [
            'duration' => 120,
            'price' => '50.00',
            'available' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.duration', 120)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.updatedBy', $updater->id);

        $this->assertDatabaseHas('salon_services', [
            'id' => $service->id,
            'available' => false,
            'updated_by' => $updater->id,
        ]);
    }

    public function test_destroy_physically_deletes_the_service(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $service = $this->service($tenant, $user);
        $this->actingAs($user);

        $this->deleteJson(route('api.admin.services.destroy', $service))->assertNoContent();

        $this->assertDatabaseMissing('salon_services', ['id' => $service->id]);
    }

    public function test_service_validation_rejects_invalid_scheduling_and_money_values(): void
    {
        [, $user] = $this->tenantUser();
        $this->actingAs($user);

        $this->postJson(route('api.admin.services.store'), [
            'name' => '',
            'duration' => 1441,
            'imageUrl' => 'not-a-url',
            'price' => '10.999',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'duration', 'imageUrl', 'price']);
    }

    public function test_service_api_requires_authentication(): void
    {
        $this->getJson(route('api.admin.services.index'))->assertUnauthorized();
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::OWNER,
        ]);

        return [$tenant, $user];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(Tenant $tenant, User $user, array $attributes = []): SalonService
    {
        return SalonService::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            ...$attributes,
        ]);
    }
}
