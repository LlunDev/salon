<?php

namespace App\Http\Controllers;

use App\Actions\CreateAppointment;
use App\Actions\ParseSalonLocalDateTime;
use App\Enums\UserRole;
use App\Http\Requests\StoreManualAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\SalonScheduleBlock;
use App\Models\SalonSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAgendaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $tenant = $this->tenant($request);
        $timezone = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id])->timezone;
        $localStart = CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], $timezone);
        $startsAt = $localStart->utc();
        $endsAt = $localStart->addDay()->utc();

        $appointments = $tenant->appointments()
            ->with(['services', 'client'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->get();
        $blocks = $tenant->scheduleBlocks()
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->get();
        $services = $tenant->salonServices()->where('available', true)->orderBy('name')->get();

        return response()->json(['data' => [
            'date' => $validated['date'],
            'timezone' => $timezone,
            'appointments' => AppointmentResource::collection($appointments)->resolve($request),
            'blocks' => $blocks->map(fn (SalonScheduleBlock $block): array => $this->blockPayload($block, $timezone)),
            'services' => $services->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration' => $service->duration_minutes,
                'price' => $service->price,
            ]),
        ]]);
    }

    public function clients(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $search = trim((string) $request->query('search', ''));
        $clients = $this->tenant($request)->users()
            ->where('role', UserRole::CLIENT)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['data' => $clients]);
    }

    public function store(StoreManualAppointmentRequest $request, ParseSalonLocalDateTime $parser, CreateAppointment $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        $client = $tenant->users()
            ->where('role', UserRole::CLIENT)
            ->find($request->validated('clientId'));

        if (! $client) {
            throw ValidationException::withMessages(['clientId' => 'Selecciona un cliente válido del salón.']);
        }

        $timezone = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id])->timezone;
        $appointment = $action->handle(
            $tenant,
            $client,
            (string) Str::uuid(),
            $parser->handle($request->validated('startsAtLocal'), $timezone, 'startsAtLocal'),
            $request->validated('serviceIds'),
            $request->user(),
        )->load('client');

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    private function blockPayload(SalonScheduleBlock $block, string $timezone): array
    {
        return [
            'id' => $block->id,
            'startsAt' => $block->starts_at->toISOString(),
            'endsAt' => $block->ends_at->toISOString(),
            'startsAtLocal' => $block->starts_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'endsAtLocal' => $block->ends_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'reason' => $block->reason,
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, [UserRole::OWNER, UserRole::STAFF], true), 403);
    }

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }
}
