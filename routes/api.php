<?php

use App\Http\Controllers\SalonServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('api.admin.')
    ->middleware(['auth:sanctum', 'tenant.resolve'])
    ->group(function (): void {
        Route::apiResource('services', SalonServiceController::class)
            ->parameters(['services' => 'salonService']);
    });
