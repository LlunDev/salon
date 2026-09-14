<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\SalonSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CalculateAvailableAppointmentSlots
{
    public function __construct(
        private readonly ResolveBookableServices $resolveBookableServices,
        private readonly ParseSalonLocalDateTime $parseSalonLocalDateTime,
        private readonly AppointmentCapacity $appointmentCapacity,
    ) {}

    /**
     * @param  list<string>  $serviceIds
     * @return array{date: string, timezone: string, duration: int, slotIntervalMinutes: int, slots: list<array{startsAt: string, endsAt: string, startsAtLocal: string, endsAtLocal: string}>}
     */
    public function handle(Tenant $tenant, string $date, array $serviceIds): array
    {
        $settings = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        $services = $this->resolveBookableServices->handle($tenant, $serviceIds);
        $duration = (int) $services->sum('duration_minutes');
        $localDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, $settings->timezone);

        if (! $localDate || $localDate->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => 'La fecha no es válida.']);
        }

        $hours = $tenant->weeklyHours()->where('weekday', $localDate->dayOfWeek)->first();
        if (! $hours || $hours->closed) {
            return $this->result($date, $settings, $duration, []);
        }

        $dayStart = $localDate->startOfDay()->utc();
        $dayEnd = $localDate->addDay()->startOfDay()->utc();
        $blocks = $tenant->scheduleBlocks()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at']);
        $appointments = $tenant->appointments()
            ->where('status', AppointmentStatus::CONFIRMED)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at']);

        $opening = substr($hours->opens_at, 0, 5);
        $closing = substr($hours->closes_at, 0, 5);
        $slots = [];

        for ($minutes = 0; $minutes < 1440; $minutes += $settings->slot_interval_minutes) {
            $wallStart = CarbonImmutable::createFromFormat('!H:i', $opening, 'UTC')->addMinutes($minutes);
            if ($wallStart->format('H:i') >= $closing) {
                break;
            }

            try {
                $startsAt = $this->parseSalonLocalDateTime->handle("{$date} {$wallStart->format('H:i')}", $settings->timezone, 'date');
            } catch (ValidationException) {
                continue;
            }

            $endsAt = $startsAt->addMinutes($duration);
            $localEnd = $endsAt->setTimezone($settings->timezone);

            if ($startsAt->lessThanOrEqualTo(now())
                || $localEnd->toDateString() !== $date
                || $localEnd->format('H:i:s') > $hours->closes_at
                || $this->intersects($blocks, $startsAt, $endsAt)
                || ! $this->appointmentCapacity->isAvailable($appointments, $startsAt, $endsAt, $settings->appointment_capacity)) {
                continue;
            }

            $slots[] = [
                'startsAt' => $startsAt->toISOString(),
                'endsAt' => $endsAt->toISOString(),
                'startsAtLocal' => $startsAt->setTimezone($settings->timezone)->format('Y-m-d H:i'),
                'endsAtLocal' => $localEnd->format('Y-m-d H:i'),
            ];
        }

        return $this->result($date, $settings, $duration, $slots);
    }

    /**
     * @param  Collection<int, object{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $ranges
     */
    private function intersects(Collection $ranges, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $ranges->contains(fn ($range): bool => $range->starts_at->lessThan($endsAt) && $range->ends_at->greaterThan($startsAt));
    }

    /**
     * @param  list<array{startsAt: string, endsAt: string, startsAtLocal: string, endsAtLocal: string}>  $slots
     * @return array{date: string, timezone: string, duration: int, slotIntervalMinutes: int, slots: list<array{startsAt: string, endsAt: string, startsAtLocal: string, endsAtLocal: string}>}
     */
    private function result(string $date, SalonSetting $settings, int $duration, array $slots): array
    {
        return [
            'date' => $date,
            'timezone' => $settings->timezone,
            'duration' => $duration,
            'slotIntervalMinutes' => $settings->slot_interval_minutes,
            'slots' => $slots,
        ];
    }
}
