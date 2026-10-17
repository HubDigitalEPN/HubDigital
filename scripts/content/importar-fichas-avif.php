<?php

declare(strict_types=1);

// Contenido editorial únicamente. No ejecuta suites, compila ni activa releases.
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;
use Modules\CatalogoPublico\Infrastructure\ImportadorFichasAvif;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$carpeta = $argv[1] ?? ''; $modo = $argv[2] ?? '--plan'; $respaldo = $argv[3] ?? '';
if (!in_array($modo, ['--plan', '--aplicar-produccion'], true) || !is_dir($carpeta)) throw new InvalidArgumentException('Indica carpeta editorial y --plan o --aplicar-produccion [respaldo nuevo].');
$plan = json_decode(file_get_contents(rtrim($carpeta, '/\\').'/manifiesto.json'), true, flags: JSON_THROW_ON_ERROR);
if (($plan['version'] ?? 0) !== 1 || !is_array($plan['entradas'] ?? null)) throw new InvalidArgumentException('Manifiesto editorial inválido.');
$importador = app(ImportadorFichasAvif::class); $conteos = []; $huellas = []; $fotos = []; $ids = [];
// Todo el plan se revisa antes de la primera escritura en R2 o PostgreSQL.
foreach ($plan['entradas'] as $entrada) {
    $p = $importador->preparar($entrada, rtrim($carpeta, '/\\').'/fotos');
    if (isset($huellas[$entrada['foto_sha256']])
        || isset($fotos[$entrada['foto_id']]) || isset($ids[$entrada['taxon_id']])) throw new InvalidArgumentException('El plan repite fotografía o taxón.');
    $huellas[$entrada['foto_sha256']] = $fotos[$entrada['foto_id']] = $ids[$entrada['taxon_id']] = true;
    $conteos[$p['estado']] = ($conteos[$p['estado']] ?? 0) + 1;
}
echo json_encode(['modo' => $modo, 'fotos_unicas' => count($huellas), 'fichas' => $conteos], JSON_UNESCAPED_UNICODE).PHP_EOL;
if ($modo === '--plan') exit;
if (config('deposit-storage.driver') !== 'r2' || config('deposit-storage.r2.bucket') !== 'labinvepn-depositos-produccion'
    || config('deposit-storage.prefix') !== 'produccion') throw new RuntimeException('La carga está limitada al bucket y prefijo de producción.');
if (($conteos['conflicto'] ?? 0) > 0) throw new RuntimeException('Hay fichas editadas después del inventario. Revisa el plan sin sobrescribirlas.');
if (!$respaldo || file_exists($respaldo)) throw new InvalidArgumentException('Indica una ruta de respaldo nueva.');
$copia = DB::table(FichasTaxonomicas::TABLA)->whereIn('taxon_id', array_keys($ids))->get()->all();
$archivo = fopen($respaldo, 'x');
if (!$archivo) throw new RuntimeException('No se pudo crear el respaldo editorial.');
try {
    chmod($respaldo, 0600); $contenido = json_encode(['ids' => array_keys($ids), 'fichas' => $copia], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (fwrite($archivo, $contenido) !== strlen($contenido)) throw new RuntimeException('No se pudo escribir el respaldo completo.');
}
finally {fclose($archivo);}
$resultado = []; $n = 0;
foreach ($plan['entradas'] as $entrada) {
    $p = $importador->preparar($entrada, rtrim($carpeta, '/\\').'/fotos');
    if ($p['estado'] === 'conflicto') throw new RuntimeException('Una ficha cambió durante la carga. Se conserva esa edición; reanuda tras revisar el conflicto.');
    $estado = $importador->aplicar($p); $resultado[$estado] = ($resultado[$estado] ?? 0) + 1;
    if (++$n % 50 === 0) {echo json_encode(['procesadas' => $n, 'resultado' => $resultado]).PHP_EOL; flush();}
}
echo json_encode(['finalizado' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE).PHP_EOL;
