<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        $tenant = Tenant::query()
            ->where('domain', $host)
            ->first();

        abort_if(! $tenant, 404);

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
