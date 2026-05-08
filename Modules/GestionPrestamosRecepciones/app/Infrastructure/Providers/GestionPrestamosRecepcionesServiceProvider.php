<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\DocumentacionInsuficiente;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\LimiteAnualDepositosAlcanzado;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\EloquentTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\SyncEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories\EloquentSolicitudDepositoRepository;
use Livewire\Livewire;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentSolicitudPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaActas;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\ValidarActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleSolicitud;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisSolicitudes;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\SolicitudForm;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GestionPrestamosRecepcionesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'GestionPrestamosRecepciones';

    protected string $nameLower = 'gestionprestamosrecepciones';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public array $bindings = [
        SolicitudPrestamoRepositoryInterface::class => EloquentSolicitudPrestamoRepository::class,
        ActaPrestamoRepositoryInterface::class => EloquentActaPrestamoRepository::class,
        EventPublisherPort::class => LaravelEventPublisherAdapter::class,
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(function (SolicitudNoEncontradaException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 404);
            }
        });
        $handler->renderable(function (LimiteAnualDepositosAlcanzado $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
        $handler->renderable(function (TransicionEstadoInvalida $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
        $handler->renderable(function (DocumentacionInsuficiente $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
        $handler->renderable(function (\DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
        Livewire::component('prestamos.investigador.mis-solicitudes', MisSolicitudes::class);
        Livewire::component('prestamos.investigador.solicitud-form', SolicitudForm::class);
        Livewire::component('prestamos.investigador.detalle-solicitud', DetalleSolicitud::class);
        Livewire::component('prestamos.curador.bandeja-solicitudes', BandejaSolicitudes::class);
        Livewire::component('prestamos.curador.revisar-solicitud', RevisarSolicitud::class);
        Livewire::component('prestamos.curador.bandeja-actas', BandejaActas::class);
        Livewire::component('prestamos.curador.validar-acta', ValidarActa::class);
    }
}
