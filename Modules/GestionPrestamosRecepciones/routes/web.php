<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\AprobarVerificacion;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\AuditarPrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaPrestamos as CuradorBandejaPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\CerrarPrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ConfiguracionRecordatorios;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\DetallePrestamo as CuradorDetallePrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\GestionarProrroga;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\PanelPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RecepcionFisicaLote;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RegistrarCertificado;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\DescargarActaPdf;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\DescargarActaRecepcion;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ImprimirQrDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\BandejaActas as InvestigadorBandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\BandejaPrestamos as InvestigadorBandejaPrestamos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetallePrestamo as InvestigadorDetallePrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistrarDevolucionPrestamo;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitarProrroga;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitudForm;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\VerificacionEntrega;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ResolverLoteQr;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirActaTransferenciaDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirDocumentoDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\ServirDocumentoExportacion;
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
        Route::get('/acta/{id}/descargar-pdf', DescargarActaPdf::class)->name('acta.descargar-pdf');
        Route::get('/acta/{id}/documento-identidad', ServirDocumentoIdentidad::class)->name('acta.documento-identidad');
        Route::get('/acta/{id}/documento-exportacion', ServirDocumentoExportacion::class)->name('acta.documento-exportacion');
        Route::get('/deposito/{id}/documento/{indice}', ServirDocumentoDeposito::class)
            ->where('indice', '[0-9]+')
            ->name('deposito.documento');
        Route::get('/deposito/{id}/qr.pdf', ImprimirQrDeposito::class)->name('deposito.qr-pdf');
        Route::get('/lote/{codigo}', ResolverLoteQr::class)
            ->where('codigo', 'LOTE-[A-Z0-9]{6}')
            ->name('lote.resolver');
        Route::get('/deposito/{id}/acta-transferencia', ServirActaTransferenciaDeposito::class)->name('deposito.acta');
        Route::get('/deposito/{id}/acta-recepcion.pdf', DescargarActaRecepcion::class)->name('deposito.acta-recepcion');

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
            Route::get('/prestamo/{id}/verificacion-entrega', VerificacionEntrega::class)->name('investigador.prestamo.verificacion-entrega');
            Route::get('/prestamo/{id}/registrar-devolucion', RegistrarDevolucionPrestamo::class)->name('investigador.prestamo.registrar-devolucion');
            Route::get('/prestamo/{id}/solicitar-prorroga', SolicitarProrroga::class)->name('investigador.prestamo.solicitar-prorroga');
        });

        // Depositante — solicitudes de depósito
        Route::middleware('role:depositante')->group(function () {
            Route::get('/mis-depositos', MisDepositos::class)->name('investigador.mis-depositos');
            Route::get('/deposito/nueva', RegistroSolicitudDeposito::class)->name('investigador.deposito.crear');
            Route::get('/deposito/{id}/corregir', RegistroSolicitudDeposito::class)->name('investigador.deposito.corregir');
            Route::get('/deposito/{id}', DetalleDeposito::class)->name('investigador.deposito.detalle');
        });

        // Curador — solo usuarios con rol CURADOR
        Route::middleware('role:curador')->group(function () {
            Route::get('/curador/panel', PanelPrestamos::class)->name('curador.panel');
            Route::get('/curador/solicitudes', BandejaSolicitudes::class)->name('curador.solicitudes');
            Route::get('/curador/solicitud/{id}', RevisarSolicitud::class)->name('curador.solicitud.revisar');
            Route::get('/curador/depositos', BandejaDepositos::class)->name('curador.depositos');
            Route::get('/curador/deposito/{id}', RevisarDeposito::class)->name('curador.deposito.revisar');
            Route::get('/curador/deposito/{id}/recepcion', RecepcionFisicaLote::class)->name('curador.deposito.recepcion');
            Route::get('/curador/actas', BandejaActas::class)->name('curador.actas');
            Route::get('/curador/acta/{id}/validar', ValidarActa::class)->name('curador.acta.validar');
            Route::get('/curador/prestamos', CuradorBandejaPrestamos::class)->name('curador.prestamos');
            Route::get('/curador/prestamo/{id}', CuradorDetallePrestamo::class)->name('curador.prestamo.detalle');
            Route::get('/curador/prestamo/{id}/auditar', AuditarPrestamo::class)->name('curador.prestamo.auditar');
            Route::get('/curador/prestamo/{id}/aprobar-verificacion', AprobarVerificacion::class)->name('curador.prestamo.aprobar-verificacion');
            Route::get('/curador/prestamo/{id}/cerrar', CerrarPrestamo::class)->name('curador.prestamo.cerrar');
            Route::get('/curador/prestamo/{id}/prorroga', GestionarProrroga::class)->name('curador.prestamo.gestionar-prorroga');
            Route::get('/curador/configuracion', ConfiguracionRecordatorios::class)->name('curador.configuracion');
            Route::get('/curador/certificado', RegistrarCertificado::class)->name('curador.certificado');
        });
    });
