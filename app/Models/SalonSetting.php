<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'timezone', 'slot_interval_minutes', 'appointment_capacity', 'cancellation_notice_hours'])]
class SalonSetting extends Model
{
    protected $attributes = [
        'timezone' => 'America/Bogota',
        'slot_interval_minutes' => 15,
        'appointment_capacity' => 1,
        'cancellation_notice_hours' => 24,
    ];

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::created(function (self $settings): void {
            $settings->weeklyHours()->createMany(collect(range(0, 6))->map(fn (int $weekday): array => [
                'weekday' => $weekday,
                'closed' => false,
                'opens_at' => '00:00',
                'closes_at' => '23:59',
            ])->all());
        });
    }

    protected function casts(): array
    {
        return [
            'slot_interval_minutes' => 'integer',
            'appointment_capacity' => 'integer',
            'cancellation_notice_hours' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function weeklyHours(): HasMany
    {
        return $this->hasMany(SalonWeeklyHour::class, 'tenant_id', 'tenant_id');
    }
}
