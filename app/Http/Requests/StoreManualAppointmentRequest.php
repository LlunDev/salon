<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::OWNER, UserRole::STAFF], true);
    }

    public function rules(): array
    {
        return [
            'clientId' => ['required', 'uuid'],
            'startsAtLocal' => ['required', 'string'],
            'serviceIds' => ['required', 'array', 'min:1'],
            'serviceIds.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
