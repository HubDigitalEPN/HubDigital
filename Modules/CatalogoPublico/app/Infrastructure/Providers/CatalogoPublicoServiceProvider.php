<?php

namespace Modules\CatalogoPublico\Infrastructure\Providers;

use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\LaravelTransactionManager;
use Modules\CatalogoPublico\Infrastructure\Adapters\NullEventPublisher;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenDivulgableRepository;
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
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
