<?php

declare(strict_types=1);

// Inventario editorial para preparar la carga autorizada, sin modificar taxones.
$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$publico = app(\Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico::class)
    ->estructura(\Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda::vacio());
$datos = [
    'taxones' => \Illuminate\Support\Facades\DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango'])->all(),
    'publicos' => $publico['nodos'],
    'fichas' => \Illuminate\Support\Facades\DB::table(\Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas::TABLA)->get()->all(),
];
if (!empty($argv[1])) {
    file_put_contents($argv[1], json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo json_encode(['taxones' => count($datos['taxones']), 'nodos_publicos' => count($datos['publicos']), 'fichas' => count($datos['fichas'])]).PHP_EOL;
} else echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
