<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSectionController extends Controller
{
    public function services(Request $request): Response
    {
        return $this->renderSection($request, 'Admin/Services', 'Servicios');
    }

    public function reminders(Request $request): Response
    {
        return $this->renderSection($request, 'Admin/Reminders', 'Recordatorios');
    }

    public function users(Request $request): Response
    {
        return $this->renderSection($request, 'Admin/Users', 'Usuarios');
    }

    public function clients(Request $request): Response
    {
        return $this->renderSection($request, 'Admin/Clients', 'Clientes');
    }

    public function settings(Request $request): Response
    {
        return $this->renderSection($request, 'Admin/Settings', 'Configuracion');
    }

    private function renderSection(Request $request, string $component, string $title): Response
    {
        $tenant = $request->attributes->get('tenant');

        return Inertia::render($component, [
            'salon' => [
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
            ],
            'pageTitle' => $title,
        ]);
    }
}
