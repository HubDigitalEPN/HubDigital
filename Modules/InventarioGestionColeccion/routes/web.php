<?php

declare(strict_types=1);

use App\Http\Controllers\RedirigirTablaCatalogo;
use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionColumnasIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DatasetConfigForm;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DescargarPlantillaImportController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EtiquetadoQrIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ExportarDwcController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ActualizarCatalogoIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ResolverQrController;

Route::middleware(['web', 'auth', 'verified', 'role:curador'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {
        Route::prefix('taxonomia')->name('taxonomia.')->group(function () {

            Route::get('/taxones', RedirigirTablaCatalogo::class)->defaults('tablaCatalogo', 'taxones')->name('taxones');

            Route::get('/localidades', RedirigirTablaCatalogo::class)->defaults('tablaCatalogo', 'localidades-coleccion')->name('localidades');

            Route::get('/especimenes', EspecimenIndex::class)->withoutMiddleware('role:curador')->name('especimenes');
            Route::get('/importar', ActualizarCatalogoIndex::class)->withoutMiddleware('role:curador')->name('importar');
            Route::get('/importar/plantilla', DescargarPlantillaImportController::class)
                ->withoutMiddleware('role:curador')->name('importar.plantilla');
            Route::get('/importar/plantilla/configurar', RedirigirTablaCatalogo::class)
                ->defaults('tablaCatalogo', 'plantilla-catalogo')->defaults('destinoCatalogo', 'admin.matrices')->name('importar.plantilla.configurar');
            Route::get('/importar/{importacion}/original', \Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DescargarOriginalImportacionController::class)->whereUuid('importacion')->name('importar.original');
            Route::get('/etiquetas', EtiquetadoQrIndex::class)->name('etiquetas');

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
