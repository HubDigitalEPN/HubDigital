<?php

use App\Models\User;
use App\Support\PublicacionExportacionCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;

uses(Tests\DatabaseFeatureTestCase::class);

test('la exportación externa exige revisión y todos los datos mínimos publicables', function (): void {
    $filo = (string) Str::uuid(); $orden = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Filo RBAC'],
        ['id' => $orden, 'padre_id' => $filo, 'rango' => 'orden', 'nombre_cientifico' => 'Orden RBAC'],
    ]);
    $base = ['taxon_id' => $orden, 'estado' => 'disponible', 'estado_revision' => 'confirmada',
        'occurrence_id' => 'RBAC-externo', 'located_at' => 'Colección EPN', 'preparations' => 'Etanol',
        'country' => 'Ecuador', 'state_province' => 'Pichincha', 'locality_name' => 'Quito',
        'decimal_latitude' => -0.2, 'decimal_longitude' => -78.5];
    $ids = [];
    foreach (['completo', 'occurrence_id', 'located_at', 'preparations', 'country', 'state_province', 'locality_name', 'decimal_latitude', 'decimal_longitude', 'taxon_id', 'estado_revision'] as $campo) {
        $id = (string) Str::uuid(); $ids[$campo] = $id;
        $fila = [...$base, 'id' => $id, 'codigo_catalogo' => 'RBAC-'.$id];
        if ($campo !== 'completo') $fila[$campo] = $campo === 'estado_revision' ? 'pendiente' : null;
        DB::table('taxonomia.especimenes')->insert($fila);
    }
    expect(PublicacionExportacionCatalogo::aplicar(DB::table('taxonomia.especimenes as te')->whereIn('te.id', $ids))->pluck('te.id')->all())->toBe([$ids['completo']]);
});

test('ningún usuario externo descarga el Excel interno aunque habiliten el autoservicio', function (): void {
    DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->update(['habilitado' => true]);
    $this->actingAs(User::factory()->depositante()->create());
    $this->getJson(route('portal.descarga', ['exportacion' => 'datos']))->assertForbidden();
    $this->getJson(route('portal.descarga', ['exportacion' => 'ipt']))->assertForbidden();
});

test('la hoja grande usa disco privado y limpia temporales incluso ante un fallo', function (): void {
    $directorio = storage_path('app/private/exportaciones');
    // tempnam usa solo tres caracteres del prefijo en Windows (manual PHP).
    $antes = glob($directorio.'/*') ?: [];
    $filas = (static function () use ($directorio, $antes): Generator {
        // Más de 192 MiB sin comprimir: excede el tmpfs completo configurado en OCI.
        for ($i = 0; $i < 16000; $i++) {
            if ($i === 1) expect(count(glob($directorio.'/*') ?: []))->toBe(count($antes) + 2);
            yield [str_repeat('Texto de una ficha. ', 700), (string) $i];
        }
    })();
    $bytes = app(PhpSpreadsheetGeneradorXlsxAdapter::class)->generar(['Notas', 'Código'], $filas);
    expect($bytes)->toStartWith('PK')->and(glob($directorio.'/*') ?: [])->toBe($antes);
    $fallo = (static function (): Generator { yield ['primera']; throw new RuntimeException('Fallo de origen'); })();
    expect(fn () => app(PhpSpreadsheetGeneradorXlsxAdapter::class)->generar(['Código'], $fallo))->toThrow(RuntimeException::class, 'Fallo de origen');
    expect(glob($directorio.'/*') ?: [])->toBe($antes);
});
