<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $appointment): void {
            $immutable = ['tenant_id', 'client_id', 'booking_key', 'starts_at', 'ends_at', 'duration_minutes', 'total', 'created_by'];

            if ($appointment->isDirty($immutable)) {
                throw new LogicException('Los datos de la reserva son inmutables.');
            }
        });

        static::deleting(fn () => throw new LogicException('Las citas no se pueden eliminar.'));
    }

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'total' => 'decimal:2',
            'duration_minutes' => 'integer',
            'notify_client' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }
}
