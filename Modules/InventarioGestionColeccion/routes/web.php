<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\AlertaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\AsignacionUnitTrayIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\CajaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\Dashboard;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\GabineteIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\GabineteShow;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\HorarioSettingsForm;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\OrdenFamiliasIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\CentroRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ConfiguracionColumnasIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DatasetConfigForm;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\DuplicadosCatalogNumberIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EntidadDepositanteIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\EspecimenIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ExportarDwcController;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\FechasRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadesRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\LocalidadIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\MuestrasColectaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxaRevisionIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\TaxonIndex;

Route::middleware(['web', 'auth', 'verified', 'role:curador'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/gabinetes', GabineteIndex::class)->name('gabinetes');
        Route::get('/gabinetes/{id}', GabineteShow::class)->name('gabinetes.show');
        Route::get('/cajas', CajaIndex::class)->name('cajas');
        Route::get('/unit-trays', AsignacionUnitTrayIndex::class)->name('unit-trays');
        Route::get('/alertas', AlertaIndex::class)->name('alertas');
        Route::get('/orden-familias', OrdenFamiliasIndex::class)->name('orden-familias');
        Route::get('/horario', HorarioSettingsForm::class)->name('horario');

        Route::prefix('taxonomia')->name('taxonomia.')->group(function () {
            Route::get('/revision', CentroRevisionIndex::class)->name('revision');
            Route::get('/taxones', TaxonIndex::class)->name('taxones');
            Route::get('/taxones/revision', TaxaRevisionIndex::class)->name('taxones.revision');
            Route::get('/localidades', LocalidadIndex::class)->name('localidades');
            Route::get('/localidades/revision', LocalidadesRevisionIndex::class)->name('localidades.revision');
            Route::get('/especimenes', EspecimenIndex::class)->name('especimenes');
            Route::get('/especimenes/duplicados', DuplicadosCatalogNumberIndex::class)->name('especimenes.duplicados');
            Route::get('/muestras', MuestrasColectaIndex::class)->name('muestras');
            Route::get('/fechas/revision', FechasRevisionIndex::class)->name('fechas.revision');
            Route::get('/entidades-depositantes', EntidadDepositanteIndex::class)->name('entidades-depositantes');
            Route::get('/dataset-config', DatasetConfigForm::class)->name('dataset.config');
            Route::get('/dwc/descargar', ExportarDwcController::class)->name('dwc.descargar');
            Route::get('/columnas-config', ConfiguracionColumnasIndex::class)->name('columnas.config');
        });
    });
