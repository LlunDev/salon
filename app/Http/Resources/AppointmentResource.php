<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenant_id,
            'clientId' => $this->client_id,
            'bookingKey' => $this->booking_key,
            'status' => $this->status->value,
            'startsAt' => $this->starts_at->toISOString(),
            'endsAt' => $this->ends_at->toISOString(),
            'duration' => $this->duration_minutes,
            'total' => $this->total,
            'createdBy' => $this->created_by,
            'updatedBy' => $this->updated_by,
            'services' => $this->services->map(fn ($service): array => [
                'serviceId' => $service->salon_service_id,
                'name' => $service->name,
                'duration' => $service->duration_minutes,
                'price' => $service->price,
            ]),
            'cancellationReason' => $this->cancellation_reason,
            'notifyClient' => $this->notify_client,
            'cancelledBy' => $this->cancelled_by,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'completedBy' => $this->completed_by,
            'completedAt' => $this->completed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
