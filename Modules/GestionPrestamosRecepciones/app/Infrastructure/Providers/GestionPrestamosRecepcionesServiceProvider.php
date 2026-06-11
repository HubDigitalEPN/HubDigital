<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\DocumentacionInsuficiente;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\LimiteAnualDepositosAlcanzado;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\EloquentHistorialAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\FakeNotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\GbifValidacionTaxonomicaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\GroqExtraccionDatosDocumentoAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\InventarioGestionColeccionCatalogoCuraduriaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\PdfsigValidacionFirmaElectronicaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands\EvaluarPlazosDevolucionTodosLosPrestamosCommand;
use Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands\LimpiarBorradoresAbandonadosCommand;
use Modules\GestionPrestamosRecepciones\Infrastructure\Gateways\DomPdfGeneratorAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Gateways\LaravelUserInvestigadorEmailAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentConfiguracionGlobalRecordatoriosRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentRecordatorioDevolucionRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentSolicitudPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentVerificacionEntregaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories\EloquentMatrizEspeciesRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories\EloquentSolicitudDepositoRepository;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitudForm;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Proveedor principal de servicios del módulo.
 *
 * Registra los enlaces (bindings) de las interfaces de dominio con sus
 * implementaciones en la capa de infraestructura. También arranca componentes
 * de Livewire, comandos Artisan, excepciones personalizadas y tareas programadas.
 */
class GestionPrestamosRecepcionesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'GestionPrestamosRecepciones';

    protected string $nameLower = 'gestionprestamosrecepciones';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Mapeo de interfaces (Ports) a sus implementaciones (Adapters).
     */
    public array $bindings = [
        SolicitudPrestamoRepositoryInterface::class => EloquentSolicitudPrestamoRepository::class,
        ActaPrestamoRepositoryInterface::class => EloquentActaPrestamoRepository::class,
        PrestamoRepositoryInterface::class => EloquentPrestamoRepository::class,
        SolicitudDepositoRepositoryInterface::class => EloquentSolicitudDepositoRepository::class,
        MatrizEspeciesRepositoryInterface::class => EloquentMatrizEspeciesRepository::class,
        EventPublisherPort::class => LaravelEventPublisherAdapter::class,
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
        NotificacionCuratoriaPort::class => FakeNotificacionCuratoriaAdapter::class,
        ValidacionFirmaElectronicaPort::class => PdfsigValidacionFirmaElectronicaAdapter::class,
        HistorialPort::class => EloquentHistorialAdapter::class,
        RecordatorioDevolucionRepositoryInterface::class => EloquentRecordatorioDevolucionRepository::class,
        ConfiguracionGlobalRecordatoriosRepositoryInterface::class => EloquentConfiguracionGlobalRecordatoriosRepository::class,
        InvestigadorEmailPort::class => LaravelUserInvestigadorEmailAdapter::class,
        CatalogoCuraduriaPort::class => InventarioGestionColeccionCatalogoCuraduriaAdapter::class,
        ValidacionTaxonomicaPort::class => GbifValidacionTaxonomicaAdapter::class,
        PdfGeneratorPort::class => DomPdfGeneratorAdapter::class,
        VerificacionEntregaPrestamoRepositoryInterface::class => EloquentVerificacionEntregaPrestamoRepository::class,
    ];

    /**
     * Registra servicios en el contenedor de dependencias.
     */
    public function register(): void
    {
        parent::register();

        $this->commands([
            LimpiarBorradoresAbandonadosCommand::class,
            EvaluarPlazosDevolucionTodosLosPrestamosCommand::class,
        ]);

        $this->app->bind(ExtraccionDatosDocumentoPort::class, fn () => new GroqExtraccionDatosDocumentoAdapter(
            modelo: config('ai.providers.groq.model'),
        ));
    }

    /**
     * Configura las tareas programadas (CRON) del módulo.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(LimpiarBorradoresAbandonadosCommand::class)->daily();
        $schedule->command(EvaluarPlazosDevolucionTodosLosPrestamosCommand::class)->daily();
    }

    /**
     * Arranca servicios y configuraciones de componentes tras el registro.
     */
    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(function (SolicitudNoEncontradaException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 404);
        });
        $handler->renderable(function (LimiteAnualDepositosAlcanzado $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
        $handler->renderable(function (TransicionEstadoInvalida $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
        $handler->renderable(function (DocumentacionInsuficiente $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
        $handler->renderable(function (\DomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        });
        Livewire::component('prestamos.investigador.registro-solicitud-deposito', RegistroSolicitudDeposito::class);
        Livewire::component('prestamos.investigador.mis-solicitudes', MisSolicitudes::class);
        Livewire::component('prestamos.investigador.solicitud-form', SolicitudForm::class);
        Livewire::component('prestamos.investigador.detalle-solicitud', DetalleSolicitud::class);
        Livewire::component('prestamos.curador.bandeja-solicitudes', BandejaSolicitudes::class);
        Livewire::component('prestamos.curador.revisar-solicitud', RevisarSolicitud::class);
        Livewire::component('prestamos.curador.bandeja-actas', BandejaActas::class);
        Livewire::component('prestamos.curador.validar-acta', ValidarActa::class);
    }
}
