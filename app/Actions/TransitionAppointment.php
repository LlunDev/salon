<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\SalonSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionAppointment
{
    public function cancel(Appointment $appointment, User $actor, ?string $reason, ?bool $notifyClient): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason, $notifyClient): Appointment {
            $appointment = Appointment::query()
                ->whereKey($appointment->id)
                ->where('tenant_id', $appointment->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->status !== AppointmentStatus::CONFIRMED) {
                throw ValidationException::withMessages(['status' => 'Solo se pueden cancelar citas confirmadas.']);
            }

            if ($actor->role === UserRole::CLIENT) {
                abort_unless($appointment->client_id === $actor->id, 404);
                $noticeHours = SalonSetting::query()->firstOrCreate(
                    ['tenant_id' => $appointment->tenant_id],
                    ['appointment_capacity' => 1, 'cancellation_notice_hours' => 24],
                )->cancellation_notice_hours;

                if (now()->gt($appointment->starts_at->subHours($noticeHours))) {
                    throw ValidationException::withMessages(['status' => 'Ha vencido el plazo permitido para cancelar la cita.']);
                }

                $reason = null;
                $notifyClient = null;
            }

            $appointment->forceFill([
                'status' => AppointmentStatus::CANCELLED,
                'cancellation_reason' => $reason,
                'notify_client' => $notifyClient,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            return $appointment->refresh()->load('services');
        });
    }

    public function complete(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor): Appointment {
            $appointment = Appointment::query()
                ->whereKey($appointment->id)
                ->where('tenant_id', $appointment->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($appointment->status !== AppointmentStatus::CONFIRMED) {
                throw ValidationException::withMessages(['status' => 'Solo se pueden completar citas confirmadas.']);
            }

            if (now()->lt($appointment->starts_at)) {
                throw ValidationException::withMessages(['status' => 'No se puede completar una cita antes de su hora de inicio.']);
            }

            $appointment->forceFill([
                'status' => AppointmentStatus::COMPLETED,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            return $appointment->refresh()->load('services');
        });
    }
}
