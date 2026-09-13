<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\SalonServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('api.admin.')
    ->middleware(['auth:sanctum', 'tenant.resolve'])
    ->group(function (): void {
        Route::apiResource('services', SalonServiceController::class)
            ->parameters(['services' => 'salonService']);
    });

Route::middleware(['auth:sanctum', 'tenant.resolve'])
    ->name('api.')
    ->group(function (): void {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    });
