<?php

namespace Database\Factories;

use App\Models\SalonService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalonService>
 */
class SalonServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => Tenant::factory()->create()->id,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->numberBetween(15, 240),
            'image_url' => fake()->optional()->imageUrl(),
            'price' => fake()->randomFloat(2, 0, 500),
            'available' => true,
            'created_by' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
