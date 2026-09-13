<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

class ParseSalonLocalDateTime
{
    public function handle(string $value, string $timezone, string $field): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, 'UTC');
        $errors = CarbonImmutable::getLastErrors();

        if (! $local || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $local->format('Y-m-d H:i') !== $value) {
            throw ValidationException::withMessages([$field => 'La fecha y hora local no son válidas.']);
        }

        $zone = new DateTimeZone($timezone);
        $wallTimestamp = $local->getTimestamp();
        $offsets = collect($zone->getTransitions($wallTimestamp - 172800, $wallTimestamp + 172800))
            ->pluck('offset')
            ->unique();

        $candidates = $offsets->map(fn (int $offset): CarbonImmutable => CarbonImmutable::createFromTimestampUTC($wallTimestamp - $offset))
            ->filter(fn (CarbonImmutable $candidate): bool => $candidate->setTimezone($timezone)->format('Y-m-d H:i') === $value)
            ->unique(fn (CarbonImmutable $candidate): int => $candidate->getTimestamp())
            ->values();

        if ($candidates->count() !== 1) {
            throw ValidationException::withMessages([$field => 'La fecha y hora local no existe o es ambigua en la zona horaria del salón.']);
        }

        return $candidates->first();
    }
}
