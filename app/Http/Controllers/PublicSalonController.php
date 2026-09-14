<?php

namespace App\Http\Controllers;

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
}
