<?php

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ActaEntregaController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DescargarActaEntregaController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EntidadDepositanteController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {

    // --- Gestión de registros taxonómicos ---
    Route::prefix('taxonomia')
        ->name('api.v1.taxonomia.')
        ->middleware(['ability:curaduria:gestionar', 'role:CURADOR'])
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
            Route::get('actas/{objeto}', DescargarActaEntregaController::class)
                ->where('objeto', '[A-Za-z0-9_-]+')
                ->name('entidades-depositantes.actas.descargar');
        });
});
