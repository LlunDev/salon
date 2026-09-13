<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, UserRole::cases(), true);
    }

    public function rules(): array
    {
        $isSalonUser = in_array($this->user()?->role, [UserRole::OWNER, UserRole::STAFF], true);

        return [
            'reason' => [Rule::prohibitedIf(! $isSalonUser), 'nullable', 'string', 'max:1000'],
            'notifyClient' => [Rule::prohibitedIf(! $isSalonUser), 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.prohibited' => 'Los clientes no pueden indicar un motivo de cancelación.',
            'reason.string' => 'El motivo de cancelación debe ser texto.',
            'reason.max' => 'El motivo de cancelación no puede superar los 1000 caracteres.',
            'notifyClient.prohibited' => 'Los clientes no pueden configurar la notificación de cancelación.',
            'notifyClient.boolean' => 'La opción de notificar al cliente debe ser verdadera o falsa.',
        ];
    }
}
