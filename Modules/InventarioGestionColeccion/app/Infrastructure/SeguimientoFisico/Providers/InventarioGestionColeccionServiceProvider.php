<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Providers;

use Livewire\Livewire;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GestorTokenEsp32Port;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\HorarioValidadorPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TraductorErroresPersistenciaPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionEspecimenPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\ConfiguracionColumnaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\HorarioRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\SincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\EventServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\Providers\RouteServiceProvider;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\DatabaseHorarioValidadorAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\EloquentUbicacionEspecimenAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\HttpSeguridadContextoAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelEventPublisherAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\LaravelTransactionManagerAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\NominatimGeocodificadorInversoAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\PostgresTraductorErroresPersistenciaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\SanctumTokenEsp32Adapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\SimplePdfActaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\TaxonArbolClasificacionTaxonomicaAdapter;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console\ExportarGbifCommand;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Console\ImportarCatalogoInvertebradosCommand;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentAlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentCodigoQrRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentConfiguracionColumnaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentDatasetConfigRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEspecimenRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentEventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentHorarioRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentIdentificacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentLocalidadRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentMuestraColectaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentNotificacionRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentOrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentRanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentSincronizacionEsp32Repository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentTaxonRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentUnitTrayRepository;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories\EloquentVisitanteRepository;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Mapa\MapaInteractivo;
use Modules\InventarioGestionColeccion\Presentation\Http\Middleware\VisitanteSesionMiddleware;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Proveedor de servicios del módulo: cablea cada interfaz de dominio (repositorios) y cada
 * Port de aplicación con su implementación concreta de infraestructura mediante el array
 * $bindings, registra los comandos de consola y arranca las migraciones y el componente
 * Livewire del mapa interactivo. Es el punto único donde se resuelven las dependencias del
 * componente de seguimiento físico, conforme a la regla del proyecto.
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
        CajaRepository::class => EloquentCajaRepository::class,
        UnitTrayRepository::class => EloquentUnitTrayRepository::class,
        UnitTrayEspecimenRepository::class => EloquentUnitTrayEspecimenRepository::class,
        GabineteRepository::class => EloquentGabineteRepository::class,
        RanuraGabineteRepository::class => EloquentRanuraGabineteRepository::class,
        HorarioRepository::class => EloquentHorarioRepository::class,
        AlertaUbicacionRepository::class => EloquentAlertaUbicacionRepository::class,
        UbicacionCajaRepository::class => EloquentUbicacionCajaRepository::class,
        NotificacionRepository::class => EloquentNotificacionRepository::class,
        OrdenEsperadoFamiliasRepository::class => EloquentOrdenEsperadoFamiliasRepository::class,
        EventoCicloIotRepository::class => EloquentEventoCicloIotRepository::class,
        SincronizacionEsp32Repository::class => EloquentSincronizacionEsp32Repository::class,
        TransactionManagerPort::class => LaravelTransactionManagerAdapter::class,
        EventPublisherPort::class => LaravelEventPublisherAdapter::class,
        HorarioValidadorPort::class => DatabaseHorarioValidadorAdapter::class,
        ContextoEjecucionPort::class => HttpSeguridadContextoAdapter::class,
        TaxonRepositoryInterface::class => EloquentTaxonRepository::class,
        EspecimenRepositoryInterface::class => EloquentEspecimenRepository::class,
        CodigoQrRepositoryInterface::class => EloquentCodigoQrRepository::class,
        EntidadDepositanteRepositoryInterface::class => EloquentEntidadDepositanteRepository::class,
        VisitanteRepositoryInterface::class => EloquentVisitanteRepository::class,
        DatasetConfigRepositoryInterface::class => EloquentDatasetConfigRepository::class,
        ConfiguracionColumnaRepositoryInterface::class => EloquentConfiguracionColumnaRepository::class,
        LocalidadRepositoryInterface::class => EloquentLocalidadRepository::class,
        MuestraColectaRepositoryInterface::class => EloquentMuestraColectaRepository::class,
        IdentificacionRepositoryInterface::class => EloquentIdentificacionRepository::class,
        GeneradorActaPdfPort::class => SimplePdfActaAdapter::class,
        ClasificacionTaxonomicaPort::class => TaxonArbolClasificacionTaxonomicaAdapter::class,
        UbicacionEspecimenPort::class => EloquentUbicacionEspecimenAdapter::class,
        GestorTokenEsp32Port::class => SanctumTokenEsp32Adapter::class,
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
            ]);
        }
    }

    /**
     * Arranca el módulo: carga sus migraciones y registra el mapa interactivo como componente
     * Livewire con alias, de modo que distintas páginas-host puedan montarlo de forma reutilizable.
     */
    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // El mapa interactivo es un componente Livewire anidado y reutilizable: se
        // registra con alias para que distintas páginas-host (curador y portal del
        // visitante) puedan montarlo con su propio modo.
        Livewire::component('inventario-mapa-interactivo', MapaInteractivo::class);

        // Guarda de la sesión efímera del visitante: protege el mapa del visitante para
        // que solo entre quien llegó por un QR válido y vigente, y nada más.
        $this->app['router']->aliasMiddleware('visitante', VisitanteSesionMiddleware::class);
    }
}
