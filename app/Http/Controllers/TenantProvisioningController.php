<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TenantProvisioningController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Demo', [
            'baseDomain' => $this->baseDomain(),
        ]);
    }

    public function domainAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $domain = $this->fullDomain($validated['subdomain']);
        $available = ! Tenant::query()->where('domain', $domain)->exists();

        return response()->json([
            'available' => $available,
            'full_domain' => $domain,
            'message' => $available
                ? 'Este dominio esta disponible.'
                : 'Este dominio ya esta en uso.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'salon_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $domain = $this->fullDomain((string) $value);

                    if (Tenant::query()->where('domain', $domain)->exists()) {
                        $fail('Este dominio ya esta en uso.');
                    }
                },
            ],
            'owner_first_name' => ['required', 'string', 'max:120'],
            'owner_last_name' => ['required', 'string', 'max:120'],
            'owner_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ownerExists = User::query()
                        ->where('email', $value)
                        ->where('role', UserRole::OWNER->value)
                        ->exists();

                    if ($ownerExists) {
                        $fail('Ya existe una cuenta administradora con este correo.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => 'Debes aceptar los terminos para continuar.',
        ]);

        $tenant = DB::transaction(function () use ($validated): Tenant {
            $tenant = Tenant::create([
                'name' => $validated['salon_name'],
                'domain' => $this->fullDomain($validated['subdomain']),
                'status' => 'active',
            ]);

            $tenant->forceFill([
                'tenant_id' => $tenant->id,
            ])->save();

            User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $validated['owner_first_name'],
                'last_name' => $validated['owner_last_name'],
                'name' => trim($validated['owner_first_name'].' '.$validated['owner_last_name']),
                'email' => $validated['owner_email'],
                'password' => $validated['password'],
                'role' => UserRole::OWNER,
            ]);

            return $tenant;
        });

        return redirect()
            ->away($this->tenantAdminUrl($tenant->domain, $request))
            ->with('success', 'Tu espacio de trabajo para el salon ya esta listo.');
    }

    private function baseDomain(): string
    {
        return strtolower(trim((string) config('provisioning.base_domain'), '.'));
    }

    private function fullDomain(string $subdomain): string
    {
        return strtolower(trim($subdomain)).'.'.$this->baseDomain();
    }

    private function tenantAdminUrl(string $domain, Request $request): string
    {
        $port = $request->getPort();
        $portSegment = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return $request->getScheme().'://'.$domain.$portSegment.'/admin/dashboard';
    }
}
