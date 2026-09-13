<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalonServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->attributes->get('tenant');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('salon_services')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($this->route('salonService')),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'duration' => ['sometimes', 'required', 'integer', 'between:1,1440'],
            'imageUrl' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'price' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
            'available' => ['sometimes', 'boolean'],
        ];
    }
}
