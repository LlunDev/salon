<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Actions\TransitionAppointment;
use App\Enums\UserRole;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\CompleteAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->tenant($request)->appointments()->with('services')->latest('starts_at');

        if ($request->user()->role === UserRole::CLIENT) {
            $query->where('client_id', $request->user()->id);
        }

        return AppointmentResource::collection($query->paginate(15));
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $action): JsonResponse
    {
        $appointment = $action->handle(
            $this->tenant($request),
            $request->user(),
            $request->validated('bookingKey'),
            CarbonImmutable::parse($request->validated('startsAt')),
            $request->validated('serviceIds'),
        );

        return (new AppointmentResource($appointment))->response()->setStatusCode($appointment->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $appointment): AppointmentResource
    {
        $appointment = $this->appointment($request, $appointment);
        abort_if($request->user()->role === UserRole::CLIENT && $appointment->client_id !== $request->user()->id, 404);

        return new AppointmentResource($appointment);
    }

    public function cancel(CancelAppointmentRequest $request, string $appointment, TransitionAppointment $action): AppointmentResource
    {
        return new AppointmentResource($action->cancel(
            $this->appointment($request, $appointment),
            $request->user(),
            $request->validated('reason'),
            $request->validated('notifyClient'),
        ));
    }

    public function complete(CompleteAppointmentRequest $request, string $appointment, TransitionAppointment $action): AppointmentResource
    {
        return new AppointmentResource($action->complete($this->appointment($request, $appointment), $request->user()));
    }

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }

    private function appointment(Request $request, string $id): Appointment
    {
        return $this->tenant($request)->appointments()->with('services')->findOrFail($id);
    }
}
