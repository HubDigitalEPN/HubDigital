<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\CajaController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GabineteController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\RanuraGabineteController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\SeguimientoFisicoController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {

    // --- Configuración del inventario físico (CRUD de setup) ---
    Route::get('gabinetes', [GabineteController::class, 'index'])->name('api.v1.gabinetes.index');
    Route::post('gabinetes', [GabineteController::class, 'store'])->name('api.v1.gabinetes.store');

    Route::get('gabinetes/{gabinete_id}/ranuras', [RanuraGabineteController::class, 'index'])->name('api.v1.gabinetes.ranuras.index');
    Route::post('gabinetes/{gabinete_id}/ranuras', [RanuraGabineteController::class, 'store'])->name('api.v1.gabinetes.ranuras.store');

    Route::get('cajas', [CajaController::class, 'index'])->name('api.v1.cajas.index');
    Route::post('cajas', [CajaController::class, 'store'])->name('api.v1.cajas.store');

    // --- Seguimiento físico (eventos IoT) ---
    Route::prefix('seguimiento-fisico')->name('api.v1.seguimiento-fisico.')->group(function (): void {
        // ESP32: evento con tag_uid + gabinete_id + slot_index (backend resuelve IDs)
        Route::post('eventos', [SeguimientoFisicoController::class, 'procesarEvento'])
            ->name('eventos');

        // Acceso directo con IDs ya conocidos (testing / integración manual)
        Route::post('cajas/{caja_id}/ingresos', [SeguimientoFisicoController::class, 'registrarIngreso'])
            ->name('cajas.ingresos');
        Route::post('cajas/{caja_id}/retiros', [SeguimientoFisicoController::class, 'registrarRetiro'])
            ->name('cajas.retiros');
    });
});
