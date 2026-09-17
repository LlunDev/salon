<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\SalonSetting;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicSalonController extends Controller
{
    public function services(Request $request): Response
    {
        return Inertia::render('Public/Services', [
            'salon' => $this->salon($request),
        ]);
    }

    public function schedule(Request $request): Response
    {
        return Inertia::render('Public/Schedule', [
            'salon' => $this->salon($request),
        ]);
    }

    public function account(Request $request): Response
    {
        abort_unless($request->user()->role === UserRole::CLIENT, 403);

        return Inertia::render('Public/Account', [
            'salon' => $this->salon($request),
            'customer' => $request->user()->only(['name', 'email', 'phone']),
        ]);
    }

    public function confirmation(Request $request, string $appointment): Response
    {
        abort_unless($request->user()->role === UserRole::CLIENT, 403);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $appointment = $tenant->appointments()
            ->with('services')
            ->where('client_id', $request->user()->id)
            ->findOrFail($appointment);

        return Inertia::render('Public/Confirmation', [
            'salon' => $this->salon($request),
            'appointment' => $this->appointment($appointment),
        ]);
    }

    /**
     * @return array{id: string, name: string, timezone: string}
     */
    private function salon(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $settings = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id]);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'timezone' => $settings->timezone,
        ];
    }

    /**
     * @return array{id: string, status: string, startsAt: string, endsAt: string, duration: int, total: string, services: array<int, array{name: string, duration: int, price: string}>}
     */
    private function appointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'status' => $appointment->status->value,
            'startsAt' => $appointment->starts_at->toISOString(),
            'endsAt' => $appointment->ends_at->toISOString(),
            'duration' => $appointment->duration_minutes,
            'total' => $appointment->total,
            'services' => $appointment->services->map(fn ($service): array => [
                'name' => $service->name,
                'duration' => $service->duration_minutes,
                'price' => $service->price,
            ])->all(),
        ];
    }
}
