<?php

use Illuminate\Support\Facades\Route;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AnalisisDiversidadCurador;
use Modules\CatalogoPublico\Presentation\Http\Controllers\GestionImagenesTaxonomicas;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ServirImagenCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ServirReferenciaFotografica;
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
        Route::get('/explorador', \Modules\CatalogoPublico\Presentation\Http\Controllers\ExploradorPublico::class)
            ->middleware('throttle:180,1')->name('explorador');
        Route::get('/explorador/ficha/{taxon}', \Modules\CatalogoPublico\Presentation\Http\Controllers\FichaTaxonomicaPublica::class)
            ->whereUuid('taxon')->middleware('throttle:180,1')->name('ficha');
        Route::get('/explorador/ficha/{taxon}/foto', \Modules\CatalogoPublico\Presentation\Http\Controllers\FotoFichaTaxonomica::class)
            ->whereUuid('taxon')->middleware('throttle:180,1')->name('ficha-foto');
        Route::get('/explorador/dibujo', \Modules\CatalogoPublico\Presentation\Http\Controllers\DibujoTaxonomicoPublico::class)
            ->middleware('throttle:180,1')->name('explorador-dibujo');
        Route::get('/filos', \Modules\CatalogoPublico\Presentation\Http\Controllers\ArbolFiloPublico::class)
            ->middleware('throttle:180,1')->name('filos');
        // Arranque normal de una página pública: obtiene la sesión actual antes de Livewire.
        // Este script nunca se cachea ni modifica peticiones enviadas por el visitante.
        Route::get('/pwa/arranque.js', function (\Illuminate\Http\Request $request) {
            abort_if($request->header('Sec-Fetch-Site') === 'cross-site', 403);
            return response('document.querySelector(\'meta[name="csrf-token"]\')?.setAttribute("content", '.json_encode(csrf_token()).');', 200, [
                'Content-Type' => 'application/javascript', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('pwa-arranque');
        Route::get('/sugerencias', \Modules\CatalogoPublico\Presentation\Http\Controllers\SugerenciasBusquedaPublica::class)
            ->middleware('throttle:120,1')->name('sugerencias');
        Route::view('/diccionario-exportacion', 'catalogopublico::diccionario-exportacion')->name('diccionario-exportacion');
        Route::get('/lista-especies.csv', [PortalEstadisticas::class, 'descargarLista'])->name('lista-especies');
        Route::get('/imagenes/{objeto}', ServirImagenCatalogo::class)
            ->where('objeto', '[A-Za-z0-9_-]+')
            ->name('imagen');
        Route::get('/referencias-fotograficas/{foto}/{extension}.webp', ServirReferenciaFotografica::class)
            ->where('foto', '[1-9][0-9]{0,11}')->where('extension', 'jpg|jpeg|png')
            ->middleware('throttle:120,1')->name('referencia-fotografica');
    });
