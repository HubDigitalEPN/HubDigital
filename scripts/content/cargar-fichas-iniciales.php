<?php

declare(strict_types=1);

// Carga editorial solicitada: no ejecuta migraciones generales, suites ni publica código.
$raiz = getenv('HUBDIGITAL_BASE') ?: dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$manifest = $argv[1] ?? $raiz.'/database/seed-data/fichas-taxonomicas/manifest.json';
$modo = $argv[2] ?? '--listar';
$datos = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
if (!in_array($modo, ['--aplicar', '--listar', '--copiar-fotos-vm'], true)) throw new \InvalidArgumentException('Usa --listar, --aplicar o --copiar-fotos-vm.');
if ($modo !== '--listar') {
    if (!class_exists(\Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas::class)) require __DIR__.'/FichasTaxonomicas.php';
    \Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas::crearTabla();
    $fichas = app(\Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas::class);
}
$resultado = [];
foreach ($datos as $entrada) {
    $coincidencias = \Illuminate\Support\Facades\DB::table('taxonomia.taxones')
        ->where('nombre_cientifico', $entrada['nombre'])->whereIn('rango', $entrada['rangos'])->get(['id', 'nombre_cientifico', 'rango']);
    if ($coincidencias->count() !== 1) { $resultado[] = ['nombre' => $entrada['nombre'], 'estado' => $coincidencias->isEmpty() ? 'ausente' : 'identidad_ambigua']; continue; }
    $taxon = $coincidencias->first();
    if ($modo === '--listar') { $resultado[] = ['nombre' => $taxon->nombre_cientifico, 'id' => $taxon->id, 'rango' => $taxon->rango]; continue; }
    if ($modo === '--copiar-fotos-vm') {
        $prefijo = $argv[3] ?? '';
        if (!preg_match('#^[a-z0-9][a-z0-9/_-]{0,100}$#D', $prefijo) || str_contains($prefijo, '..')) throw new \InvalidArgumentException('Prefijo remoto no válido.');
        $ficha = $fichas->buscar($taxon->id);
        if (!$ficha?->foto_ruta) continue;
        $contenido = $fichas->contenidoFoto($ficha);
        $previo = config('deposit-storage.prefix');
        try {
            config(['deposit-storage.prefix' => $prefijo]);
            (new \Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos)->guardarContenido($ficha->foto_ruta, $contenido, 'image/webp');
        } finally { config(['deposit-storage.prefix' => $previo]); }
        $resultado[] = ['nombre' => $taxon->nombre_cientifico, 'rango' => $taxon->rango, 'descripcion' => $ficha->descripcion,
            'foto_ruta' => $ficha->foto_ruta, 'foto_sha256' => $ficha->foto_sha256, 'foto_alt' => $ficha->foto_alt,
            'credito' => $ficha->credito, 'fuente_url' => $ficha->fuente_url, 'publicada' => (bool) $ficha->publicada];
        continue;
    }
    if ($fichas->buscar($taxon->id)) { $resultado[] = ['nombre' => $entrada['nombre'], 'estado' => 'conservada']; continue; }
    if (!preg_match('/^[a-z0-9-]+\.webp$/D', $entrada['archivo'])) throw new \InvalidArgumentException('Nombre de foto no válido.');
    $binario = file_get_contents(dirname($manifest).'/'.$entrada['archivo']);
    if (!hash_equals($entrada['sha256'], hash('sha256', $binario))) throw new \RuntimeException('La foto no corresponde al manifiesto.');
    $foto = $fichas->prepararFoto($binario);
    try {
        $fichas->guardar($taxon->id, ['descripcion' => $entrada['descripcion'], 'foto_alt' => $entrada['alt'],
            'credito' => $entrada['credito'], 'fuente_url' => $entrada['fuente'], 'publicada' => true], $foto, false, null, '');
        $resultado[] = ['nombre' => $entrada['nombre'], 'estado' => 'creada', 'bytes' => $foto['bytes']];
    } catch (\Illuminate\Validation\ValidationException) { $resultado[] = ['nombre' => $entrada['nombre'], 'estado' => 'conservada']; }
}
if ($modo === '--copiar-fotos-vm') {
    if (empty($argv[4])) throw new \InvalidArgumentException('Indica el archivo de datos de salida.');
    file_put_contents($argv[4], json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL);
    echo json_encode(['fichas' => count($resultado), 'prefijo_destino' => $prefijo]).PHP_EOL;
} else echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
