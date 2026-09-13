<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalonServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration_minutes,
            'imageUrl' => $this->image_url,
            'price' => $this->price,
            'available' => $this->available,
            'createdAt' => $this->created_at?->toISOString(),
            'createdBy' => $this->created_by,
            'updatedAt' => $this->updated_at?->toISOString(),
            'updatedBy' => $this->updated_by,
        ];
    }
}
