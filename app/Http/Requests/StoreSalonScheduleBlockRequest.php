<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalonScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::OWNER, UserRole::STAFF], true);
    }

    public function rules(): array
    {
        return [
            'startsAtLocal' => ['required', 'string'],
            'endsAtLocal' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
