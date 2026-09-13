<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalonServiceRequest;
use App\Http\Requests\UpdateSalonServiceRequest;
use App\Http\Resources\SalonServiceResource;
use App\Models\SalonService;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SalonServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim($validated['search'] ?? '');

        return SalonServiceResource::collection(
            $this->tenant($request)
                ->salonServices()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('description', 'ilike', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        );
    }

    public function store(StoreSalonServiceRequest $request): JsonResponse
    {
        $service = $this->tenant($request)->salonServices()->create([
            ...$this->attributes($request->validated()),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return (new SalonServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $salonService): SalonServiceResource
    {
        return new SalonServiceResource($this->service($request, $salonService));
    }

    public function update(UpdateSalonServiceRequest $request, string $salonService): SalonServiceResource
    {
        $service = $this->service($request, $salonService);
        $service->forceFill([
            ...$this->attributes($request->validated()),
            'updated_by' => $request->user()->id,
        ])->save();

        return new SalonServiceResource($service);
    }

    public function destroy(Request $request, string $salonService): Response
    {
        $this->service($request, $salonService)->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $attributes = $validated;

        if (array_key_exists('duration', $attributes)) {
            $attributes['duration_minutes'] = $attributes['duration'];
            unset($attributes['duration']);
        }

        if (array_key_exists('imageUrl', $attributes)) {
            $attributes['image_url'] = $attributes['imageUrl'];
            unset($attributes['imageUrl']);
        }

        return $attributes;
    }

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }

    private function service(Request $request, string $id): SalonService
    {
        return $this->tenant($request)->salonServices()->findOrFail($id);
    }
}
