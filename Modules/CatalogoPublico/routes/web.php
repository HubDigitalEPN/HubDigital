<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;

Route::middleware(['auth', 'verified'])
    ->prefix('admin/divulgacion')
    ->name('admin.divulgacion.')
    ->group(function () {
        Route::get('/', TablaEspecimenesDivulgados::class)->name('index');
        Route::get('/sincronizar', SincronizarEspecimenes::class)->name('sincronizar');
    });
