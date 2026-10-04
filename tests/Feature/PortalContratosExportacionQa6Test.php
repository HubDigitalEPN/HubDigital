<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EnlaceSeleccionCatalogo;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(Tests\DatabaseFeatureTestCase::class);

function contratoExportacionFixtureQa6(): array
{
    $filo = (string) Str::uuid();
    $especie = (string) Str::uuid();
    $nombre = 'Exportqa'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24))).' contrato';
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'nombre_cientifico' => $nombre.' filo', 'rango' => 'phylum'],
        ['id' => $especie, 'padre_id' => $filo, 'nombre_cientifico' => $nombre, 'rango' => 'especie'],
    ]);
    $ids = $codigos = [];
    $nota = 'Coordenadas recuperadas del Excel; precisión pendiente de revisión.';
    foreach ([null, 'holotype', 'Estado curatorial no catalogado'] as $i => $tipo) {
        $ids[$i] = (string) Str::uuid();
        $codigos[$i] = 'QA6-CONTRATO-'.Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $especie,
            'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i],
            'fila_origen_excel' => $i + 1, 'state_province' => 'Orellana', 'country' => 'Ecuador',
            'localidad' => 'Localidad QA6', 'localidad_verbatim' => 'Localidad original QA6',
            'locality_name' => 'Localidad QA6', 'fecha_colecta' => '2025-01-10',
            'decimal_latitude' => -0.658, 'decimal_longitude' => -76.452,
            'lat_lon_max_error' => $i === 1 ? 'Referencia aproximada' : $nota,
            'type_status' => $tipo, 'type_notes' => $i === 1 ? 'Tipo documentado en fixture' : null,
            'disposition' => $i === 0 ? 'in_collection' : ($i === 1 ? 'in collection' : null),
            'occurrence_status' => 'present',
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true,
        ]);
    }
    return compact('filo', 'especie', 'nombre', 'ids', 'codigos', 'nota');
}

test('QA6 tipo y disposición conservan la población entre filtros públicos ficha y CSV XLSX', function (): void {
    $f = contratoExportacionFixtureQa6();
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $base = ['filtroFiloId' => $f['filo']];
    $total = static fn (array $extras): int => $repo->consultaPublica(FiltrosBusqueda::desde($base + $extras))->count();
    expect($total([]))->toBe(3)
        ->and($total(['filtroTipo' => 'in_collection']))->toBe(0)
        ->and($total(['filtroDisposicion' => 'in_collection']))->toBe(2)
        ->and($total(['filtroDisposicion' => 'En la colección']))->toBe(2)
        ->and($total(['filtroTipo' => 'Holotipo', 'filtroDisposicion' => 'En la colección']))->toBe(1);

    $portal = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo'], 'fd' => 'En la colección'])
        ->test(PortalCatalogo::class)->assertSet('filtroDisposicion', 'En la colección')
        ->assertViewHas('totalRegistrosVista', 2)->assertSee('En la colección');
    $registros = $portal->viewData('registrosVista');
    $porId = array_column($registros, null, 'especimen_id');
    expect($porId[$f['ids'][0]]->type_status)->toBeNull()
        ->and($porId[$f['ids'][0]]->disposition)->toBe('in_collection')
        ->and($porId[$f['ids'][1]]->type_status)->toBe('holotype');
    $respuesta = $portal->instance()->descargarResultados($repo);
    ob_start();
    try {
        $respuesta->sendContent();
        $csv = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
    $stream = fopen('php://memory', 'w+');
    try {
        fwrite($stream, substr($csv, 3)); rewind($stream);
        $cabecera = fgetcsv($stream, null, ';', '"', '');
        $filas = [];
        while (($fila = fgetcsv($stream, null, ';', '"', '')) !== false) $filas[] = array_combine($cabecera, $fila);
    } finally {
        fclose($stream);
    }
    expect($cabecera)->toBe(PerfilExportacionPublica::ENCABEZADOS_CSV)->and($filas)->toHaveCount(2);
    $csvPorCodigo = array_column($filas, null, 'N.º catálogo');
    expect($csvPorCodigo[$f['codigos'][0]]['Tipo'])->toBe('')
        ->and($csvPorCodigo[$f['codigos'][0]]['Disposición'])->toBe('in_collection')
        ->and($csvPorCodigo[$f['codigos'][0]]['Precisión'])->toBe($f['nota'])
        ->and($csvPorCodigo[$f['codigos'][1]]['Tipo'])->toBe('holotype')
        ->and($csvPorCodigo[$f['codigos'][0]]['Perfil de exportación'])->toBe(PerfilExportacionPublica::IDENTIFICADOR);

    $ids = $repo->consultaPublica(FiltrosBusqueda::desde($base + ['filtroDisposicion' => 'in_collection']))
        ->orderBy('te.fila_origen_excel')->pluck('te.id')->all();
    $xlsx = app(ExportarRegistrosEspecimenesHandler::class)->handle(new ExportarRegistrosEspecimenesInput($f['nombre'], $ids));
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa6-export-');
    $libro = null;
    try {
        file_put_contents($archivo, $xlsx->contenidoXlsx);
        $libro = IOFactory::load($archivo);
        $valores = $libro->getActiveSheet()->toArray(null, false, false);
        expect($valores)->toHaveCount(3)->and($valores[0])->toBe(PerfilExportacionPublica::ENCABEZADOS_XLSX);
        foreach (array_slice($valores, 1) as $valoresFila) {
            $fila = array_combine($valores[0], array_map(static fn ($v): string => (string) ($v ?? ''), $valoresFila));
            $csvFila = $csvPorCodigo[$fila['occurrenceID']];
            expect($fila['typeStatus'])->toBe($csvFila['Tipo'])
                ->and($fila['disposition'])->toBe($csvFila['Disposición'])
                ->and($fila['georeferenceRemarks'])->toBe($csvFila['Precisión'])
                ->and($fila['coordinateUncertaintyInMeters'])->toBe('')
                ->and($fila['exportProfile'])->toBe($csvFila['Perfil de exportación']);
        }
    } finally {
        if ($libro !== null) $libro->disconnectWorksheets();
        unlink($archivo);
    }

    $seleccion = SeleccionPaginaChat::desde(['fd' => 'En la colección', 'fph' => $f['filo']]);
    expect($seleccion)->not->toBeNull()->and($seleccion->filtros->disposicion)->toBe('in_collection')
        ->and(EnlaceSeleccionCatalogo::parametros($seleccion->filtros, '', '')['fd'])->toBe('in_collection');
});

test('QA6 tipo reservado bloquea también filtros por disposición y datos de su exportación', function (): void {
    $f = contratoExportacionFixtureQa6();
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])
        ->update(['type_status_visible' => false]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroFiloId' => $f['filo'], 'filtroDisposicion' => 'in_collection']);
    expect($repo->consultaPublica($filtros)->pluck('te.id')->all())->toBe([$f['ids'][1]]);
});
