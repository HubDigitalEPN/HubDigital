<?php

declare(strict_types=1);

// Carga editorial explícita. No modifica taxonomía, no publica código ni ejecuta suites.
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$modo = $argv[1] ?? '--plan';
if (!in_array($modo, ['--plan', '--aplicar', '--exportar-vm'], true)) throw new InvalidArgumentException('Usa --plan, --aplicar o --exportar-vm.');
$carpeta = $raiz.'/database/seed-data/fichas-taxonomicas';
$plan = json_decode(file_get_contents($carpeta.'/ampliacion.json'), true, flags: JSON_THROW_ON_ERROR);
$taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango'])->keyBy('id');
$identidades = [];
foreach ($taxones as $taxon) {
    $camino = []; $visitados = []; $nodo = $taxon;
    while ($nodo && !isset($visitados[$nodo->id]) && count($visitados) < 64) {
        $visitados[$nodo->id] = true;
        array_unshift($camino, [mb_strtolower(trim($nodo->rango)), $nodo->nombre_cientifico]);
        $nodo = $taxones[$nodo->padre_id] ?? null;
    }
    if ($nodo) continue;
    $firma = json_encode($camino, JSON_UNESCAPED_UNICODE);
    $identidades[$firma][] = $taxon->id;
}
$entradas = []; $sinIdentidad = 0;
foreach ($plan['fichas'] as $entrada) {
    $ids = $identidades[json_encode($entrada['linaje'], JSON_UNESCAPED_UNICODE)] ?? [];
    if (count($ids) !== 1) {$sinIdentidad++; continue;}
    $entradas[$ids[0]] = $entrada;
}
if ($modo === '--plan') {
    echo json_encode(['objetivo' => $plan['objetivo'], 'taxones' => $taxones->count(), 'identidades_resueltas' => count($entradas), 'sin_identidad_unica' => $sinIdentidad]).PHP_EOL;
    exit;
}
$fichas = app(FichasTaxonomicas::class);
$actuales = DB::table(FichasTaxonomicas::TABLA)->get()->keyBy('taxon_id');
if ($modo === '--aplicar') {
    $fotos = []; $preparadas = 0;
    foreach ($plan['fotos'] as $grupo => $referencia) {
        $existente = $actuales->first(fn (object $f): bool => $f->foto_ruta && $f->fuente_url === $referencia['fuente']);
        if ($existente) {$fotos[$grupo] = ['foto_ruta' => $existente->foto_ruta, 'foto_sha256' => $existente->foto_sha256]; continue;}
        if (!preg_match('/^[a-z0-9-]+\.webp$/D', $referencia['archivo'])) throw new InvalidArgumentException('Nombre de miniatura inválido.');
        $contenido = file_get_contents($carpeta.'/'.$referencia['archivo']);
        if (!hash_equals($referencia['sha256'], hash('sha256', $contenido))) throw new RuntimeException('La miniatura no corresponde a su referencia.');
        $fotos[$grupo] = $fichas->prepararFoto($contenido);
        $preparadas++;
        if ($preparadas % 10 === 0) {echo json_encode(['fotos_preparadas' => $preparadas]).PHP_EOL; flush();}
    }
    $filas = []; $fecha = now();
    foreach ($entradas as $id => $entrada) {
        if ($actuales->has($id)) continue;
        $referencia = $plan['fotos'][$entrada['foto_grupo']]; $foto = $fotos[$entrada['foto_grupo']];
        if (mb_strlen($entrada['descripcion']) > 800 || mb_strlen($entrada['foto_alt']) > 180 || mb_strlen($referencia['credito']) > 500) throw new InvalidArgumentException('Contenido editorial demasiado largo.');
        $filas[] = ['taxon_id' => $id, 'descripcion' => $entrada['descripcion'], 'foto_ruta' => $foto['foto_ruta'], 'foto_sha256' => $foto['foto_sha256'],
            'foto_alt' => $entrada['foto_alt'], 'credito' => $referencia['credito'], 'fuente_url' => $referencia['fuente'], 'publicada' => true,
            'updated_by' => null, 'created_at' => $fecha, 'updated_at' => $fecha];
    }
    $creadas = 0;
    foreach (array_chunk($filas, 400) as $lote) {
        $creadas += DB::transaction(fn (): int => DB::table(FichasTaxonomicas::TABLA)->insertOrIgnore($lote));
    }
    $publicadas = DB::table(FichasTaxonomicas::TABLA)->where('publicada', true)->whereNotNull('foto_ruta')->whereRaw("btrim(coalesce(descripcion, '')) <> ''")->count();
    echo json_encode(['creadas' => $creadas, 'conservadas' => count($entradas) - count($filas), 'publicadas_con_foto_y_texto' => $publicadas, 'taxones' => $taxones->count(), 'porcentaje' => round(100 * $publicadas / max(1, $taxones->count()), 2), 'sin_identidad_unica' => $sinIdentidad]).PHP_EOL;
    exit;
}
$prefijo = $argv[2] ?? ''; $salida = $argv[3] ?? '';
if (!$salida || !preg_match('#^[a-z0-9][a-z0-9/_-]{0,100}$#D', $prefijo) || str_contains($prefijo, '..')) throw new InvalidArgumentException('Indica prefijo remoto y archivo editorial de salida.');
$exportadas = []; $copiadas = [];
foreach ($entradas as $id => $entrada) {
    $ficha = $actuales[$id] ?? null;
    if (!$ficha || !$ficha->publicada || !$ficha->foto_ruta) continue;
    if (!isset($copiadas[$ficha->foto_sha256])) {
        $contenido = $fichas->contenidoFoto($ficha); $previo = config('deposit-storage.prefix');
        try {config(['deposit-storage.prefix' => $prefijo]); (new AlmacenamientoDepositos)->guardarContenido($ficha->foto_ruta, $contenido, 'image/webp');}
        finally {config(['deposit-storage.prefix' => $previo]);}
        $copiadas[$ficha->foto_sha256] = true;
        if (count($copiadas) % 10 === 0) {echo json_encode(['fotos_copiadas' => count($copiadas)]).PHP_EOL; flush();}
    }
    $exportadas[] = ['linaje' => $entrada['linaje'], 'descripcion' => $ficha->descripcion, 'foto_ruta' => $ficha->foto_ruta, 'foto_sha256' => $ficha->foto_sha256,
        'foto_alt' => $ficha->foto_alt, 'credito' => $ficha->credito, 'fuente_url' => $ficha->fuente_url, 'publicada' => true];
}
file_put_contents($salida, json_encode($exportadas, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode(['fichas_exportadas' => count($exportadas), 'fotos_compartidas' => count($copiadas), 'prefijo_destino' => $prefijo]).PHP_EOL;
