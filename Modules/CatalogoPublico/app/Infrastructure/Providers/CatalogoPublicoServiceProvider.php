<?php

namespace Modules\CatalogoPublico\Infrastructure\Providers;

use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\GeneradorMarcaAguaPort;
use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Application\Ports\GeneradorXlsxPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorJerarquiaDeEspecimenPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\ImagenTaxonomicaRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Adapters\ClasificadorCatalogoLocal;
use Modules\CatalogoPublico\Infrastructure\Adapters\GeneradorCatalogoExacto;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioOpcionesFiltroAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\JerarquiaDeEspecimenAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\LaravelTransactionManager;
use Modules\CatalogoPublico\Infrastructure\Adapters\MarcaAguaAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\NullEventPublisher;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\CatalogoPublico\Infrastructure\Console\MigrarImagenesCatalogoAR2Command;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentImagenPorDefectoRepository;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentImagenTaxonomicaRepository;
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
        ImagenTaxonomicaRepositoryInterface::class => EloquentImagenTaxonomicaRepository::class,
        ImagenPorDefectoRepositoryInterface::class => EloquentImagenPorDefectoRepository::class,
        TransactionManagerPort::class => LaravelTransactionManager::class,
        EventPublisherPort::class => NullEventPublisher::class,
        ProveedorEspecimenesPort::class => InventarioGestionColeccionEspecimenAdapter::class,
        ProveedorEspecimenesParaArbolPort::class => EloquentProveedorEspecimenesParaArbol::class,
        ProveedorJerarquiaDeEspecimenPort::class => JerarquiaDeEspecimenAdapter::class,
        ProveedorOpcionesFiltroPort::class => InventarioOpcionesFiltroAdapter::class,
        GeneradorXlsxPort::class => PhpSpreadsheetGeneradorXlsxAdapter::class,
        AlmacenamientoImagenesPort::class => StorageImagenesAdapter::class,
        GeneradorMarcaAguaPort::class => MarcaAguaAdapter::class,
    ];

    public function register(): void
    {
        parent::register();

        if ($this->app->runningInConsole()) {
            $this->commands([MigrarImagenesCatalogoAR2Command::class]);
        }

        $this->app->bind(ClasificadorIntencionPort::class, ClasificadorCatalogoLocal::class);
        $this->app->bind(GeneradorRespuestaChatBotPort::class, GeneradorCatalogoExacto::class);
        $this->app->scoped(ReferenciaTaxonomicaPublica::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
