<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!$app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql') throw new RuntimeException('El fixture del navegador requiere PostgreSQL en testing.');
[$script, $accion, $token, $archivo] = $argv;
if (!preg_match('/^[a-f0-9]{16}$/', $token)) throw new RuntimeException('Identidad de fixture inválida.');
$prefijo = 'QA-BROWSER-'.$token.'-';
if ($accion === 'limpiar') {
    if (!is_file($archivo)) exit(0);
    $datos = json_decode(file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR);
    if (($datos['token'] ?? null) !== $token) throw new RuntimeException('El fixture no pertenece a esta ejecución.');
    DB::transaction(static function () use ($prefijo, $datos): void {
        $ids = DB::table('taxonomia.especimenes')->where('occurrence_id', 'LIKE', $prefijo.'%')->pluck('id');
        DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $ids)->delete();
        DB::table('taxonomia.especimenes')->whereIn('id', $ids)->delete();
        foreach (array_reverse($datos['taxones']) as $id) DB::table('taxonomia.taxones')->where('id', $id)->delete();
        DB::table('taxonomia.muestras_colecta')->whereIn('id', $datos['muestras'])->delete();
    });
    exit(0);
}
if ($accion !== 'crear') throw new RuntimeException('Acción de fixture inválida.');
$datos = DB::transaction(static function () use ($token, $prefijo, $archivo): array {
    $taxones = $filos = $familias = $especies = $muestras = [];
    foreach (range(0, 1) as $i) {
        // La base local conserva la colección: reutilizar el filo real respeta
        // la unicidad del nombre sin modificarlo ni eliminarlo al limpiar.
        $filoExistente = $i ? DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Nematomorpha')->where('rango', 'phylum')->value('id') : null;
        $filos[$i] = $filoExistente ?: (string) Str::uuid(); $familias[$i] = (string) Str::uuid(); $especies[$i] = (string) Str::uuid();
        $reino = (string) Str::uuid(); $clase = (string) Str::uuid(); $orden = (string) Str::uuid(); $suborden = (string) Str::uuid();
        $taxones = [...$taxones, ...($filoExistente ? [] : [$reino, $filos[$i]]), $clase, $orden, $suborden, $familias[$i], $especies[$i]];
        $nuevos = [
            ['id' => $clase, 'padre_id' => $filos[$i], 'rango' => 'clase', 'nombre_cientifico' => 'Browserclass'.$token.$i],
            ['id' => $orden, 'padre_id' => $clase, 'rango' => 'orden', 'nombre_cientifico' => 'Browserorder'.$token.$i],
            ['id' => $suborden, 'padre_id' => $orden, 'rango' => 'suborden', 'nombre_cientifico' => 'Browsersuborder'.$token.$i],
            ['id' => $familias[$i], 'padre_id' => $suborden, 'rango' => 'familia', 'nombre_cientifico' => 'Browseridae'.$token.$i],
            ['id' => $especies[$i], 'padre_id' => $familias[$i], 'rango' => 'especie', 'nombre_cientifico' => 'Browserobius '.$token.' '.($i ? 'beta' : 'alfa')],
        ];
        if (!$filoExistente) $nuevos = [
            ['id' => $reino, 'padre_id' => null, 'rango' => 'reino', 'nombre_cientifico' => 'BrowserAnimalia'.$token.$i],
            ['id' => $filos[$i], 'padre_id' => $reino, 'rango' => 'phylum', 'nombre_cientifico' => $i ? 'Nematomorpha' : 'FiloBrowser'.$token],
            ...$nuevos,
        ];
        DB::table('taxonomia.taxones')->insert($nuevos);
        $muestras[$i] = (string) Str::uuid();
        DB::table('taxonomia.muestras_colecta')->insert(['id' => $muestras[$i], 'sampling_protocol' => $i ? 'Nebulización de dosel' : 'Red entomológica']);
    }
    $codigos = $ids = [];
    foreach (range(0, 17) as $i) {
        $ids[$i] = (string) Str::uuid(); $codigos[$i] = $prefijo.($i + 1);
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $especies[$i === 17 ? 1 : 0], 'muestra_id' => $muestras[$i % 2],
            'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i], 'fila_origen_excel' => 200000 + $i,
            'state_province' => $i < 12 ? 'Orellana' : 'Pichincha', 'country' => 'Ecuador', 'colector' => 'Fixture del navegador',
            'localidad' => $i < 12 ? 'Lugar Browser Uno' : 'Lugar Browser Dos', 'locality_name' => $i < 12 ? 'Lugar Browser Uno' : 'Lugar Browser Dos',
            'decimal_latitude' => $i < 12 ? -.273 : ($i === 17 ? -2 : -1), 'decimal_longitude' => $i < 12 ? -79.024 : ($i === 17 ? -77.5 : -78.5),
            'fecha_colecta' => $i % 2 ? '2011-02-10' : '2001-01-10', 'elevation_min_m' => $i % 2 ? 600 : 100, 'elevation_max_m' => $i % 2 ? 600 : 100,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    $taxonBusqueda = 'Browserobius '.$token;
    $datos = compact('token', 'prefijo', 'taxones', 'filos', 'familias', 'especies', 'muestras', 'codigos', 'ids', 'taxonBusqueda');
    if (file_put_contents($archivo, json_encode($datos, JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('No se pudo registrar el fixture para limpiarlo.');
    return $datos;
});
echo json_encode($datos, JSON_THROW_ON_ERROR);
