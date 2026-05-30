<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\AuditarPrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaPrestamos as CuradorBandejaPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ConfiguracionRecordatorios;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\DetallePrestamo as CuradorDetallePrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\PanelPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\BandejaActas as InvestigadorBandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\BandejaPrestamos as InvestigadorBandejaPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetallePrestamo as InvestigadorDetallePrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitudForm;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirDocumentoIdentidad;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirPdfActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirPdfFirmado;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\VerActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\VerActaEmbed;

Route::middleware(['auth', 'verified'])
    ->prefix('prestamos')
    ->name('prestamos.')
    ->group(function () {

        // Compartido — curador e investigador (la autorización la aplica el componente)
        Route::get('/acta/{id}/ver', VerActa::class)->name('acta.ver');
        Route::get('/acta/{id}/embed', VerActaEmbed::class)->name('acta.embed');
        Route::get('/acta/{id}/pdf-original', ServirPdfActa::class)->name('acta.pdf-original');
        Route::get('/acta/{id}/pdf-firmado', ServirPdfFirmado::class)->name('acta.pdf-firmado');
        Route::get('/acta/{id}/documento-identidad', ServirDocumentoIdentidad::class)->name('acta.documento-identidad');

        // Investigador — solo usuarios con rol PRESTAMISTA
        Route::middleware('role:prestamista')->group(function () {
            Route::get('/mis-solicitudes', MisSolicitudes::class)->name('investigador.mis-solicitudes');
            Route::get('/mis-actas', InvestigadorBandejaActas::class)->name('investigador.mis-actas');
            Route::get('/mis-prestamos', InvestigadorBandejaPrestamos::class)->name('investigador.mis-prestamos');
            Route::get('/solicitud/nueva', SolicitudForm::class)->name('investigador.solicitud.crear');
            Route::get('/solicitud/{id}/editar', SolicitudForm::class)->name('investigador.solicitud.editar');
            Route::get('/solicitud/{id}', DetalleSolicitud::class)->name('investigador.solicitud.detalle');
            Route::get('/acta/{id}', DetalleActa::class)->name('investigador.acta.detalle');
            Route::get('/prestamo/{id}', InvestigadorDetallePrestamo::class)->name('investigador.prestamo.detalle');
        });

        // Depositante — solicitudes de depósito
        Route::middleware('role:depositante')->group(function () {
            Route::get('/mis-depositos', MisDepositos::class)->name('investigador.mis-depositos');
            Route::get('/deposito/nueva', RegistroSolicitudDeposito::class)->name('investigador.deposito.crear');
            Route::get('/deposito/{id}', DetalleDeposito::class)->name('investigador.deposito.detalle');
        });

        // Curador — solo usuarios con rol CURADOR
        Route::middleware('role:curador')->group(function () {
            Route::get('/curador/panel', PanelPrestamos::class)->name('curador.panel');
            Route::get('/curador/solicitudes', BandejaSolicitudes::class)->name('curador.solicitudes');
            Route::get('/curador/solicitud/{id}', RevisarSolicitud::class)->name('curador.solicitud.revisar');
            Route::get('/curador/actas', BandejaActas::class)->name('curador.actas');
            Route::get('/curador/acta/{id}/validar', ValidarActa::class)->name('curador.acta.validar');
            Route::get('/curador/prestamos', CuradorBandejaPrestamos::class)->name('curador.prestamos');
            Route::get('/curador/prestamo/{id}', CuradorDetallePrestamo::class)->name('curador.prestamo.detalle');
            Route::get('/curador/prestamo/{id}/auditar', AuditarPrestamo::class)->name('curador.prestamo.auditar');
            Route::get('/curador/configuracion', ConfiguracionRecordatorios::class)->name('curador.configuracion');
        });
    });
