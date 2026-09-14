<?php

namespace App\Actions;

use App\Models\SalonService;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ResolveBookableServices
{
    /**
     * @param  list<string>  $serviceIds
     * @return Collection<int, SalonService>
     */
    public function handle(Tenant $tenant, array $serviceIds): Collection
    {
        $services = SalonService::query()
            ->where('tenant_id', $tenant->id)
            ->where('available', true)
            ->whereIn('id', $serviceIds)
            ->get();

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['serviceIds' => 'Todos los servicios deben existir y estar disponibles en este salón.']);
        }

        return $services;
    }
}
