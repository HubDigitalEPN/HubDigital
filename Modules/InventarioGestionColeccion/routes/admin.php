<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\AlertaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\CajaIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\Dashboard;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\GabineteIndex;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin\GabineteShow;

Route::middleware(['web', 'admin.auth'])
    ->prefix('admin/inventario')
    ->name('admin.inventario.')
    ->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/gabinetes', GabineteIndex::class)->name('gabinetes');
        Route::get('/gabinetes/{id}', GabineteShow::class)->name('gabinetes.show');
        Route::get('/cajas', CajaIndex::class)->name('cajas');
        Route::get('/alertas', AlertaIndex::class)->name('alertas');
    });
