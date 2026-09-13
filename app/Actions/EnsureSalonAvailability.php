<?php

namespace App\Actions;

use App\Models\SalonSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EnsureSalonAvailability
{
    public function handle(Tenant $tenant, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['startsAt' => 'El rango de la cita no es válido.']);
        }

        $settings = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $localStart = $startsAt->utc()->setTimezone($settings->timezone);
        $localEnd = $endsAt->utc()->setTimezone($settings->timezone);

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            throw ValidationException::withMessages(['startsAt' => 'La cita debe comenzar y terminar el mismo día local.']);
        }

        $hours = $tenant->weeklyHours()->where('weekday', $localStart->dayOfWeek)->first();
        if (! $hours || $hours->closed) {
            throw ValidationException::withMessages(['startsAt' => 'El salón está cerrado en este horario.']);
        }

        $startTime = $localStart->format('H:i:s');
        $endTime = $localEnd->format('H:i:s');
        if ($startTime < $hours->opens_at || $endTime > $hours->closes_at) {
            throw ValidationException::withMessages(['startsAt' => 'La cita debe estar completamente dentro del horario del salón.']);
        }

        $blocked = $tenant->scheduleBlocks()
            ->where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages(['startsAt' => 'Este horario está bloqueado por el salón.']);
        }
    }
}
