<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ArbolTaxonomicoDivulgacion;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;

Route::middleware(['auth', 'verified'])
    ->prefix('divulgacion')
    ->name('divulgacion.')
    ->group(function () {
        Route::get('/', TablaEspecimenesDivulgados::class)->name('index');
        Route::get('/sincronizar', SincronizarEspecimenes::class)->name('sincronizar');
    });

Route::prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', ArbolTaxonomicoDivulgacion::class)->name('catalogo');
    });
