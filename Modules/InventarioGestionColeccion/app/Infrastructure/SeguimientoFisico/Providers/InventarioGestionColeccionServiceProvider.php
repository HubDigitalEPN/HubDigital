<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Providers;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\SincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\EventServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\RouteServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentAlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentNotificacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentSincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUbicacionCajaRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InventarioGestionColeccionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'InventarioGestionColeccion';

    protected string $nameLower = 'inventariogestioncoleccion';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public array $bindings = [
        CajaRepository::class => EloquentCajaRepository::class,
        GabineteRepository::class => EloquentGabineteRepository::class,
        RanuraGabineteRepository::class => EloquentRanuraGabineteRepository::class,
        AlertaUbicacionRepository::class => EloquentAlertaUbicacionRepository::class,
        UbicacionCajaRepository::class => EloquentUbicacionCajaRepository::class,
        NotificacionRepository::class => EloquentNotificacionRepository::class,
        EventoCicloIotRepository::class => EloquentEventoCicloIotRepository::class,
        SincronizacionEsp32Repository::class => EloquentSincronizacionEsp32Repository::class,
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
