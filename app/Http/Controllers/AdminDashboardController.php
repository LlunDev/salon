<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Admin/Dashboard', [
            'salon' => [
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
            ],
            'stats' => [
                [
                    'label' => 'Citas de hoy',
                    'value' => '28',
                    'detail' => '+12% frente a ayer',
                ],
                [
                    'label' => 'Servicios reservados',
                    'value' => '16',
                    'detail' => 'Color, corte y spa capilar',
                ],
                [
                    'label' => 'Clientes nuevos',
                    'value' => '5',
                    'detail' => 'Captados desde Instagram y referidos',
                ],
            ],
            'agenda' => [
                [
                    'time' => '09:00',
                    'client' => 'Valeria Gomez',
                    'service' => 'Balayage premium',
                    'stylist' => 'Marina',
                ],
                [
                    'time' => '11:30',
                    'client' => 'Claudia Ruiz',
                    'service' => 'Corte + brushing',
                    'stylist' => 'Andrea',
                ],
                [
                    'time' => '16:00',
                    'client' => 'Lucia Perez',
                    'service' => 'Manicura spa',
                    'stylist' => 'Sofia',
                ],
            ],
        ]);
    }
}
