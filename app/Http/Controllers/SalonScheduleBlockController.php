<?php

namespace App\Http\Controllers;

use App\Actions\ParseSalonLocalDateTime;
use App\Enums\UserRole;
use App\Http\Requests\StoreSalonScheduleBlockRequest;
use App\Models\SalonScheduleBlock;
use App\Models\SalonSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SalonScheduleBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $blocks = $this->tenant($request)->scheduleBlocks()->orderBy('starts_at')->get();

        return response()->json(['data' => $blocks->map(fn (SalonScheduleBlock $block): array => $this->payload($block))]);
    }

    public function store(StoreSalonScheduleBlockRequest $request, ParseSalonLocalDateTime $parser): JsonResponse
    {
        $tenant = $this->tenant($request);
        $timezone = SalonSetting::query()->firstOrCreate(['tenant_id' => $tenant->id])->timezone;
        $startsAt = $parser->handle($request->validated('startsAtLocal'), $timezone, 'startsAtLocal');
        $endsAt = $parser->handle($request->validated('endsAtLocal'), $timezone, 'endsAtLocal');

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['endsAtLocal' => 'La fecha de fin debe ser posterior a la fecha de inicio.']);
        }

        $block = $tenant->scheduleBlocks()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $request->validated('reason'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($block)], 201);
    }

    public function destroy(Request $request, string $block): Response
    {
        $this->authorizeAdmin($request);
        $this->tenant($request)->scheduleBlocks()->findOrFail($block)->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function payload(SalonScheduleBlock $block): array
    {
        $timezone = SalonSetting::query()->firstOrCreate(['tenant_id' => $block->tenant_id])->timezone;

        return [
            'id' => $block->id,
            'startsAt' => $block->starts_at->toISOString(),
            'endsAt' => $block->ends_at->toISOString(),
            'startsAtLocal' => $block->starts_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'endsAtLocal' => $block->ends_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'reason' => $block->reason,
            'createdBy' => $block->created_by,
            'updatedBy' => $block->updated_by,
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
