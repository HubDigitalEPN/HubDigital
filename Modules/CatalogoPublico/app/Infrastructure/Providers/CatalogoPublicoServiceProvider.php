<?php

namespace Modules\CatalogoPublico\Infrastructure\Providers;

use Illuminate\Support\Facades\View;
use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Adapters\LaravelTransactionManager;
use Modules\CatalogoPublico\Infrastructure\Adapters\NullEventPublisher;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenRepository;
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
        EspecimenRepositoryInterface::class => EloquentEspecimenRepository::class,
        EspecimenDivulgableRepositoryInterface::class => EloquentEspecimenDivulgableRepository::class,
        TransactionManagerPort::class => LaravelTransactionManager::class,
        EventPublisherPort::class => NullEventPublisher::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        View::composer('layouts.admin*', function ($view) {
            $view->getFactory()->startPush('admin-nav-items');
            echo view('catalogopublico::partials.admin-nav')->render();
            $view->getFactory()->stopPush();
        });
    }
}
