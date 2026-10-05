<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\DatabaseFeatureTestCase::class);

test('QA6 distribución agregada conserva reservas de provincia fecha y nombre y cuenta especies por grupo', function (): void {
    $filo = (string) Str::uuid();
    $a = (string) Str::uuid();
    $b = (string) Str::uuid();
    $c = (string) Str::uuid();
    $g = (string) Str::uuid();
    $sinFilo = (string) Str::uuid();
    $marca = 'Qadistrib'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24)));
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => $marca.' filo'],
        ['id' => $a, 'padre_id' => $filo, 'rango' => 'especie', 'nombre_cientifico' => $marca.' primera'],
        ['id' => $b, 'padre_id' => $filo, 'rango' => 'especie', 'nombre_cientifico' => $marca.' tercera'],
        ['id' => $c, 'padre_id' => $filo, 'rango' => 'especie', 'nombre_cientifico' => $marca.' segunda'],
        ['id' => $g, 'padre_id' => $filo, 'rango' => 'genero', 'nombre_cientifico' => $marca.' genero'],
        ['id' => $sinFilo, 'padre_id' => null, 'rango' => 'especie', 'nombre_cientifico' => $marca.' cuarta'],
    ]);
    $insertar = static function (string $taxon, string $provincia, string $fecha, array $permisos = []) use ($marca): string {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $id, 'codigo_catalogo' => 'QA6-DIST-'.Str::uuid(), 'occurrence_id' => 'QA6-DIST-'.Str::uuid(),
            'taxon_id' => $taxon, 'country' => 'Ecuador', 'state_province' => $provincia, 'fecha_colecta' => $fecha, 'colector' => $marca,
            'decimal_latitude' => -0.5, 'decimal_longitude' => -76.5,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(array_replace([
            'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
        ], $permisos));
        return $id;
    };
    $insertar($a, 'Nariño', '2001-01-01');
    $insertar($a, 'NARIÑO', '2002-01-01');
    $insertar($b, 'Narino', '2003-01-01');
    $insertar($c, 'Chocó', '1991-01-01');
    $insertar($a, 'Orellana', '2005-01-01', ['state_province_visible' => false]);
    $insertar($c, 'Choco', '1995-01-01', ['event_date_visible' => false]);
    $insertar($c, 'Carchi', '2011-01-01', ['scientific_name_visible' => false]);
    // El trigger productivo decide publicación por filo confirmado, incluso si
    // la entrada pide false. Este taxón válido sin filo queda realmente privado.
    $noPublicado = $insertar($sinFilo, 'Napo', '2001-01-01', ['publicado' => false]);
    $insertar($g, 'Esmeraldas', '1981-01-01');

    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $noPublicado)->value('publicado'))->toBeFalse();

    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $filo], false);
    expect($datos['riqueza'])->toBe([
        ['provincia' => 'Nariño', 'especies' => 2, 'registros' => 3],
        ['provincia' => 'Chocó', 'especies' => 1, 'registros' => 2],
        ['provincia' => 'Esmeraldas', 'especies' => 0, 'registros' => 1],
    ])->and($datos['decadas'])->toBe([
        ['decada' => 1980, 'especies' => 0, 'registros' => 1],
        ['decada' => 1990, 'especies' => 1, 'registros' => 1],
        ['decada' => 2000, 'especies' => 2, 'registros' => 4],
    ]);
    // Un filo exige identificación pública: el registro reservado solo aporta
    // cobertura de provincia/fecha cuando la selección no filtra su identidad.
    $sinIdentificacionExigida = app(PortalEstadisticas::class)->datosParaVista(['colector' => $marca], false);
    expect($sinIdentificacionExigida['riqueza'])->toBe([
        ['provincia' => 'Nariño', 'especies' => 2, 'registros' => 3],
        ['provincia' => 'Chocó', 'especies' => 1, 'registros' => 2],
        ['provincia' => 'Carchi', 'especies' => 0, 'registros' => 1],
        ['provincia' => 'Esmeraldas', 'especies' => 0, 'registros' => 1],
    ])->and($sinIdentificacionExigida['decadas'])->toBe([
        ['decada' => 1980, 'especies' => 0, 'registros' => 1],
        ['decada' => 1990, 'especies' => 1, 'registros' => 1],
        ['decada' => 2000, 'especies' => 2, 'registros' => 4],
        ['decada' => 2010, 'especies' => 0, 'registros' => 1],
    ]);
});
