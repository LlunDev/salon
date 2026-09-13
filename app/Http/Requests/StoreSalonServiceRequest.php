<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalonServiceRequest extends FormRequest
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
                'required',
                'string',
                'max:120',
                Rule::unique('salon_services')->where('tenant_id', $tenant->id),
            ],
            'description' => ['nullable', 'string'],
            'duration' => ['required', 'integer', 'between:1,1440'],
            'imageUrl' => ['nullable', 'url', 'max:2048'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
            'available' => ['sometimes', 'boolean'],
        ];
    }
}
