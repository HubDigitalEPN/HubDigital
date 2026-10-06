<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AnalisisDiversidadCurador;
use Modules\CatalogoPublico\Presentation\Http\Controllers\GestionImagenesTaxonomicas;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ServirImagenCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;

Route::middleware(['auth', 'verified', 'role:curador'])
    ->prefix('divulgacion')
    ->name('divulgacion.')
    ->group(function () {
        Route::get('/', TablaEspecimenesDivulgados::class)->name('index');
        Route::get('/sincronizar', SincronizarEspecimenes::class)->name('sincronizar');
        Route::get('/imagenes', GestionImagenesTaxonomicas::class)->name('imagenes');
        Route::get('/asistente', AdministrarAsistente::class)->middleware('role:curador')->name('asistente');
        Route::get('/analisis-diversidad', AnalisisDiversidadCurador::class)->middleware('role:curador')->name('analisis-diversidad');
    });

Route::prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('portal.catalogo', ['vista' => 'mapa']))->name('inicio');
        Route::get('/estadisticas', PortalEstadisticas::class)->name('estadisticas');
        Route::get('/catalogo', PortalCatalogo::class)->lazy('on-load')->name('catalogo');
        Route::view('/diccionario-exportacion', 'catalogopublico::diccionario-exportacion')->name('diccionario-exportacion');
        Route::get('/lista-especies.csv', [PortalEstadisticas::class, 'descargarLista'])->name('lista-especies');
        Route::get('/imagenes/{objeto}', ServirImagenCatalogo::class)
            ->where('objeto', '[A-Za-z0-9_-]+')
            ->name('imagen');
    });
