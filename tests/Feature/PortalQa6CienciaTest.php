<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function qa6TaxonCiencia(string $nombre, string $rango, ?string $padre = null): string
{
    $id = DB::table('taxonomia.taxones')->where('nombre_cientifico', $nombre)->where('rango', $rango)->value('id');
    if ($id === null) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'nombre_cientifico' => $nombre, 'rango' => $rango, 'padre_id' => $padre]);
    } else DB::table('taxonomia.taxones')->where('id', $id)->update(['padre_id' => $padre]);
    return $id;
}

function qa6MigracionCiencia(): object
{
    return require base_path('Modules/CatalogoPublico/database/migrations/2026_10_03_000017_remediate_qa6_scientific_lineage_and_geography.php');
}

test('QA6-002 migración preserva fuente y corrige árbol, filtros y lectura de familia del mismo UUID', function (): void {
    $padre = null;
    foreach ([['Animalia', 'reino'], ['Arthropoda', 'phylum'], ['Insecta', 'clase'], ['Coleoptera', 'orden'],
        ['Chrysomelidae', 'familia'], ['Cassidinae', 'subfamilia'], ['Anastrepha', 'genero'], ['Anastrepha freidbergi', 'especie']] as [$nombre, $rango]) {
        $padre = qa6TaxonCiencia($nombre, $rango, $padre);
    }
    $id = (string) Str::uuid(); $colector = 'QA6-Ciencia-'.Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $id, 'taxon_id' => $padre, 'codigo_catalogo' => 'MEPN-INV-37369',
        'occurrence_id' => 'MEPN-INV-37369', 'old_code' => 'LOTE # 641', 'fila_origen_excel' => 31584,
        'colector' => $colector, 'taxon_verbatim' => 'Anastrepha freidbergi', 'taxonomic_notes' => 'Nota original',
        'country' => 'Ecuador', 'state_province' => 'Orellana', 'decimal_latitude' => -0.658, 'decimal_longitude' => -76.452]);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);

    qa6MigracionCiencia()->up();
    $proveedor = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroColector' => $colector]);
    expect($proveedor->consultaPublica($filtros, 'order', 'Diptera')->pluck('te.id')->all())->toBe([$id])
        ->and($proveedor->consultaPublica($filtros, 'order', 'Coleoptera')->count())->toBe(0)
        ->and($proveedor->consultaPublica($filtros, 'family', 'Tephritidae')->count())->toBe(1)
        ->and($proveedor->consultaPublica($filtros, 'family', 'Chrysomelidae')->count())->toBe(0)
        ->and(app(InventarioGestionColeccionEspecimenAdapter::class)->buscarPorOccurrenceId('MEPN-INV-37369')->family)->toBe('Tephritidae')
        ->and(DB::table('taxonomia.especimenes')->where('id', $id)->value('taxon_verbatim'))->toBe('Anastrepha freidbergi');
    $auditoria = DB::table('taxonomia.correcciones_cientificas')->where('hallazgo', 'QA6-002')->where('entidad', 'especimen')->where('entidad_id', $id)->first();
    $original = json_decode($auditoria->original, true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($original['linaje'], 'nombre_cientifico'))->toContain('Coleoptera', 'Chrysomelidae', 'Cassidinae')
        ->and($original['taxonomic_notes'])->toBe('Nota original');
    $primeraNota = DB::table('taxonomia.especimenes')->where('id', $id)->value('taxonomic_notes');
    qa6MigracionCiencia()->up();
    expect(DB::table('taxonomia.especimenes')->where('id', $id)->value('taxonomic_notes'))->toBe($primeraNota)
        ->and(DB::table('taxonomia.correcciones_cientificas')->where('hallazgo', 'QA6-002')->where('entidad', 'especimen')->where('entidad_id', $id)->count())->toBe(1);
});

test('QA6-003 migración cuarentena sólo fuente exacta, preserva par y asociación INEC y exige curación', function (): void {
    // El trigger de publicación exige un filo confirmado incluso si el fixture
    // solicita publicado=true. Ambos registros deben ser públicos antes de curar.
    $filo = qa6TaxonCiencia('Arthropoda', 'phylum');
    $taxon = qa6TaxonCiencia('Camponotus cruentatus', 'especie', $filo);
    $localidad = (string) Str::uuid();
    DB::table('taxonomia.localidades')->insert(['id' => $localidad, 'nombre_canonico' => 'SIERRA NEVADA', 'rango' => 'localidad', 'country' => 'Ecuador', 'codigo_inec' => '110150999030003', 'referencia_inec' => 'referencia del contexto']);
    $colector = 'QA6-Ragua-'.Str::uuid(); $ids = [];
    foreach ([25342, 999999] as $fila) {
        $ids[] = $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'taxon_id' => $taxon, 'codigo_catalogo' => 'QA6-'.$fila,
            'occurrence_id' => 'MEPN-INV-30203', 'old_code' => '787', 'fila_origen_excel' => $fila,
            'colector' => $colector, 'country' => 'Ecuador', 'state_province' => 'Granada', 'localidad_id' => $localidad,
            'localidad_verbatim' => 'Sierra Nevada, Puerto de la Ragua', 'decimal_latitude' => -4.0226841,
            'decimal_longitude' => -79.194422, 'motivo_revision' => 'Observación previa', 'specimen_notes' => 'Etiqueta conservada']);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
    }
    $proveedor = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroColector' => $colector]);
    expect($proveedor->consultaPublica($filtros)->orderBy('te.fila_origen_excel')->pluck('te.id')->all())->toBe($ids);
    qa6MigracionCiencia()->up();
    $curado = DB::table('taxonomia.especimenes')->where('id', $ids[0])->first();
    expect($curado->decimal_latitude)->toBeNull()->and($curado->decimal_longitude)->toBeNull()->and($curado->localidad_id)->toBeNull()
        ->and($curado->localidad_verbatim)->toBe('Sierra Nevada, Puerto de la Ragua')
        ->and($curado->coord_verbatim)->toBe('-4.0226841 / -79.194422')->and($curado->estado_revision)->toBe('pendiente')
        ->and($curado->motivo_revision)->toContain('Observación previa', 'QA6-003')
        ->and($curado->specimen_notes)->toContain('Etiqueta conservada', 'No se ha confirmado el lugar de colecta');
    expect($proveedor->consultaPublica($filtros)->pluck('te.id')->all())->toBe([$ids[1]])
        ->and(DB::table('taxonomia.especimenes')->where('id', $ids[1])->value('localidad_id'))->toBe($localidad);
    $auditoria = DB::table('taxonomia.correcciones_cientificas')->where('hallazgo', 'QA6-003')->where('entidad_id', $ids[0])->first();
    $original = json_decode($auditoria->original, true, flags: JSON_THROW_ON_ERROR);
    expect((float) $original['decimal_latitude'])->toBe(-4.0226841)->and((float) $original['decimal_longitude'])->toBe(-79.194422)
        ->and($original['referencia_localidad']['codigo_inec'])->toBe('110150999030003')->and($auditoria->estado)->toBe('pendiente');
    qa6MigracionCiencia()->up();
    expect(DB::table('taxonomia.correcciones_cientificas')->where('hallazgo', 'QA6-003')->where('entidad_id', $ids[0])->count())->toBe(1);
});
