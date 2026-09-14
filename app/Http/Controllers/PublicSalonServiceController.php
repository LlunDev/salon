<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSalonServiceResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicSalonServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return PublicSalonServiceResource::collection(
            $tenant->salonServices()
                ->where('available', true)
                ->orderBy('name')
                ->get(),
        );
    }
}
