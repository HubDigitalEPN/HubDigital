<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentSolicitudPrestamoRepository;
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
    }
}
