<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PublicAppointmentAvailabilityController;
use App\Http\Controllers\PublicSalonServiceController;
use App\Http\Controllers\SalonScheduleBlockController;
use App\Http\Controllers\SalonScheduleController;
use App\Http\Controllers\SalonServiceController;
use Illuminate\Support\Facades\Route;

Route::get('public/services', [PublicSalonServiceController::class, 'index'])
    ->middleware('tenant.resolve')
    ->name('api.public.services.index');
Route::get('public/availability', PublicAppointmentAvailabilityController::class)
    ->middleware('tenant.resolve')
    ->name('api.public.availability');

Route::prefix('admin')
    ->name('api.admin.')
    ->middleware(['auth:sanctum', 'tenant.resolve'])
    ->group(function (): void {
        Route::apiResource('services', SalonServiceController::class)
            ->parameters(['services' => 'salonService']);
        Route::get('schedule', [SalonScheduleController::class, 'show'])->name('schedule.show');
        Route::put('schedule', [SalonScheduleController::class, 'update'])->name('schedule.update');
        Route::get('schedule/blocks', [SalonScheduleBlockController::class, 'index'])->name('schedule.blocks.index');
        Route::post('schedule/blocks', [SalonScheduleBlockController::class, 'store'])->name('schedule.blocks.store');
        Route::delete('schedule/blocks/{block}', [SalonScheduleBlockController::class, 'destroy'])->name('schedule.blocks.destroy');
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
