<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::CLIENT;
    }

    public function rules(): array
    {
        return [
            'bookingKey' => ['required', 'uuid'],
            'startsAt' => ['required', 'date', 'after:now'],
            'serviceIds' => ['required', 'array', 'min:1'],
            'serviceIds.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'bookingKey.required' => 'La clave de reserva es obligatoria.',
            'bookingKey.uuid' => 'La clave de reserva debe ser un UUID válido.',
            'startsAt.required' => 'La fecha de inicio es obligatoria.',
            'startsAt.date' => 'La fecha de inicio no es válida.',
            'startsAt.after' => 'La fecha de inicio debe ser futura.',
            'serviceIds.required' => 'Debes seleccionar al menos un servicio.',
            'serviceIds.array' => 'La selección de servicios no es válida.',
            'serviceIds.min' => 'Debes seleccionar al menos un servicio.',
            'serviceIds.*.required' => 'Cada servicio seleccionado es obligatorio.',
            'serviceIds.*.uuid' => 'Cada servicio seleccionado debe tener un UUID válido.',
            'serviceIds.*.distinct' => 'No puedes seleccionar el mismo servicio más de una vez.',
        ];
    }
}
