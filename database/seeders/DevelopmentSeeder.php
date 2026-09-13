<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['domain' => 'dev.'.config('provisioning.base_domain')],
            [
                'name' => 'Development Salon',
                'status' => 'active',
            ],
        );

        User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => 'dev@example.com',
            ],
            [
                'first_name' => 'Dev',
                'last_name' => 'User',
                'name' => 'dev',
                'password' => 'dev1234',
                'role' => UserRole::OWNER,
            ],
        );
    }
}
