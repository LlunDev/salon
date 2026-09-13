<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateSalonScheduleRequest;
use App\Models\SalonSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalonScheduleController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => $this->payload($this->settings($this->tenant($request)))]);
    }

    public function update(UpdateSalonScheduleRequest $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $request->validated();

        $settings = DB::transaction(function () use ($tenant, $validated): SalonSetting {
            $settings = $this->settings($tenant);
            $settings->update([
                'timezone' => $validated['timezone'],
                'slot_interval_minutes' => $validated['slotIntervalMinutes'],
                'appointment_capacity' => $validated['appointmentCapacity'],
                'cancellation_notice_hours' => $validated['cancellationNoticeHours'],
            ]);

            $tenant->weeklyHours()->delete();
            $tenant->weeklyHours()->createMany(collect($validated['weeklyHours'])->map(fn (array $hours): array => [
                'weekday' => $hours['weekday'],
                'closed' => $hours['closed'],
                'opens_at' => $hours['closed'] ? null : $hours['opensAt'],
                'closes_at' => $hours['closed'] ? null : $hours['closesAt'],
            ])->all());

            return $settings->fresh('weeklyHours');
        });

        return response()->json(['data' => $this->payload($settings)]);
    }

    private function settings(Tenant $tenant): SalonSetting
    {
        return SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id])->load('weeklyHours');
    }

    /** @return array<string, mixed> */
    private function payload(SalonSetting $settings): array
    {
        return [
            'timezone' => $settings->timezone,
            'slotIntervalMinutes' => $settings->slot_interval_minutes,
            'appointmentCapacity' => $settings->appointment_capacity,
            'cancellationNoticeHours' => $settings->cancellation_notice_hours,
            'weeklyHours' => $settings->weeklyHours->sortBy('weekday')->values()->map(fn ($hours): array => [
                'weekday' => $hours->weekday,
                'closed' => $hours->closed,
                'opensAt' => $hours->closed ? null : substr($hours->opens_at, 0, 5),
                'closesAt' => $hours->closed ? null : substr($hours->closes_at, 0, 5),
            ])->all(),
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
