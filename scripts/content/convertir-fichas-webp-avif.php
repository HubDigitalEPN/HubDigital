<?php

declare(strict_types=1);

// Conversión editorial reanudable. Conserva leyendas, créditos y publicación.
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$modo = $argv[1] ?? '--plan'; $respaldo = $argv[2] ?? '';
if (!in_array($modo, ['--plan', '--aplicar-produccion'], true)) throw new InvalidArgumentException('Usa --plan o --aplicar-produccion [respaldo nuevo].');
$filas = DB::table(FichasTaxonomicas::TABLA)->where('foto_ruta', 'like', '%.webp')->get();
echo json_encode(['fichas_por_convertir' => $filas->count(), 'objetos_webp' => $filas->unique('foto_sha256')->count()]).PHP_EOL;
if ($modo === '--plan' || $filas->isEmpty()) exit;
if (config('deposit-storage.driver') !== 'r2' || config('deposit-storage.r2.bucket') !== 'labinvepn-depositos-produccion'
    || config('deposit-storage.prefix') !== 'produccion') throw new RuntimeException('La conversión está limitada al bucket y prefijo de producción.');
if (!$respaldo || file_exists($respaldo)) throw new InvalidArgumentException('Indica una ruta de respaldo nueva.');
$archivo = fopen($respaldo, 'x');
if (!$archivo) throw new RuntimeException('No se pudo crear el respaldo editorial.');
try {
    chmod($respaldo, 0600); $contenido = json_encode($filas->all(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (fwrite($archivo, $contenido) !== strlen($contenido)) throw new RuntimeException('No se pudo escribir el respaldo completo.');
}
finally {fclose($archivo);}
$fichas = app(FichasTaxonomicas::class); $fotos = []; $convertidas = 0;
foreach ($filas as $ficha) {
    $sha = $ficha->foto_sha256;
    $fotos[$sha] ??= $fichas->prepararFoto($fichas->contenidoFoto($ficha));
    $fichas->guardar($ficha->taxon_id, [], $fotos[$sha], false, $ficha->updated_by, (string) $ficha->updated_at);
    if (++$convertidas % 100 === 0) {echo json_encode(['convertidas' => $convertidas, 'objetos_avif' => count($fotos)]).PHP_EOL; flush();}
}
echo json_encode(['finalizado' => true, 'convertidas' => $convertidas, 'objetos_avif' => count($fotos), 'leyendas_conservadas' => true]).PHP_EOL;
