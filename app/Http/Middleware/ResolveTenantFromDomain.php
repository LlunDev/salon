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
            $tenant = Tenant::query()
                ->when(
                    $request->session()->get('provisioned_tenant_id'),
                    fn ($query, $tenantId) => $query->whereKey($tenantId),
                    fn ($query) => $query->latest('id'),
                )
                ->first();

            abort_if(! $tenant, 404);

            $request->attributes->set('tenant', $tenant);

            return $next($request);
        }

        $tenant = Tenant::query()
            ->where('domain', $host)
            ->first();

        abort_if(! $tenant, 404);

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
