<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\SeguimientoFisicoController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {

    // --- Seguimiento físico (eventos IoT) — requiere token con habilidad 'esp32' ---
    Route::middleware('ability:esp32')
        ->prefix('seguimiento-fisico')
        ->name('api.v1.seguimiento-fisico.')
        ->group(function (): void {
            // ESP32: evento con tag_uid + gabinete_id + slot_index (backend resuelve IDs)
            Route::post('eventos', [SeguimientoFisicoController::class, 'procesarEvento'])
                ->name('eventos');
        });
});
