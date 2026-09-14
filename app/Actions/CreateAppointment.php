<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\SalonSetting;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAppointment
{
    public function __construct(
        private readonly EnsureSalonAvailability $ensureSalonAvailability,
        private readonly ResolveBookableServices $resolveBookableServices,
        private readonly AppointmentCapacity $appointmentCapacity,
    ) {}

    /**
     * @param  list<string>  $serviceIds
     */
    public function handle(Tenant $tenant, User $client, string $bookingKey, CarbonImmutable $startsAt, array $serviceIds): Appointment
    {
        return DB::transaction(function () use ($tenant, $client, $bookingKey, $startsAt, $serviceIds): Appointment {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $existing = Appointment::query()
                ->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)
                ->where('booking_key', $bookingKey)
                ->first();

            if ($existing) {
                return $existing->load('services');
            }

            $settings = SalonSetting::query()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['timezone' => 'America/Bogota', 'slot_interval_minutes' => 15, 'appointment_capacity' => 1, 'cancellation_notice_hours' => 24],
            );
            $services = $this->resolveBookableServices->handle($tenant, $serviceIds);

            $duration = $services->sum('duration_minutes');
            $endsAt = $startsAt->addMinutes($duration);

            $this->ensureSalonAvailability->handle($tenant, $startsAt, $endsAt);

            $overlaps = Appointment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', AppointmentStatus::CONFIRMED)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->get(['starts_at', 'ends_at']);

            if (! $this->appointmentCapacity->isAvailable($overlaps, $startsAt, $endsAt, $settings->appointment_capacity)) {
                throw ValidationException::withMessages(['startsAt' => 'El salón no tiene capacidad disponible para este horario.']);
            }

            $totalCents = $services->sum(fn ($service): int => (int) round(((float) $service->price) * 100));
            $appointment = Appointment::query()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'booking_key' => $bookingKey,
                'status' => AppointmentStatus::CONFIRMED,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $duration,
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'created_by' => $client->id,
                'updated_by' => $client->id,
            ]);

            $appointment->services()->createMany($services->map(fn ($service): array => [
                'salon_service_id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
            ])->all());

            return $appointment->load('services');
        });
    }
}
