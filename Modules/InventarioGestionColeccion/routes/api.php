<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ActaEntregaController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EntidadDepositanteController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\SeguimientoFisicoController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {

    // --- Seguimiento físico (eventos IoT) — requiere token con habilidad 'esp32' ---
    Route::middleware('ability:esp32')
        ->prefix('seguimiento-fisico')
        ->name('api.v1.seguimiento-fisico.')
        ->group(function (): void {
            Route::post('eventos', [SeguimientoFisicoController::class, 'procesarEvento'])
                ->name('eventos');
        });

    // --- Gestión de registros taxonómicos ---
    // 'ability:taxonomia' impide que tokens de dispositivo (solo 'esp32') escriban taxonomía;
    // los tokens de usuario emitidos en login tienen ['*'] y las sesiones web usan TransientToken.
    Route::middleware('ability:taxonomia')
        ->prefix('taxonomia')
        ->name('api.v1.taxonomia.')
        ->group(function (): void {
            // Taxones
            Route::post('taxones', [TaxonController::class, 'registrar'])
                ->name('taxones.registrar');
            Route::put('taxones/{id}', [TaxonController::class, 'actualizar'])
                ->name('taxones.actualizar');

            // Especímenes
            Route::post('especimenes', [EspecimenController::class, 'registrar'])
                ->name('especimenes.registrar');
            Route::get('especimenes', [EspecimenController::class, 'buscar'])
                ->name('especimenes.buscar');

            // Entidades depositantes
            Route::post('entidades-depositantes', [EntidadDepositanteController::class, 'registrar'])
                ->name('entidades-depositantes.registrar');
            Route::put('entidades-depositantes/{id}', [EntidadDepositanteController::class, 'actualizar'])
                ->name('entidades-depositantes.actualizar');

            // Acta de entrega
            Route::post('entidades-depositantes/{entidadId}/actas', [ActaEntregaController::class, 'generar'])
                ->name('entidades-depositantes.actas.generar');
        });
});
