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

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $sessionTenantId = $request->hasSession() ? $request->session()->get('provisioned_tenant_id') : null;
            $tenant = Tenant::query()
                ->whereKey($request->user()?->tenant_id ?? $sessionTenantId)
                ->first();

            abort_if(! $tenant, 404);

            $request->attributes->set('tenant', $tenant);

            return $next($request);
        }

        $tenant = Tenant::query()
            ->where('domain', $host)
            ->first();

        abort_if(! $tenant, 404);
        abort_if($request->user() && $request->user()->tenant_id !== $tenant->id, 403);

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
