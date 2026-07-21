<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaCriptograficamentePorCurador;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DevolucionRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentoExportacionSubido;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoActivado;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoCerrado;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoHabilitadoParaEnvio;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaConObservaciones;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaFisicamente;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoObservada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaRegistrada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\EnviarNotificacionCierrePrestamoListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\EnviarNotificacionDevolucionRegistradaListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\EnviarNotificacionRecordatorioListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\EnviarNotificacionResultadoProrrogaListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\IngresarLoteEnColeccionListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\IniciarPrestamoAlValidarActaListener;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\RegistrarEventoHistorialListener;

/**
 * Proveedor de servicios que registra los suscriptores (listeners) para los eventos
 * de dominio emitidos por el módulo de Préstamos y Recepciones.
 */
class EventServiceProvider extends ServiceProvider
{
    /** @var array<string, array<int, string>> */
    protected $listen = [
        // Traspaso a la colección: la recepción física aprobada materializa los
        // especímenes de la matriz en InventarioGestionColeccion (en cola).
        RecepcionLoteVerificadaFisicamente::class => [IngresarLoteEnColeccionListener::class],
        RecepcionLoteVerificadaConObservaciones::class => [IngresarLoteEnColeccionListener::class],

        SolicitudPrestamoRegistrada::class => [RegistrarEventoHistorialListener::class],
        SolicitudPrestamoEnviada::class => [RegistrarEventoHistorialListener::class],
        SolicitudPrestamoObservada::class => [RegistrarEventoHistorialListener::class],
        SolicitudPrestamoAprobada::class => [RegistrarEventoHistorialListener::class],
        SolicitudPrestamoRechazada::class => [RegistrarEventoHistorialListener::class],
        PrestamoIniciado::class => [RegistrarEventoHistorialListener::class],
        ActaEnviada::class => [RegistrarEventoHistorialListener::class],
        ActaFirmadaSubida::class => [RegistrarEventoHistorialListener::class],
        ActaDevueltaPorFirmaInvalida::class => [RegistrarEventoHistorialListener::class],
        ActaValidada::class => [
            RegistrarEventoHistorialListener::class,
            IniciarPrestamoAlValidarActaListener::class,
        ],
        ActaFirmadaCriptograficamentePorCurador::class => [RegistrarEventoHistorialListener::class],
        RecordatorioDevolucionEnviado::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionRecordatorioListener::class,
        ],
        VerificacionEntregaRegistrada::class => [RegistrarEventoHistorialListener::class],
        VerificacionEntregaAprobada::class => [RegistrarEventoHistorialListener::class],
        PrestamoActivado::class => [RegistrarEventoHistorialListener::class],
        DocumentoExportacionSubido::class => [RegistrarEventoHistorialListener::class],
        PrestamoHabilitadoParaEnvio::class => [RegistrarEventoHistorialListener::class],
        DevolucionRegistrada::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionDevolucionRegistradaListener::class,
        ],
        PrestamoCerrado::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionCierrePrestamoListener::class,
        ],
        ProrrogaSolicitada::class => [RegistrarEventoHistorialListener::class],
        ProrrogaAprobada::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionResultadoProrrogaListener::class,
        ],
        ProrrogaRechazada::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionResultadoProrrogaListener::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
