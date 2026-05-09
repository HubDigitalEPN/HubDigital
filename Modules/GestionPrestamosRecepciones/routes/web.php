<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\PanelPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitudForm;

Route::middleware(['auth', 'verified'])
    ->prefix('prestamos')
    ->name('prestamos.')
    ->group(function () {
        // Investigador — solo usuarios con rol PRESTAMISTA
        Route::middleware('role:prestamista')->group(function () {
            Route::get('/mis-solicitudes', MisSolicitudes::class)->name('investigador.mis-solicitudes');
            Route::get('/solicitud/nueva', SolicitudForm::class)->name('investigador.solicitud.crear');
            Route::get('/solicitud/{id}/editar', SolicitudForm::class)->name('investigador.solicitud.editar');
            Route::get('/solicitud/{id}', DetalleSolicitud::class)->name('investigador.solicitud.detalle');
        });

        // Curador — solo usuarios con rol CURADOR
        Route::middleware('role:curador')->group(function () {
            Route::get('/curador/panel', PanelPrestamos::class)->name('curador.panel');
            Route::get('/curador/solicitudes', BandejaSolicitudes::class)->name('curador.solicitudes');
            Route::get('/curador/solicitud/{id}', RevisarSolicitud::class)->name('curador.solicitud.revisar');
            Route::get('/curador/actas', BandejaActas::class)->name('curador.actas');
            Route::get('/curador/acta/{id}/validar', ValidarActa::class)->name('curador.acta.validar');
        });
    });
