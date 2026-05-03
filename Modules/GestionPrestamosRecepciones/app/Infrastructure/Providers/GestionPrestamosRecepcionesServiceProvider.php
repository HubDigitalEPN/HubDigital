<?php

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\EloquentTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\SyncEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories\EloquentSolicitudDepositoRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GestionPrestamosRecepcionesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'GestionPrestamosRecepciones';

    protected string $nameLower = 'gestionprestamosrecepciones';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** @var array<class-string, class-string> */
    public array $bindings = [
        SolicitudDepositoRepositoryInterface::class => EloquentSolicitudDepositoRepository::class,
        TransactionManagerPort::class => EloquentTransactionManagerAdapter::class,
        EventPublisherPort::class => SyncEventPublisherAdapter::class,
    ];

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
