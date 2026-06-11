<?php

namespace Modules\CatalogoPublico\Infrastructure\Providers;

use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioOpcionesFiltroAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\LaravelTransactionManager;
use Modules\CatalogoPublico\Infrastructure\Adapters\NullEventPublisher;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CatalogoPublicoServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'CatalogoPublico';

    protected string $nameLower = 'catalogopublico';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public array $bindings = [
        EspecimenDivulgableRepositoryInterface::class => EloquentEspecimenDivulgableRepository::class,
        TransactionManagerPort::class => LaravelTransactionManager::class,
        EventPublisherPort::class => NullEventPublisher::class,
        ProveedorEspecimenesPort::class => InventarioGestionColeccionEspecimenAdapter::class,
        ProveedorEspecimenesParaArbolPort::class => EloquentProveedorEspecimenesParaArbol::class,
        ProveedorOpcionesFiltroPort::class => InventarioOpcionesFiltroAdapter::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
