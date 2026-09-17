<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredCustomerController extends Controller
{
    public function create(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Auth/Register', [
            'salon' => $tenant->only(['id', 'name']),
        ]);
    }

    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $validated = $request->validated();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::CLIENT,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('booking.account', absolute: false));
    }
}
