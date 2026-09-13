<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDay();

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'booking_key' => Str::uuid(),
            'status' => AppointmentStatus::CONFIRMED,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'duration_minutes' => 60,
            'total' => '25.00',
            'created_by' => fn (array $attributes) => $attributes['client_id'],
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }
}
