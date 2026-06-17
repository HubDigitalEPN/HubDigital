<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentoExportacionSubido;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoActivado;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoHabilitadoParaEnvio;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoObservada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaRegistrada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Listeners\EnviarNotificacionRecordatorioListener;
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
        RecordatorioDevolucionEnviado::class => [
            RegistrarEventoHistorialListener::class,
            EnviarNotificacionRecordatorioListener::class,
        ],
        VerificacionEntregaRegistrada::class => [RegistrarEventoHistorialListener::class],
        VerificacionEntregaAprobada::class => [RegistrarEventoHistorialListener::class],
        PrestamoActivado::class => [RegistrarEventoHistorialListener::class],
        DocumentoExportacionSubido::class => [RegistrarEventoHistorialListener::class],
        PrestamoHabilitadoParaEnvio::class => [RegistrarEventoHistorialListener::class],
    ];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
