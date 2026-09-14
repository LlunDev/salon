<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AppointmentCapacity
{
    /**
     * @param  Collection<int, object{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $appointments
     */
    public function isAvailable(Collection $appointments, CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $capacity): bool
    {
        return $this->maximumOverlap($appointments, $startsAt, $endsAt) < $capacity;
    }

    /**
     * @param  Collection<int, object{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $appointments
     */
    private function maximumOverlap(Collection $appointments, CarbonImmutable $startsAt, CarbonImmutable $endsAt): int
    {
        $events = [];

        foreach ($appointments as $appointment) {
            $start = max($appointment->starts_at->getTimestamp(), $startsAt->getTimestamp());
            $end = min($appointment->ends_at->getTimestamp(), $endsAt->getTimestamp());

            if ($start >= $end) {
                continue;
            }

            $events[] = [$start, 1];
            $events[] = [$end, -1];
        }

        usort($events, fn (array $left, array $right): int => $left[0] <=> $right[0] ?: $left[1] <=> $right[1]);

        $active = 0;
        $maximum = 0;
        foreach ($events as [, $change]) {
            $active += $change;
            $maximum = max($maximum, $active);
        }

        return $maximum;
    }
}
