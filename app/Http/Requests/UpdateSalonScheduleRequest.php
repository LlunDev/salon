<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSalonScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::OWNER, UserRole::STAFF], true);
    }

    public function rules(): array
    {
        return [
            'timezone' => ['required', 'timezone:all'],
            'slotIntervalMinutes' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60])],
            'appointmentCapacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'cancellationNoticeHours' => ['required', 'integer', 'min:0', 'max:65535'],
            'weeklyHours' => ['required', 'array', 'size:7'],
            'weeklyHours.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'weeklyHours.*.closed' => ['required', 'boolean'],
            'weeklyHours.*.opensAt' => ['nullable', 'date_format:H:i'],
            'weeklyHours.*.closesAt' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('weeklyHours', []) as $index => $hours) {
                $closed = filter_var($hours['closed'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $opensAt = $hours['opensAt'] ?? null;
                $closesAt = $hours['closesAt'] ?? null;

                if ($closed === true && ($opensAt !== null || $closesAt !== null)) {
                    $validator->errors()->add("weeklyHours.$index.opensAt", 'Un día cerrado no puede tener horas de apertura o cierre.');
                }

                if ($closed === false && (! $opensAt || ! $closesAt || $opensAt >= $closesAt)) {
                    $validator->errors()->add("weeklyHours.$index.opensAt", 'La hora de apertura debe ser anterior a la hora de cierre.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'timezone.timezone' => 'La zona horaria debe ser un identificador IANA válido.',
            'weeklyHours.size' => 'Debes enviar exactamente los siete días de la semana.',
            'weeklyHours.*.weekday.distinct' => 'Cada día de la semana debe aparecer una sola vez.',
            'slotIntervalMinutes.in' => 'El intervalo debe dividir una hora de forma exacta.',
        ];
    }
}
