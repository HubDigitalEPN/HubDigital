<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\CentroRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionColumnasIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DatasetConfigForm;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DescargarPlantillaImportController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DuplicadosCatalogNumberIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EntidadDepositanteIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EtiquetadoQrIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ExportarDwcController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\FechasRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ImportarCatalogoIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadesRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\MuestrasColectaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ResolverQrController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxaRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;

Route::middleware(['web', 'auth', 'verified', 'role:curador'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {
        Route::prefix('taxonomia')->name('taxonomia.')->group(function () {
            Route::get('/revision', CentroRevisionIndex::class)->name('revision');
            Route::get('/taxones', TaxonIndex::class)->name('taxones');
            Route::get('/taxones/revision', TaxaRevisionIndex::class)->name('taxones.revision');
            Route::get('/localidades', LocalidadIndex::class)->name('localidades');
            Route::get('/localidades/revision', LocalidadesRevisionIndex::class)->name('localidades.revision');
            Route::get('/especimenes', EspecimenIndex::class)->name('especimenes');
            Route::get('/importar', ImportarCatalogoIndex::class)->name('importar');
            Route::get('/importar/plantilla', DescargarPlantillaImportController::class)->name('importar.plantilla');
            Route::get('/etiquetas', EtiquetadoQrIndex::class)->name('etiquetas');
            Route::get('/especimenes/duplicados', DuplicadosCatalogNumberIndex::class)->name('especimenes.duplicados');
            Route::get('/muestras', MuestrasColectaIndex::class)->name('muestras');
            Route::get('/fechas/revision', FechasRevisionIndex::class)->name('fechas.revision');
            Route::get('/entidades-depositantes', EntidadDepositanteIndex::class)->name('entidades-depositantes');
            Route::get('/dataset-config', DatasetConfigForm::class)->name('dataset.config');
            Route::get('/dwc/descargar', ExportarDwcController::class)->name('dwc.descargar');
            Route::get('/columnas-config', ConfiguracionColumnasIndex::class)->name('columnas.config');
        });
    });

// Resolución del QR físico de un espécimen a su ficha digital. El `payload` es un
// token opaco e inadivinable impreso en la etiqueta, así que funciona como capacidad
// de acceso: el investigador en campo escanea y ve la ficha sin autenticarse.
Route::middleware(['web'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {
        Route::get('/qr/{payload}', ResolverQrController::class)->name('qr.resolver');
    });
