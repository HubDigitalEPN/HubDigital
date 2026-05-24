<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Providers;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\SincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\EventServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\RouteServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\DatabaseHorarioValidadorAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\HttpSeguridadContextoAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelEventPublisherAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\SimplePdfActaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentAlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEspecimenRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentHorarioRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentNotificacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentSincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentTaxonRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUnitTrayRepository;
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
        UnitTrayRepository::class => EloquentUnitTrayRepository::class,
        GabineteRepository::class => EloquentGabineteRepository::class,
        RanuraGabineteRepository::class => EloquentRanuraGabineteRepository::class,
        HorarioRepository::class => EloquentHorarioRepository::class,
        AlertaUbicacionRepository::class => EloquentAlertaUbicacionRepository::class,
        UbicacionCajaRepository::class => EloquentUbicacionCajaRepository::class,
        NotificacionRepository::class => EloquentNotificacionRepository::class,
        EventoCicloIotRepository::class => EloquentEventoCicloIotRepository::class,
        SincronizacionEsp32Repository::class => EloquentSincronizacionEsp32Repository::class,
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
        EventPublisherPort::class => LaravelEventPublisherAdapter::class,
        HorarioValidadorPort::class => DatabaseHorarioValidadorAdapter::class,
        ContextoEjecucionPort::class => HttpSeguridadContextoAdapter::class,
        TaxonRepositoryInterface::class => EloquentTaxonRepository::class,
        EspecimenRepositoryInterface::class => EloquentEspecimenRepository::class,
        EntidadDepositanteRepositoryInterface::class => EloquentEntidadDepositanteRepository::class,
        GeneradorActaPdfPort::class => SimplePdfActaAdapter::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
