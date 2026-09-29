<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Providers;

use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TraductorErroresPersistenciaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\EventServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\RouteServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelEventPublisherAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\NominatimGeocodificadorInversoAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\PostgresTraductorErroresPersistenciaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\SimplePdfActaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console\DiagnosticoCatalogoCommand;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console\ExportarGbifCommand;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console\ImportarCatalogoInvertebradosCommand;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\CachedConfiguracionColumnaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentBitacoraEdicionMasivaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentCodigoQrRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentDatasetConfigRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEspecimenRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentIdentificacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentLocalidadRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentMuestraColectaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentTaxonRepository;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\ExportarEspecimenesGbif;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos\FichaEspecimenModal;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Proveedor de servicios del módulo: cablea cada interfaz de dominio (repositorios) y cada
 * Port de aplicación con su implementación concreta de infraestructura mediante el array
 * $bindings, registra los comandos de consola y arranca las migraciones y el componente
 * Livewire del catálogo. Es el punto único donde se resuelven las dependencias del
 * inventario científico, conforme a la regla del proyecto.
 */
class InventarioGestionColeccionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'InventarioGestionColeccion';

    protected string $nameLower = 'inventariogestioncoleccion';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public array $bindings = [
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
        EventPublisherPort::class => LaravelEventPublisherAdapter::class,
        TaxonRepositoryInterface::class => EloquentTaxonRepository::class,
        EspecimenRepositoryInterface::class => EloquentEspecimenRepository::class,
        CodigoQrRepositoryInterface::class => EloquentCodigoQrRepository::class,
        EntidadDepositanteRepositoryInterface::class => EloquentEntidadDepositanteRepository::class,
        DatasetConfigRepositoryInterface::class => EloquentDatasetConfigRepository::class,
        // Decorador con caché: se lee en cada render de Livewire y se escribe
        // desde una sola pantalla de administración.
        ConfiguracionColumnaRepositoryInterface::class => CachedConfiguracionColumnaRepository::class,
        LocalidadRepositoryInterface::class => EloquentLocalidadRepository::class,
        MuestraColectaRepositoryInterface::class => EloquentMuestraColectaRepository::class,
        IdentificacionRepositoryInterface::class => EloquentIdentificacionRepository::class,
        BitacoraEdicionMasivaRepositoryInterface::class => EloquentBitacoraEdicionMasivaRepository::class,
        GeneradorActaPdfPort::class => SimplePdfActaAdapter::class,
        TraductorErroresPersistenciaPort::class => PostgresTraductorErroresPersistenciaAdapter::class,
        GeocodificadorInversoPort::class => NominatimGeocodificadorInversoAdapter::class,
    ];

    /**
     * Registra los servicios del módulo: aplica el cableado base del proveedor y, solo cuando
     * la aplicación corre en consola, da de alta los comandos de importación y exportación.
     */
    public function register(): void
    {
        parent::register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportarCatalogoInvertebradosCommand::class,
                ExportarGbifCommand::class,
                DiagnosticoCatalogoCommand::class,
            ]);
        }
    }

    /**
     * Arranca el módulo: carga sus migraciones y registra los componentes Livewire del catálogo.
     */
    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // Sección de selección/exportación de especímenes embebida en la página
        // de Publicación GBIF (componente Livewire anidado).
        Livewire::component('inventario-exportar-especimenes-gbif', ExportarEspecimenesGbif::class);

        // Modal reutilizable con la ficha completa (todas las columnas) de un
        // espécimen: se monta una vez por bandeja de revisión y escucha el evento
        // `ver-ficha-especimen`.
        Livewire::component('inventario-ficha-especimen', FichaEspecimenModal::class);
    }
}
