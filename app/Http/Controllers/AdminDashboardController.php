<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(in_array($request->user()?->role, [UserRole::OWNER, UserRole::STAFF], true), 403);
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Admin/Dashboard', [
            'salon' => [
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
            ],
        ]);
    }
}
