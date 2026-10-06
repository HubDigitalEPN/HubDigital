<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioOpcionesFiltroAdapter;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

$raiz = dirname(__DIR__, 3);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$estadisticas = app(PortalEstadisticas::class);
$datos = $estadisticas->datosParaVista([], false);
$estadisticas->geografiaParaFiltros(FiltrosBusqueda::vacio());
app(EloquentProveedorEspecimenesParaArbol::class)->filosPublicosDisponibles();
$opciones = app(InventarioOpcionesFiltroAdapter::class);
$opciones->obtenerPreparaciones();
$opciones->obtenerBiomas();
$opciones->obtenerMetodosRecoleccion();
$opciones->obtenerColectores();
echo 'Caché inicial del portal preparada: '.(int) $datos['resumen']['registros'].' registros públicos.'.PHP_EOL;
