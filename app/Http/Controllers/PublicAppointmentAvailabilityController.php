<?php

namespace App\Http\Controllers;

use App\Actions\CalculateAvailableAppointmentSlots;
use App\Http\Requests\PublicAppointmentAvailabilityRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class PublicAppointmentAvailabilityController extends Controller
{
    public function __invoke(PublicAppointmentAvailabilityRequest $request, CalculateAvailableAppointmentSlots $calculateSlots): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $validated = $request->validated();

        return response()->json([
            'data' => $calculateSlots->handle($tenant, $validated['date'], $validated['serviceIds']),
        ]);
    }
}
