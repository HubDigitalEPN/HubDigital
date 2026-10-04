<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\DatabaseFeatureTestCase::class);

function cartografiaRealFixture(): array
{
    $filo = (string) Str::uuid();
    $prefijo = 'Carto'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24)));
    $taxones = [(string) Str::uuid(), (string) Str::uuid()];
    DB::table('taxonomia.taxones')->insert(['id' => $filo, 'nombre_cientifico' => $prefijo, 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026]);
    foreach ($taxones as $i => $id) DB::table('taxonomia.taxones')->insert([
        'id' => $id, 'padre_id' => $filo, 'nombre_cientifico' => $prefijo.' '.($i ? 'beta' : 'alfa'), 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
    ]);
    $muestra = (string) Str::uuid();
    DB::table('taxonomia.muestras_colecta')->insert(['id' => $muestra, 'sampling_protocol' => 'Red '.$prefijo]);
    $ids = $codigos = [];
    foreach ([0, 1, 2] as $i) {
        $ids[$i] = (string) Str::uuid();
        $codigos[$i] = 'QA-CARTO-'.Str::upper(Str::random(10));
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i],
            'taxon_id' => $taxones[$i === 2 ? 1 : 0], 'muestra_id' => $muestra,
            'localidad' => $prefijo, 'state_province' => $i === 2 ? 'Napo' : 'Pichincha',
            'fecha_colecta' => '2025-01-10', 'colector' => 'QA',
            'decimal_latitude' => $i === 2 ? -2.5 : -0.25,
            'decimal_longitude' => $i === 2 ? -76.25 : -78.5,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filo', 'prefijo', 'taxones', 'ids', 'codigos', 'muestra');
}

test('QA3-001 las tarjetas usan UUID y excluyen otro taxón con el mismo código público', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['occurrence_id' => $f['codigos'][0]]);
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
        ->test(PortalCatalogo::class)->assertViewHas('totalEspecimenes', 2)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 2 && array_diff(array_column($filas, 'especimen_id'), [$f['ids'][0], $f['ids'][1]]) === []);
    $componente->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 2)
        ->assertViewHas('registrosVista', fn ($filas) => array_column($filas, 'scientific_name') === [$f['prefijo'].' alfa', $f['prefijo'].' alfa']);
    $componente->call('cambiarVista', 'mapa')->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2);
});

test('tarjetas y tabla cargan seis UUID por página sin duplicarlos ni perder el resto', function (): void {
    $f = cartografiaRealFixture();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    for ($i = 0; $i < 23; $i++) {
        $id = (string) Str::uuid(); $codigo = 'QA-PAGINA-'.Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($original, ['id' => $id, 'occurrence_id' => $codigo, 'codigo_catalogo' => $codigo]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
    }
    foreach (['tarjetas' => 'especimenes', 'registros' => 'registrosVista'] as $vista => $clave) {
        $componente = Livewire::withQueryParams(['vista' => $vista, 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])->test(PortalCatalogo::class)
            ->assertViewHas('totalRegistrosVista', 25)->assertViewHas('ultimaPagina', 5)->assertViewHas($clave, fn ($filas) => count($filas) === 6);
        $vistos = [];
        foreach (range(1, 5) as $pagina) {
            $componente->call('cambiarPagina', $pagina)->assertViewHas($clave, fn ($filas) => count($filas) === ($pagina === 5 ? 1 : 6));
            $actuales = array_column($componente->viewData($clave), 'especimen_id');
            expect(array_intersect($vistos, $actuales))->toBe([]);
            $vistos = [...$vistos, ...$actuales];
        }
        expect(count(array_unique($vistos)))->toBe(25);
    }
});

test('el rail pagina hermanos y sus conteos en el padre público sin ampliar los registros de la ficha', function (): void {
    $f = cartografiaRealFixture();
    $familia = (string) Str::uuid(); $generoId = (string) Str::uuid(); $otroGeneroId = (string) Str::uuid();
    $genero = $f['prefijo'].'Genus'; $familiaNombre = $f['prefijo'].'Familia';
    $principal = $genero.' carinulata'; $hermana = $genero.' crenata';
    foreach ([[$familia, $f['filo'], $familiaNombre, 'familia'], [$generoId, $familia, $genero, 'genero'],
        [$otroGeneroId, $familia, $f['prefijo'].'OtroGenero', 'genero']] as [$id, $padre, $nombre, $rango]) {
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'nombre_cientifico' => $nombre, 'rango' => $rango]);
    }
    foreach ($f['taxones'] as $i => $id) DB::table('taxonomia.taxones')->where('id', $id)->update([
        'padre_id' => $generoId, 'nombre_cientifico' => $i === 0 ? $principal : $hermana,
    ]);
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $insertar = static function (string $taxonId, string $colector = 'QA', array $flags = []) use ($original): string {
        $id = (string) Str::uuid(); $codigo = 'QA-HERMANOS-'.Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($original, [
            'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo, 'taxon_id' => $taxonId, 'colector' => $colector,
        ]));
        DB::table('divulgacion.especimenes_divulgables')->insert(array_replace([
            'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
        ], $flags));
        return $id;
    };
    $idsPrincipal = [$f['ids'][0], $f['ids'][1]];
    for ($i = 0; $i < 11; $i++) $idsPrincipal[] = $insertar($f['taxones'][0]);
    $insertar($f['taxones'][1], 'OTRO COLECTOR');
    $insertar($f['taxones'][1], 'QA', ['genus_visible' => false]);
    for ($i = 0; $i < 13; $i++) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $generoId,
            'nombre_cientifico' => $genero.' zeta'.sprintf('%02d', $i), 'rango' => 'especie']);
        $insertar($id);
    }
    $especieReservada = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $especieReservada, 'padre_id' => $generoId,
        'nombre_cientifico' => $genero.' reservada', 'rango' => 'especie']);
    $insertar($especieReservada, 'QA', ['scientific_name_visible' => false]);
    $especieOtroGenero = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $especieOtroGenero, 'padre_id' => $otroGeneroId,
        'nombre_cientifico' => $f['prefijo'].'OtroGenero vecina', 'rango' => 'especie']);
    $insertar($especieOtroGenero);
    $insertar($especieOtroGenero, 'QA', ['family_visible' => false]);
    $generoReservado = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $generoReservado, 'padre_id' => $familia,
        'nombre_cientifico' => $f['prefijo'].'GeneroReservado', 'rango' => 'genero']);
    $insertar($generoReservado, 'QA', ['genus_visible' => false]);

    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $principal])
        ->test(PortalCatalogo::class)->assertViewHas('totalEspecimenes', 13)
        ->assertViewHas('totalHermanos', 14)->assertViewHas('ultimaPaginaHermanos', 3)
        ->assertViewHas('hermanos', fn ($nodos) => count($nodos) === 6 && $nodos[0]['taxon'] === $hermana && $nodos[0]['total'] === 2)
        ->assertViewHas('conteos', fn ($conteos) => ! isset($conteos['species:'.$hermana]))
        ->assertViewHas('especimenes', fn ($filas) => array_diff(array_column($filas, 'especimen_id'), $idsPrincipal) === [])
        ->assertDontSee($genero.' reservada')->assertDontSee($f['prefijo'].'OtroGenero vecina');
    $componente->call('cambiarPagina', 3)->assertViewHas('paginaActual', 3)->assertViewHas('especimenes', fn ($filas) => count($filas) === 1);
    $idPaginaPrincipal = $componente->viewData('especimenes')[0]->especimen_id;
    $primeraHermanos = array_column($componente->viewData('hermanos'), 'taxon');
    $componente->call('cambiarPaginaHermanos', 2)->assertViewHas('paginaHermanosActual', 2)
        ->assertViewHas('hermanos', fn ($nodos) => count($nodos) === 6)
        ->assertViewHas('paginaActual', 3)->assertViewHas('especimenes', fn ($filas) => count($filas) === 1 && $filas[0]->especimen_id === $idPaginaPrincipal);
    $intermediaHermanos = array_column($componente->viewData('hermanos'), 'taxon');
    $componente->call('cambiarPaginaHermanos', 3)->assertViewHas('paginaHermanosActual', 3)
        ->assertViewHas('hermanos', fn ($nodos) => count($nodos) === 2 && array_intersect($primeraHermanos, array_column($nodos, 'taxon')) === [])
        ->assertViewHas('paginaActual', 3)->assertViewHas('especimenes', fn ($filas) => count($filas) === 1 && $filas[0]->especimen_id === $idPaginaPrincipal);
    expect(array_intersect($primeraHermanos, $intermediaHermanos))->toBe([])
        ->and(count(array_unique([...$primeraHermanos, ...$intermediaHermanos, ...array_column($componente->viewData('hermanos'), 'taxon')])))->toBe(14);
    $componente->set('filtroColector', 'QA')->assertViewHas('paginaHermanosActual', 1)
        ->assertViewHas('hermanos', fn ($nodos) => $nodos[0]['taxon'] === $hermana && $nodos[0]['total'] === 1)
        ->assertViewHas('totalEspecimenes', 13);
    $componente->call('navegar', 'species', $hermana)->assertViewHas('paginaActual', 1)->assertViewHas('paginaHermanosActual', 1)
        ->assertViewHas('totalEspecimenes', 2)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 2 && array_column($filas, 'scientific_name') === [$hermana, $hermana])
        ->assertViewHas('hermanos', fn ($nodos) => $nodos[0]['taxon'] === $principal && $nodos[0]['total'] === 13);
    $componente->call('navegar', 'genus', $genero)->assertViewHas('hermanos', fn ($nodos) => count($nodos) === 1
        && $nodos[0]['taxon'] === $f['prefijo'].'OtroGenero' && $nodos[0]['total'] === 1)
        ->assertDontSee($f['prefijo'].'GeneroReservado');
});

test('el mapa conserva coordenadas exactas y un punto vecino no entra en el modal', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => -0.2561234, 'decimal_longitude' => -78.5134567]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => -0.2641234, 'decimal_longitude' => -78.5194567]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect($datos['mapa'])->toHaveCount(3)->and(array_column($datos['mapa'], 'lat'))->toContain(-0.2561234, -0.2641234);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.2561234, -78.5134567)->call('cambiarVistaCelda', 'registros');
    expect($componente->instance()->detalleCelda['total'])->toBe(1)
        ->and(array_column($componente->instance()->detalleCelda['registros'], 'especimen_id'))->toBe([$f['ids'][0]]);
});

test('el icono registros del modal muestra toda la ubicación en páginas de seis con campos y permisos completos', function (): void {
    $f = cartografiaRealFixture();
    $latitud = -0.2561234; $longitud = -78.5134567;
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])->update([
        'decimal_latitude' => $latitud, 'decimal_longitude' => $longitud,
    ]);
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $idsEsperados = [$f['ids'][0], $f['ids'][1], $f['ids'][2]];
    $insertar = static function (array $cambios, bool $publicado = true) use ($original): string {
        $id = (string) Str::uuid(); $codigo = 'QA-TABLA-MODAL-'.Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($original, [
            'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
        ], $cambios));
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => $publicado,
        ]);
        return $id;
    };
    for ($i = 0; $i < 11; $i++) $idsEsperados[] = $insertar(['fila_origen_excel' => $i + 3]);
    // La especie beta del mismo punto también pertenece a la rejilla aunque
    // el árbol conserve seleccionada alfa. Los demás filtros siguen vigentes.
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update([
        'decimal_latitude' => $latitud, 'decimal_longitude' => $longitud, 'state_province' => 'Pichincha',
    ]);
    $taxonSinFilo = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        'id' => $taxonSinFilo, 'nombre_cientifico' => $f['prefijo'].' sin publicación', 'rango' => 'especie',
    ]);
    // La publicación se deriva por trigger de un filo confirmado, no del valor insertado.
    $noPublicado = $insertar(['taxon_id' => $taxonSinFilo], false);
    expect((bool) DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $noPublicado)->value('publicado'))->toBeFalse();
    $excluidos = [$insertar(['state_province' => 'Napo']),
        $insertar(['decimal_latitude' => $latitud + 0.0001]), $noPublicado];
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update([
        'fila_origen_excel' => 1, 'colector' => 'COLECTOR-PRIVADO-TABLA', 'specimen_notes' => 'NOTA-PRIVADA-TABLA',
        'locality_name' => 'LOCALIDAD-PRIVADA-TABLA', 'localidad_verbatim' => 'ORIGINAL-PRIVADO-TABLA',
        'elevation_min_m' => 9123, 'elevation_max_m' => 9234,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update([
        'recorded_by_visible' => false, 'specimen_notes_visible' => false,
        'locality_name_visible' => false, 'elevation_visible' => false, 'occurrence_id_visible' => false,
    ]);
    $notaPublica = '<script>alert("nota pública")</script>'."\n".'Río Ñambí';
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update([
        'fila_origen_excel' => 2, 'country' => 'Ecuador', 'locality_name' => 'Localidad registrada Ñambí',
        'localidad_verbatim' => 'Localidad original Ñambí', 'lat_lon_max_error' => 'Referencia GPS original',
        'elevation_min_m' => 0, 'elevation_max_m' => 120, 'individual_count' => 0,
        'type_status' => 'paratype', 'disposition' => 'in_collection', 'specimen_notes' => $notaPublica, 'occurrence_status' => 'present',
        'caste' => 'obrera', 'life_stage' => 'adult',
    ]);
    foreach ([0, 1] as $i) DB::table('divulgacion.imagenes_taxonomicas')->insert([
        'id' => (string) Str::uuid(), 'occurrence_id' => $f['codigos'][$i],
        'ruta' => 'divulgacion/imagenes/tabla-modal-'.Str::uuid().'.jpg', 'disco' => 'r2',
        'nombre_original' => $i === 0 ? 'foto-codigo-reservado.jpg' : 'foto-publica-tabla.jpg',
        'autor_nombre' => 'QA', 'autor_apellido' => 'Portal', 'autor_nombre_completo' => 'QA Portal',
    ]);

    $leerDom = static function (string $html): DOMXPath {
        $anterior = libxml_use_internal_errors(true);
        try {
            $documento = new DOMDocument;
            $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
            return new DOMXPath($documento);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    };
    $celdasFila = static function (DOMXPath $dom, string $id): array {
        $titulos = array_map(fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($dom->query('//table[@class="atlas-record-table"]/thead/tr/th')));
        $celdas = $dom->query('//table[@class="atlas-record-table"]/tbody/tr[@*[name()="wire:key"]="registro-tabla-mapa-'.$id.'"]/*');
        return array_combine($titulos, array_map(fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($celdas)));
    };
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo'], 'fprov' => 'Pichincha'])
        ->test(PortalCatalogo::class)->call('abrirCelda', $latitud, $longitud)
        ->call('navegarCelda', $f['taxones'][0])->call('cambiarVistaCelda', 'registros')
        ->assertSet('filtroFiloId', $f['filo'])->assertSet('filtroProvincia', 'Pichincha')
        ->assertSet('paginaCelda', 1)->assertSet('vistaCelda', 'registros');
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['total'])->toBe(14)->and($detalle['ultima'])->toBe(3)
        ->and($detalle['seleccionado']['taxon_id'])->toBe($f['taxones'][0]);
    $dom = $leerDom($componente->html());
    $tabla = $dom->query('//dialog//table[@class="atlas-record-table"]')->item(0);
    expect($dom->query('//dialog//table[@class="atlas-record-table"]/tbody/tr')->length)->toBe(6)
        ->and($dom->query('//dialog//article[contains(@class,"atlas-record-card")]')->length)->toBe(0)
        ->and($dom->query('//dialog//div[@class="collection-view-switch"]/button')->length)->toBe(2)
        ->and($dom->query('//dialog//div[@class="atlas-record-table-scroll" and @role="region" and @tabindex="0"]')->length)->toBe(1);
    foreach (['COLECTOR-PRIVADO-TABLA', 'NOTA-PRIVADA-TABLA', 'LOCALIDAD-PRIVADA-TABLA', 'ORIGINAL-PRIVADO-TABLA', $f['codigos'][0]] as $reservado) {
        expect($tabla->textContent)->not->toContain($reservado);
    }
    $publicos = $celdasFila($dom, $f['ids'][1]);
    expect($publicos)->toMatchArray([
        'Código de catálogo' => $f['codigos'][1], 'Identificación científica' => $f['prefijo'].' alfa',
        'Fecha original' => '2025-01-10', 'Colector' => 'QA', 'País' => 'Ecuador', 'Provincia' => 'Pichincha',
        'Localidad registrada' => 'Localidad registrada Ñambí', 'Localidad original' => 'Localidad original Ñambí',
        'Latitud' => (string) $latitud, 'Longitud' => (string) $longitud,
        'Referencia de coordenadas' => 'Referencia GPS original', 'Elevación mín. (m)' => '0', 'Elevación máx. (m)' => '120',
        'Método de colecta' => 'Red '.$f['prefijo'], 'Individuos' => '0', 'Condición de tipo' => 'Paratipo', 'Disposición' => 'En la colección',
        'Notas del espécimen' => $notaPublica, 'Estado' => 'Presente', 'Casta' => 'obrera', 'Estadio' => 'Adulto',
    ])->toHaveKeys(['Localidad INEC', 'Referencia INEC', 'Notas de tipo', 'Fotografías publicadas']);
    $privados = $celdasFila($dom, $f['ids'][0]);
    expect($privados['Colector'])->toBe('—')->and($privados['Localidad registrada'])->toBe('—')
        ->and($privados['Notas del espécimen'])->toBe('—')->and($privados['Elevación mín. (m)'])->toBe('—')
        ->and($dom->query('//table[@class="atlas-record-table"]//script')->length)->toBe(0)
        ->and($dom->query('//table[@class="atlas-record-table"]//a[@title="foto-publica-tabla.jpg"]')->length)->toBe(1)
        ->and($dom->query('//table[@class="atlas-record-table"]//a[@title="foto-codigo-reservado.jpg"]')->length)->toBe(0);
    $primera = array_column($detalle['registros'], 'especimen_id');
    $componente->call('paginarCelda', 2)->assertSet('paginaCelda', 2)->assertSet('pagina', 1);
    $segunda = array_column($componente->instance()->detalleCelda['registros'], 'especimen_id');
    expect($leerDom($componente->html())->query('//dialog//table[@class="atlas-record-table"]/tbody/tr')->length)->toBe(6);
    $componente->call('paginarCelda', 3)->assertSet('paginaCelda', 3);
    $tercera = array_column($componente->instance()->detalleCelda['registros'], 'especimen_id');
    expect($leerDom($componente->html())->query('//dialog//table[@class="atlas-record-table"]/tbody/tr')->length)->toBe(2)
        ->and(array_intersect($primera, $segunda))->toBe([])
        ->and(array_intersect([...$primera, ...$segunda], $tercera))->toBe([])
        ->and(array_diff($idsEsperados, [...$primera, ...$segunda, ...$tercera]))->toBe([])
        ->and([...$primera, ...$segunda, ...$tercera])->toContain($f['ids'][2])
        ->and(array_intersect($excluidos, [...$primera, ...$segunda, ...$tercera]))->toBe([]);
    $componente->call('paginarCelda', 999)->assertSet('paginaCelda', 3)
        ->call('cambiarVistaCelda', 'grupos')->assertSet('vistaCelda', 'grupos')->assertSet('paginaCelda', 1);
    $domArbol = $leerDom($componente->html());
    expect($domArbol->query('//dialog//table[@class="atlas-record-table"]')->length)->toBe(0)
        ->and($domArbol->query('//dialog//aside[contains(@class,"atlas-taxon-information")]')->length)->toBe(1)
        ->and($domArbol->query('//dialog//section[contains(@class,"atlas-tree-section")]//svg')->length)->toBe(1)
        ->and($componente->instance()->detalleCelda['seleccionado']['taxon_id'])->toBe($f['taxones'][0]);
    // Sin filtro de filo ni taxón, el material no publicado del mismo punto sigue excluido.
    $sinFiltroTaxonomico = Livewire::withQueryParams(['vista' => 'mapa', 'fprov' => 'Pichincha'])
        ->test(PortalCatalogo::class)->call('abrirCelda', $latitud, $longitud)->call('cambiarVistaCelda', 'registros');
    expect($sinFiltroTaxonomico->instance()->detalleCelda['total'])->toBe(14);
    $idsSinFiltro = array_column($sinFiltroTaxonomico->instance()->detalleCelda['registros'], 'especimen_id');
    foreach ([2, 3] as $pagina) {
        $sinFiltroTaxonomico->call('paginarCelda', $pagina);
        $idsSinFiltro = [...$idsSinFiltro, ...array_column($sinFiltroTaxonomico->instance()->detalleCelda['registros'], 'especimen_id')];
    }
    expect($idsSinFiltro)->toHaveCount(14)->not->toContain($noPublicado)
        ->and(array_unique($idsSinFiltro))->toHaveCount(14);
});

test('la identidad del mapa de especie cambia al navegar y al modificar su selección', function (): void {
    $f = cartografiaRealFixture();
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
        ->test(PortalCatalogo::class)->assertViewHas('idTaxonActual', $f['taxones'][0]);
    $claveAlfa = $componente->viewData('claveMapaEspecie');
    expect($componente->viewData('puntosEspecie'))->toBe([['lat' => -0.25, 'lon' => -78.5, 'total' => 2, 'filos' => [$f['prefijo'] => 2], 'taxones' => 1]]);
    $componente->call('navegar', 'species', $f['prefijo'].' beta')->assertViewHas('idTaxonActual', $f['taxones'][1]);
    expect($componente->viewData('claveMapaEspecie'))->not->toBe($claveAlfa)
        ->and(array_column($componente->viewData('puntosEspecie'), 'lat'))->toBe([-2.5]);
    $componente->call('navegar', 'species', $f['prefijo'].' alfa')->set('filtroCatalogo', $f['codigos'][0]);
    expect($componente->viewData('claveMapaEspecie'))->not->toBe($claveAlfa)
        ->and(array_sum(array_column($componente->viewData('puntosEspecie'), 'total')))->toBe(1);
});

test('el árbol conserva ancestros y ramas hermanas y selecciona su información pública', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['taxon_id' => $f['taxones'][1]]);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5)->call('navegarCelda', $f['filo'])->call('navegarCelda', $f['taxones'][0]);
    $detalle = $componente->instance()->detalleCelda;
    expect(array_column($detalle['arbol'], 'id'))->toContain($f['filo'], $f['taxones'][0], $f['taxones'][1])
        ->and($detalle['seleccionado']['nombre'])->toBe($f['prefijo'].' alfa')
        ->and($detalle['informacion']['cantidad'])->toBe(1)
        ->and($detalle['seleccionado']['ilustracion']['representativa'])->toBeFalse()
        ->and($detalle['seleccionado']['ilustracion']['foto_real'])->toBeFalse()
        ->and($detalle['seleccionado']['ilustracion']['url'])->toBeNull();
    $componente->call('navegarCelda', $f['taxones'][1]);
    expect($componente->instance()->detalleCelda['seleccionado']['nombre'])->toBe($f['prefijo'].' beta');
});

test('un punto de una sola especie conserva su cadena real sin fabricar bifurcaciones', function (): void {
    $f = cartografiaRealFixture();
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5);
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['arbol_hojas_total'])->toBe(1)->and($detalle['arbol_ultima'])->toBe(1)
        ->and($detalle['arbol_registros_total'])->toBe(2)->and($detalle['arbol'])->toHaveCount(2)
        ->and(array_column($detalle['arbol'], 'taxon_id'))->toBe([$f['filo'], $f['taxones'][0]])
        ->and(array_column($detalle['arbol'], 'padre_id'))->toBe([null, $f['filo']]);
});

test('abrir un punto ofrece seis linajes completos por página y permite seleccionar directamente su especie propia', function (): void {
    $f = cartografiaRealFixture();
    $reino = (string) Str::uuid(); $familia = (string) Str::uuid(); $genero = (string) Str::uuid();
    $suborden = (string) Str::uuid(); $subfamilia = (string) Str::uuid(); $tribu = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $reino, 'padre_id' => null, 'nombre_cientifico' => $f['prefijo'].' Reino', 'rango' => 'reino'],
        ['id' => $suborden, 'padre_id' => $f['filo'], 'nombre_cientifico' => $f['prefijo'].' Suborden', 'rango' => 'suborden'],
        ['id' => $familia, 'padre_id' => $suborden, 'nombre_cientifico' => $f['prefijo'].' Familia', 'rango' => 'familia'],
        ['id' => $subfamilia, 'padre_id' => $familia, 'nombre_cientifico' => $f['prefijo'].' Subfamilia', 'rango' => 'subfamilia'],
        ['id' => $tribu, 'padre_id' => $subfamilia, 'nombre_cientifico' => $f['prefijo'].' Tribu', 'rango' => 'tribu'],
        ['id' => $genero, 'padre_id' => $tribu, 'nombre_cientifico' => $f['prefijo'].' Genero', 'rango' => 'genero'],
    ]);
    DB::table('taxonomia.taxones')->where('id', $f['filo'])->update(['padre_id' => $reino]);
    DB::table('taxonomia.taxones')->whereIn('id', $f['taxones'])->update(['padre_id' => $genero]);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['colector' => $f['prefijo']]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['decimal_latitude' => -0.25, 'decimal_longitude' => -78.5]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][1])->update(['family_visible' => false, 'genus_visible' => false]);
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $taxonesReales = [$reino, $f['filo'], $suborden, $familia, $subfamilia, $tribu, $genero, ...$f['taxones']];
    $especimenPorTaxon = [];
    foreach (range(0, 11) as $i) {
        $taxonId = (string) Str::uuid(); $id = (string) Str::uuid(); $codigo = 'QA-LINAJE-'.Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $taxonId, 'padre_id' => $genero,
            'nombre_cientifico' => $f['prefijo'].' '.($i === 11 ? 'RESERVADA' : 'zeta'.sprintf('%02d', $i)), 'rango' => 'especie']);
        DB::table('taxonomia.especimenes')->insert(array_replace($original, ['id' => $id, 'taxon_id' => $taxonId,
            'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id,
            'publicado' => true, 'scientific_name_visible' => $i !== 11]);
        if ($i !== 11) { $taxonesReales[] = $taxonId; $especimenPorTaxon[$taxonId] = $id; }
    }
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fco' => $f['prefijo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5);
    $detalle = $componente->instance()->detalleCelda;
    $hojas = static function (array $arbol): array {
        $padres = array_filter(array_column($arbol, 'padre_id'));
        return array_values(array_filter($arbol, static fn (array $n): bool => ! in_array($n['id'], $padres, true)));
    };
    expect($detalle['seleccionado'])->toBeNull()->and($detalle['registros'])->toBe([])
        ->and($detalle['arbol_hojas_total'])->toBe(14)->and($detalle['arbol_ultima'])->toBe(3)
        ->and($detalle['arbol_registros_total'])->toBe(15)->and($detalle['arbol_pagina'])->toBe(1)
        ->and($hojas($detalle['arbol']))->toHaveCount(6)
        ->and(array_values(array_unique(array_column($detalle['arbol'], 'rango'))))->toEqualCanonicalizing(['reino', 'phylum', 'suborden', 'familia', 'subfamilia', 'tribu', 'genero', 'especie'])
        ->and(array_diff(array_column($detalle['arbol'], 'taxon_id'), $taxonesReales))->toBe([]);
    $idsVisibles = array_column($detalle['arbol'], 'id');
    foreach ($detalle['arbol'] as $nodo) {
        if ($nodo['padre_id'] !== null) expect($idsVisibles)->toContain($nodo['padre_id']);
        expect(end($detalle['rutas'][$nodo['id']])['taxon_id'])->toBe($nodo['taxon_id']);
    }
    $ramasAlfa = array_values(array_filter($detalle['arbol'], static fn (array $n): bool => $n['taxon_id'] === $f['taxones'][0]));
    expect($ramasAlfa)->toHaveCount(2)->and($ramasAlfa[0]['id'])->not->toBe($ramasAlfa[1]['id']);
    $idsRutaPrivada = [$reino, $f['filo'], $suborden, $subfamilia, $tribu, $f['taxones'][0]];
    // El padre visual puede tener una variante de ruta; taxon_id es el UUID fuente.
    $privada = collect($ramasAlfa)->first(static fn (array $n): bool =>
        array_column($detalle['rutas'][$n['id']], 'taxon_id') === $idsRutaPrivada);
    expect($privada)->not->toBeNull();
    $rutaPrivada = $detalle['rutas'][$privada['id']];
    $padrePrivado = collect($detalle['arbol'])->firstWhere('id', $privada['padre_id']);
    expect($padrePrivado)->not->toBeNull()
        ->and($padrePrivado['taxon_id'])->toBe($tribu)
        ->and(array_column($rutaPrivada, 'taxon_id'))->not->toContain($familia, $genero)
        ->and(array_column($rutaPrivada, 'nombre'))->not->toContain($f['prefijo'].' Familia', $f['prefijo'].' Genero');
    $idFueraDePagina = collect($detalle['arbol'])->firstWhere('taxon_id', $f['taxones'][1])['id'];
    $componente->call('navegarCelda', $privada['id']);
    $seleccion = $componente->instance()->detalleCelda;
    expect($seleccion['informacion']['cantidad'])->toBe(1)
        ->and($seleccion['seleccionado']['jerarquia'])->toBe($seleccion['informacion']['jerarquia'])
        ->and($seleccion['informacion']['jerarquia'])->not->toHaveKey('family')->not->toHaveKey('familia')
        ->not->toHaveKey('genus')->not->toHaveKey('genero')->not->toHaveKey('género')
        ->and(array_column($seleccion['informacion']['jerarquia']['ancestros'], 'rango'))
        ->toBe(['kingdom', 'phylum', 'suborder', 'subfamily', 'tribe', 'species'])
        ->not->toContain('family', 'familia', 'genus', 'genero', 'género')
        ->and(array_column($seleccion['informacion']['jerarquia']['ancestros'], 'nombre'))
        ->toBe(array_column($rutaPrivada, 'nombre'))->not->toContain($f['prefijo'].' Familia', $f['prefijo'].' Genero')
        ->and(array_column($seleccion['rutas'][$privada['id']], 'taxon_id'))->toBe($idsRutaPrivada)
        ->and($seleccion['informacion']['jerarquia']['suborder'])->toBe($f['prefijo'].' Suborden')
        ->and($seleccion['informacion']['jerarquia']['subfamily'])->toBe($f['prefijo'].' Subfamilia')
        ->and($seleccion['informacion']['jerarquia']['tribe'])->toBe($f['prefijo'].' Tribu')
        ->and(array_column($seleccion['registros'], 'especimen_id'))->toBe([$f['ids'][1]])
        ->and($seleccion['arbol_hojas_total'])->toBe(14);
    $primerasHojas = array_column($hojas($detalle['arbol']), 'id');
    $componente->call('paginarArbolCelda', 2);
    $intermedia = $componente->instance()->detalleCelda;
    $hojasIntermedias = array_column($hojas($intermedia['arbol']), 'id');
    expect($intermedia['arbol_pagina'])->toBe(2)->and($hojasIntermedias)->toHaveCount(6)
        ->and(array_intersect($primerasHojas, $hojasIntermedias))->toBe([])
        ->and(array_column($intermedia['registros'], 'especimen_id'))->toBe([$f['ids'][1]]);
    $componente->call('paginarArbolCelda', 3);
    $segunda = $componente->instance()->detalleCelda;
    expect($segunda['arbol_pagina'])->toBe(3)->and($hojas($segunda['arbol']))->toHaveCount(2)
        ->and(array_intersect($primerasHojas, array_column($hojas($segunda['arbol']), 'id')))->toBe([])
        ->and(array_column($segunda['registros'], 'especimen_id'))->toBe([$f['ids'][1]])
        ->and($segunda['rutas'])->not->toHaveKey($idFueraDePagina);
    expect(count(array_unique([...$primerasHojas, ...$hojasIntermedias, ...array_column($hojas($segunda['arbol']), 'id')])))->toBe(14);
    $componente->call('navegarCelda', $idFueraDePagina);
    expect($componente->instance()->detalleCelda['seleccionado']['id'])->toBe($privada['id']);
    $hoja = $hojas($segunda['arbol'])[0];
    $componente->call('navegarCelda', $hoja['id']);
    $propia = $componente->instance()->detalleCelda;
    expect($propia['seleccionado']['taxon_id'])->toBe($hoja['taxon_id'])
        ->and($propia['informacion']['taxon'])->toBe($hoja['nombre'])->and($propia['informacion']['cantidad'])->toBe(1)
        ->and(array_column($propia['registros'], 'especimen_id'))->toBe([$especimenPorTaxon[$hoja['taxon_id']]])
        ->and(array_column($propia['informacion']['jerarquia']['ancestros'], 'nombre'))->toBe(array_column($propia['rutas'][$hoja['id']], 'nombre'))
        ->and($propia['informacion']['jerarquia']['suborder'])->toBe($f['prefijo'].' Suborden')
        ->and($propia['informacion']['jerarquia']['subfamily'])->toBe($f['prefijo'].' Subfamilia')
        ->and($propia['informacion']['jerarquia']['tribe'])->toBe($f['prefijo'].' Tribu')
        ->and($propia['arbol_pagina'])->toBe(3);
    $componente->call('volverCelda', 0);
    expect($componente->instance()->detalleCelda['arbol_pagina'])->toBe(1)
        ->and($componente->instance()->detalleCelda['seleccionado'])->toBeNull();
    $componente->call('paginarArbolCelda', 2)->call('cerrarCelda')->assertSet('paginaArbolCelda', 1)
        ->call('abrirCelda', -0.25, -78.5);
    expect($componente->instance()->detalleCelda['arbol_pagina'])->toBe(1);
});

test('las ramas del mismo taxón con permisos distintos conservan sus conteos y UUID propios', function (): void {
    $f = cartografiaRealFixture();
    $familia = (string) Str::uuid(); $genero = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $familia, 'padre_id' => $f['filo'], 'nombre_cientifico' => $f['prefijo'].' familia', 'rango' => 'familia'],
        ['id' => $genero, 'padre_id' => $familia, 'nombre_cientifico' => $f['prefijo'].' genero', 'rango' => 'genero'],
    ]);
    DB::table('taxonomia.taxones')->where('id', $f['taxones'][0])->update(['padre_id' => $genero]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][1])
        ->update(['family_visible' => false, 'genus_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5)->call('navegarCelda', $f['filo']);
    $detalle = $componente->instance()->detalleCelda;
    $ramaReservada = collect($detalle['grupos'])->firstWhere('taxon_id', $f['taxones'][0]);
    expect($detalle['total'])->toBe(2)->and($ramaReservada['total'])->toBe(1);
    $componente->call('navegarCelda', $ramaReservada['id']);
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['total'])->toBe(1)
        ->and($detalle['seleccionado']['taxon_id'])->toBe($f['taxones'][0])
        ->and($detalle['seleccionado']['total'])->toBe(1)
        ->and($detalle['informacion']['cantidad'])->toBe(1)
        ->and($detalle['seleccionado']['jerarquia'])->toBe($detalle['informacion']['jerarquia'])
        ->and($detalle['informacion']['jerarquia'])->not->toHaveKey('family')->not->toHaveKey('familia')
        ->not->toHaveKey('genus')->not->toHaveKey('genero')->not->toHaveKey('género')
        ->and(array_column($detalle['rutas'][$ramaReservada['id']], 'taxon_id'))->toBe([$f['filo'], $f['taxones'][0]])
        ->not->toContain($familia, $genero)
        ->and(array_column($detalle['informacion']['jerarquia']['ancestros'], 'rango'))->toBe(['phylum', 'species'])
        ->not->toContain('family', 'familia', 'genus', 'genero', 'género')
        ->and(array_column($detalle['informacion']['jerarquia']['ancestros'], 'nombre'))->toBe([$f['prefijo'], $f['prefijo'].' alfa'])
        ->not->toContain($f['prefijo'].' familia', $f['prefijo'].' genero')
        ->and(array_column($detalle['registros'], 'especimen_id'))->toBe([$f['ids'][1]])
        ->and(get_object_vars($detalle['registros'][0]))->not->toHaveKey('family')->not->toHaveKey('familia')
        ->not->toHaveKey('genus')->not->toHaveKey('genero')->not->toHaveKey('género')
        ->and(array_values(get_object_vars($detalle['registros'][0])))->not->toContain($f['prefijo'].' familia', $f['prefijo'].' genero');
    $componente->call('navegarCelda', $f['filo'])->call('navegarCelda', $familia)->call('navegarCelda', $genero);
    $ramaPublica = collect($componente->instance()->detalleCelda['grupos'])->firstWhere('taxon_id', $f['taxones'][0]);
    expect($ramaPublica['id'])->not->toBe($ramaReservada['id'])->and($ramaPublica['total'])->toBe(1);
    $componente->call('navegarCelda', $ramaPublica['id']);
    $detalle = $componente->instance()->detalleCelda;
    expect($detalle['total'])->toBe(1)->and($detalle['seleccionado']['total'])->toBe(1)
        ->and($detalle['informacion']['jerarquia']['family'])->toBe($f['prefijo'].' familia')
        ->and($detalle['informacion']['jerarquia']['genus'])->toBe($f['prefijo'].' genero')
        ->and(array_column($detalle['registros'], 'especimen_id'))->toBe([$f['ids'][0]]);
    $tarjetas = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('navegar', 'phylum', $f['prefijo'])->assertViewHas('totalTarjetas', 3)
        ->assertViewHas('especiesActuales', fn ($especies) => collect($especies)->firstWhere('id', $f['taxones'][0])['total'] === 1);
    $tarjetas->call('navegar', 'genus', $f['prefijo'].' genero')->assertViewHas('totalTarjetas', 1)
        ->assertViewHas('totalRegistrosVista', 1)->assertViewHas('especiesActuales', fn ($especies) => count($especies) === 1 && $especies[0]['total'] === 1);
    $tarjetas->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 1)
        ->assertViewHas('registrosVista', fn ($registros) => array_column($registros, 'especimen_id') === [$f['ids'][0]]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    expect($repo->cursorParaCsv(FiltrosBusqueda::desde(['filtroFiloId' => $f['filo']]), 'genus', $f['prefijo'].' genero')->pluck('occurrence_id')->all())->toBe([$f['codigos'][0]]);
    foreach ([$f['prefijo'].' familia', $f['prefijo'].' genero'] as $nombre)
        expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroTaxon' => $nombre]), 1)['ids'])->toBe([$f['ids'][0]]);
    $tarjetas->call('cambiarVista', 'tarjetas')->call('explorarNivel', 'species')->assertViewHas('totalTarjetas', 2);
    $alfa = collect($tarjetas->viewData('taxonesExplorados'))->firstWhere('taxon', $f['prefijo'].' alfa');
    expect($alfa['jerarquia']['family'])->toBe($f['prefijo'].' familia')->and($alfa['jerarquia']['genus'])->toBe($f['prefijo'].' genero');
    $tarjetas->call('cambiarPagina', 99)->call('volverAlArbol')->assertSet('pagina', 1);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][1])->update(['genus_visible' => true]);
    $tarjetas->call('explorarNivel', 'genus')->assertViewHas('totalTarjetas', 1)
        ->assertViewHas('conteos', fn ($conteos) => $conteos['genus:'.$f['prefijo'].' genero'] === 2);
    $generoExplorado = $tarjetas->viewData('taxonesExplorados')[0];
    expect($generoExplorado['taxon'])->toBe($f['prefijo'].' genero')->and($generoExplorado)->not->toHaveKey('total');
});

test('el mosaico conserva la identidad del género sin fotografía propia y retira ancestros reservados', function (): void {
    $f = cartografiaRealFixture();
    $familia = DB::table('taxonomia.taxones')->where('rango', 'familia')->where('nombre_cientifico', 'Formicidae')->value('id');
    if ($familia === null) {
        $familia = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $familia, 'padre_id' => $f['filo'], 'nombre_cientifico' => 'Formicidae', 'rango' => 'familia']);
    }
    $genero = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $genero, 'padre_id' => $familia, 'nombre_cientifico' => $f['prefijo'].' genero', 'rango' => 'genero']);
    DB::table('taxonomia.taxones')->where('id', $f['taxones'][0])->update(['padre_id' => $genero]);
    $filtros = ['taxon_navegado' => '', 'taxon' => $f['prefijo'].' genero'];
    $estadisticas = app(PortalEstadisticas::class);
    $linaje = $estadisticas->datosParaVista($filtros)['taxon_mosaico'];
    $rangosPublicos = array_fill_keys(['kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'species'], true);
    $fotografia = IlustracionTaxonomica::paraTaxon($linaje);
    expect($linaje['genus'])->toBe($f['prefijo'].' genero')->and($linaje['family'])->toBe('Formicidae')
        ->and($fotografia['foto_real'])->toBeFalse()->and($fotografia['morfologia'])->toBeFalse()
        ->and($fotografia['representativa'])->toBeFalse()->and($fotografia['url'])->toBeNull()
        ->and($fotografia['grupo'])->toBe('')->and($fotografia['species'])->toBe('')
        ->and($fotografia['taxon_consulta'])->toBe(array_intersect_key($linaje, $rangosPublicos))
        ->not->toHaveKey('species');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', [$f['ids'][0], $f['ids'][1]])->update(['family_visible' => false]);
    $linajeSinFamilia = $estadisticas->datosParaVista($filtros)['taxon_mosaico'];
    $fotografiaSinFamilia = IlustracionTaxonomica::paraTaxon($linajeSinFamilia);
    expect($linajeSinFamilia)->not->toHaveKey('family')
        ->and($linajeSinFamilia['genus'])->toBe($f['prefijo'].' genero')
        ->and($fotografiaSinFamilia['url'])->toBeNull()->and($fotografiaSinFamilia['foto_real'])->toBeFalse()
        ->and($fotografiaSinFamilia['taxon_consulta'])->toBe(array_intersect_key($linajeSinFamilia, $rangosPublicos))
        ->not->toHaveKey('family')->not->toHaveKey('species')
        ->and(array_column($linajeSinFamilia['ancestros'], 'nombre'))->not->toContain('Formicidae');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', [$f['ids'][0], $f['ids'][1]])->update(['genus_visible' => false]);
    $sinGenero = $estadisticas->datosParaVista($filtros);
    expect($sinGenero['taxon_mosaico'])->toBe([])
        ->and($sinGenero['ilustraciones_mosaico'])->toHaveCount(1)
        ->and($sinGenero['ilustraciones_mosaico'][0]['url'])->toBeNull()
        ->and($sinGenero['ilustraciones_mosaico'][0]['foto_real'])->toBeFalse()
        ->and($sinGenero['ilustraciones_mosaico'][0]['taxon_consulta'])->toBe([]);
});

test('galería y portadas limitan imágenes y conservan originales sin atribuir códigos ambiguos', function (): void {
    $f = cartografiaRealFixture(); $imagenes = [];
    for ($i = 1; $i <= 15; $i++) {
        $imagenes[] = ['id' => (string) Str::uuid(), 'occurrence_id' => $f['codigos'][0], 'nombre_original' => sprintf('vista-%02d.jpg', $i),
            'ruta' => 'divulgacion/imagenes/cartografia-'.Str::uuid().'.jpg', 'disco' => 'r2',
            'autor_nombre' => 'QA', 'autor_apellido' => 'Portal', 'autor_nombre_completo' => 'QA Portal',
            'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00'];
    }
    DB::table('divulgacion.imagenes_taxonomicas')->insert($imagenes);
    DB::table('divulgacion.imagenes_por_defecto')->insert(['id' => (string) Str::uuid(), 'nivel' => 'species',
        'valor_taxon' => $f['prefijo'].' alfa', 'imagen_id' => $imagenes[14]['id']]);
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
        ->test(PortalCatalogo::class)->assertViewHas('galeriaEspecie', fn ($galeria) => count($galeria) === 12 && $galeria[0]['imagenId'] === $imagenes[14]['id'] && $galeria[0]['esPortada'])
        ->assertViewHas('imagenesPorEspecimen', fn ($fotos) => count($fotos[$f['codigos'][0]]) === 12);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['occurrence_id' => $f['codigos'][0]]);
    $componente->call('cambiarPagina', 1)->assertViewHas('galeriaEspecie', [])
        ->assertViewHas('imagenesPorEspecimen', []);
    $componente->call('navegar', 'phylum', $f['prefijo'])->assertViewHas('portadas', []);
    expect(DB::table('divulgacion.imagenes_taxonomicas')->whereIn('id', array_column($imagenes, 'id'))->count())->toBe(15);
});

test('XLSX conserva la selección UUID y permisos sin convertir textos de colecta en fórmulas', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['colector' => '=1+1', 'country' => 'PAIS-RESERVADO']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['occurrence_id' => $f['codigos'][0], 'colector' => '=1+1']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['occurrence_id_visible' => false, 'country_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa', 'fco' => '=1+1'])
        ->test(PortalCatalogo::class)->assertViewHas('totalRegistrosVista', 1);
    ob_start();
    $componente->instance()->descargarDatos()->sendContent();
    $contenido = ob_get_clean();
    $archivo = tempnam(sys_get_temp_dir(), 'pest-portal-xlsx-');
    try {
        file_put_contents($archivo, $contenido);
        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $libro = $lector->load($archivo);
        $hoja = $libro->getActiveSheet();
        $filas = $hoja->toArray();
        expect($filas)->toHaveCount(2)->and($filas[0][0])->toBe('occurrenceID')->and($filas[1][0])->toBe('')
            ->and($filas[1][1])->toBe($f['prefijo'].' alfa')->and($filas[1][6])->toBe('')
            ->and($filas[1][9])->toBe('=1+1')->and($hoja->getCell('J2')->getDataType())->toBe('inlineStr');
        $libro->disconnectWorksheets();
        DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['scientific_name_visible' => false]);
        $reservado = app(ExportarRegistrosEspecimenesHandler::class)->handle(new ExportarRegistrosEspecimenesInput($f['prefijo'].' alfa', [$f['ids'][0]]));
        file_put_contents($archivo, $reservado->contenidoXlsx);
        $libro = $lector->load($archivo);
        expect($libro->getActiveSheet()->getCell('A2')->getValue())->toBe('')
            ->and($libro->getActiveSheet()->getCell('B2')->getValue())->toBe('');
        $libro->disconnectWorksheets();
    } finally {
        unlink($archivo);
    }
});

test('QA4 exporta la selección completa desde la segunda página conservando Unicode y coordenadas originales', function (): void {
    $f = cartografiaRealFixture();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $codigosEsperados = [$f['codigos'][0], $f['codigos'][1]];
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])
        ->update(['localidad_verbatim' => 'Río Ñambí; sector norte', 'locality_name' => 'Río Ñambí; sector norte']);
    for ($i = 0; $i < 12; $i++) {
        $id = (string) Str::uuid();
        $codigo = 'QA-EXPORT-'.Str::uuid();
        $codigosEsperados[] = $codigo;
        DB::table('taxonomia.especimenes')->insert(array_replace($original, [
            'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
            'localidad_verbatim' => 'Río Ñambí; sector norte', 'locality_name' => 'Río Ñambí; sector norte',
        ]));
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
        ]);
    }

    $componente = Livewire::withQueryParams([
        'vista' => 'tarjetas', 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa',
        'fph' => $f['filo'], 'fprov' => 'Pichincha', 'fco' => 'QA',
        'ffd' => '2025-01-01', 'ffh' => '2025-01-31', 'pagina' => 2,
    ])->test(PortalCatalogo::class)->assertViewHas('totalRegistrosVista', 14)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 6);

    ob_start();
    $componente->instance()->descargarResultados(app(EloquentProveedorEspecimenesParaArbol::class))->sendContent();
    $csv = ob_get_clean();
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();
    $filasCsv = array_map(static fn (string $linea): array => str_getcsv($linea, ';', '"', ''),
        explode("\n", rtrim(substr($csv, 3), "\r\n")));
    $datosCsv = array_slice($filasCsv, 1);
    expect($datosCsv)->toHaveCount(14)
        ->and(array_column($datosCsv, 0))->toEqualCanonicalizing($codigosEsperados)
        ->and(array_unique(array_column($datosCsv, 3)))->toBe(['Río Ñambí; sector norte']);
    foreach ($datosCsv as $fila) {
        expect(is_numeric($fila[7]))->toBeTrue()->and(is_numeric($fila[8]))->toBeTrue()
            ->and((float) $fila[7])->toBe(-0.25)
            ->and((float) $fila[8])->toBe(-78.5);
    }

    ob_start();
    $componente->instance()->descargarDatos()->sendContent();
    $contenido = ob_get_clean();
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa4-export-');
    try {
        file_put_contents($archivo, $contenido);
        $libro = IOFactory::load($archivo);
        $datosXlsx = array_slice($libro->getActiveSheet()->toArray(), 1);
        expect($datosXlsx)->toHaveCount(14)
            ->and(array_column($datosXlsx, 0))->toEqualCanonicalizing($codigosEsperados)
            ->and(array_unique(array_column($datosXlsx, 1)))->toBe([$f['prefijo'].' alfa'])
            ->and(array_unique(array_column($datosXlsx, 5)))->toBe(['Río Ñambí; sector norte']);
        foreach ($datosXlsx as $fila) {
            expect((float) $fila[7])->toBe(-0.25)->and((float) $fila[8])->toBe(-78.5);
        }
        foreach (range(2, 15) as $fila) {
            expect($libro->getActiveSheet()->getCell('H'.$fila)->getDataType())->toBe('n')
                ->and($libro->getActiveSheet()->getCell('I'.$fila)->getDataType())->toBe('n');
        }
        $libro->disconnectWorksheets();
    } finally {
        unlink($archivo);
    }
});

test('QA5 CSV comparte localidad INEC y estado con pantalla sin publicar datos reservados', function (): void {
    $f = cartografiaRealFixture();
    $codigoInec = '01'.random_int(1000000000, 9999999999);
    $localidadConfirmada = (string) Str::uuid();
    $localidadSinMatch = (string) Str::uuid();
    DB::table('recepciones.localidades_ecuador_catalogo')->insert([
        'codigo' => $codigoInec, 'provincia_codigo' => '01', 'nombre' => 'Referencia oficial '.$f['prefijo'],
        'busqueda' => $f['prefijo'], 'fuente' => 'INEC QA5',
    ]);
    DB::table('taxonomia.localidades')->insert([
        ['id' => $localidadConfirmada, 'nombre_canonico' => 'Texto de campo distinto', 'rango' => 'sitio',
            'codigo_inec' => $codigoInec, 'referencia_inec' => 'Localidad cercana (aproximada)'],
        ['id' => $localidadSinMatch, 'nombre_canonico' => 'Original sin homologación', 'rango' => 'sitio',
            'codigo_inec' => null, 'referencia_inec' => null],
    ]);
    foreach ([0, 1, 2] as $i) DB::table('taxonomia.especimenes')->where('id', $f['ids'][$i])->update([
        'localidad_id' => $i === 1 ? $localidadSinMatch : $localidadConfirmada,
        'localidad_verbatim' => $i === 1 ? '=1+1' : 'Localidad original Ñambí',
    ]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->update(['locality_name_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo']])->test(PortalCatalogo::class);
    $pantalla = collect($componente->viewData('registrosVista'))->keyBy('especimen_id');
    ob_start();
    $componente->instance()->descargarResultados(app(EloquentProveedorEspecimenesParaArbol::class))->sendContent();
    $csv = ob_get_clean();
    $filas = array_map(static fn (string $linea): array => str_getcsv($linea, ';', '"', ''),
        explode("\n", rtrim(substr($csv, 3), "\r\n")));
    $cabecera = array_shift($filas);
    $datos = array_column($filas, null, 0);
    expect($filas)->toHaveCount(3)->and($cabecera[11])->toBe('Referencia INEC')
        ->and($datos[$f['codigos'][0]][3])->toBe('Localidad original Ñambí')
        ->and($datos[$f['codigos'][0]][4])->toBe($pantalla[$f['ids'][0]]->locality_inec)
        ->and($datos[$f['codigos'][0]][5])->toBe($codigoInec)
        ->and($datos[$f['codigos'][0]][11])->toBe($pantalla[$f['ids'][0]]->locality_inec_reference)
        ->and($datos[$f['codigos'][1]][3])->toBe("'=1+1")
        ->and($datos[$f['codigos'][1]][4])->toBe('')->and($datos[$f['codigos'][1]][5])->toBe('')
        ->and($datos[$f['codigos'][1]][11])->toBe('Sin correspondencia confirmada')
        ->and($pantalla[$f['ids'][1]]->locality_inec)->toBeNull();
    foreach ([3, 4, 5, 11] as $columna) expect($datos[$f['codigos'][2]][$columna])->toBe('');
});

test('QA5 CSV conserva ceros y precisión y excluye pares incompletos o reservados', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => 0, 'decimal_longitude' => -78.1234567]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => null, 'decimal_longitude' => null]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->update(['decimal_latitude_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $f['filo']])->test(PortalCatalogo::class);
    ob_start();
    $componente->instance()->descargarResultados(app(EloquentProveedorEspecimenesParaArbol::class))->sendContent();
    $csv = ob_get_clean();
    $filas = array_map(static fn (string $linea): array => str_getcsv($linea, ';', '"', ''),
        explode("\n", rtrim(substr($csv, 3), "\r\n")));
    $datos = array_column(array_slice($filas, 1), null, 0);
    expect(is_numeric($datos[$f['codigos'][0]][7]))->toBeTrue()
        ->and((float) $datos[$f['codigos'][0]][7])->toBe(0.0)
        ->and((float) $datos[$f['codigos'][0]][8])->toBe(-78.1234567);
    expect($datos)->toHaveCount(1)->not->toHaveKey($f['codigos'][1])->not->toHaveKey($f['codigos'][2]);
});

test('QA3-002 las notas de reubicación se conservan sin contar como especie o provincia', function (): void {
    $f = cartografiaRealFixture(); $nota = 'muestra reubicada dentro de 1269 muestrareubicadadentrode1269';
    DB::table('taxonomia.taxones')->where('id', $f['taxones'][0])->update(['nombre_cientifico' => $nota]);
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])->update(['state_province' => 'muestra reubicada dentro de 1269']);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect((int) $datos['resumen']['registros'])->toBe(3)->and((int) $datos['resumen']['identificados'])->toBe(1)
        ->and(array_column($datos['riqueza'], 'provincia'))->toBe(['Napo']);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => $f['codigos'][0]])->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($filas) => $filas[0]->scientific_name === $nota && $filas[0]->taxon_en_revision === true);
});

test('el panel usa el protocolo original sin muestra y respeta su marca de visibilidad', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['muestra_id' => null, 'sampling_protocol' => 'Winkler QA']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['sampling_protocol' => 'pitfall QA']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['sampling_protocol' => 'METODO-RESERVADO-QA']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->update(['sampling_protocol_visible' => false]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect(array_column($datos['metodos'], 'metodo'))->toEqualCanonicalizing(['winkler qa', 'pitfall qa'])
        ->and(array_sum(array_column($datos['metodos'], 'registros')))->toBe(2);
    $ids = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica(FiltrosBusqueda::desde(['filtroFiloId' => $f['filo'], 'filtroMetodos' => ['Winkler QA']]), 1)['ids'];
    expect($ids)->toBe([$f['ids'][0]]);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => $f['codigos'][0]])->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($filas) => $filas[0]->sampling_protocol === 'Winkler QA');
});

test('la comparación geográfica une variantes de tilde y preserva los valores originales', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['country' => 'Perú', 'state_province' => 'Manabí', 'locality_name' => 'Yasuní']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['country' => 'Peru', 'state_province' => 'Manabi', 'locality_name' => 'Yasuni']);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    foreach (['Perú', 'Peru', ' PERÚ '] as $pais) {
        expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroFiloId' => $f['filo'], 'filtroPais' => $pais, 'filtroProvincia' => 'manabi', 'filtroGeografias' => ['Yasuni']]), 1)['ids'])
            ->toEqualCanonicalizing([$f['ids'][0], $f['ids'][1]]);
    }
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('country'))->toBe('Perú');
});

test('la ruta de estadísticas conserva un alias de provincia al redirigir al mapa', function (): void {
    $f = cartografiaRealFixture(); $provincia = 'Manabí '.$f['prefijo']; $alias = 'MANABI '.$f['prefijo'];
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])->update(['state_province' => $provincia]);
    $this->get(route('portal.estadisticas', ['provincia' => $alias, 'filo' => $f['filo']]))
        ->assertRedirect(route('portal.catalogo', ['vista' => 'mapa', 'fprov' => $alias, 'fph' => $f['filo']]));
    Livewire::withQueryParams(['vista' => 'mapa', 'fprov' => $alias, 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->assertViewHas('datosMapa', fn ($datos) => (int) $datos['resumen']['registros'] === 2);
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('state_province'))->toBe($provincia);
});

test('cambiar divulgación invalida inmediatamente los puntos y estadísticas cacheados', function (): void {
    $f = cartografiaRealFixture(); $estadisticas = app(PortalEstadisticas::class);
    expect(array_sum(array_column($estadisticas->datosParaVista(['filo' => $f['filo']])['mapa'], 'total')))->toBe(3);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_latitude_visible' => false]);
    expect(array_sum(array_column($estadisticas->datosParaVista(['filo' => $f['filo']])['mapa'], 'total')))->toBe(2)
        ->and(array_sum(array_column($estadisticas->puntosParaMapa(['filo' => $f['filo']]), 'total')))->toBe(2);
});

test('la recuperación exige las tres identidades de fuente y conserva correcciones previas', function (): void {
    // DatabaseFeatureTestCase revierte también estas filas preexistentes al terminar.
    // Se liberan temporalmente las posiciones de origen para no asumir una BD vacía.
    expect(DB::transactionLevel())->toBeGreaterThan(0);
    DB::table('taxonomia.especimenes')->whereIn('fila_origen_excel', [1, 2, 3])
        ->update(['fila_origen_excel' => null]);
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['fila_origen_excel' => 1, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => '560', 'sampling_protocol' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['fila_origen_excel' => 2, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => 'FUENTE-DISTINTA', 'sampling_protocol' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['fila_origen_excel' => 3, 'occurrence_id' => 'MEPN-INV-2', 'old_code' => '561', 'sampling_protocol' => 'Corrección curatorial']);
    $migracion = require base_path('Modules/CatalogoPublico/database/migrations/2026_10_02_000012_restore_original_sampling_protocol.php');
    $migracion->up(); $migracion->up();
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('sampling_protocol'))->toBe('fogging')
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->value('sampling_protocol'))->toBeNull()
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->value('sampling_protocol'))->toBe('Corrección curatorial');
});

test('recuperar la localidad original solo rellena ausentes de la fila fuente exacta y es idempotente', function (): void {
    expect(DB::transactionLevel())->toBeGreaterThan(0);
    DB::table('taxonomia.especimenes')->whereIn('fila_origen_excel', [1, 2, 3, 4, 302, 309])->update(['fila_origen_excel' => null]);
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update([
        'fila_origen_excel' => 1, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => '560', 'localidad_verbatim' => null,
        'localidad' => 'Localidad corregida', 'locality_name' => 'Sitio curado', 'sampling_protocol' => 'Método corregido',
    ]);
    // El código público homónimo no permite recuperar una fila con otra identidad.
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update([
        'fila_origen_excel' => 2, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => 'OTRA-FUENTE', 'localidad_verbatim' => '',
    ]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update([
        'fila_origen_excel' => 3, 'occurrence_id' => 'MEPN-INV-2', 'old_code' => '561', 'localidad_verbatim' => 'Texto corregido por curatoría',
    ]);
    $vacio = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert([
        'id' => $vacio, 'codigo_catalogo' => 'QA-LOCALIDAD-'.Str::uuid(), 'taxon_id' => $f['taxones'][0],
        'fila_origen_excel' => 4, 'occurrence_id' => 'MEPN-INV-2', 'old_code' => '420', 'localidad_verbatim' => '',
        'localidad' => 'Otro sitio curado', 'decimal_latitude' => -0.75, 'decimal_longitude' => -78.75,
    ]);
    $sinOldCode = (string) Str::uuid();
    $oldCodeDiscordante = (string) Str::uuid();
    foreach ([[$sinOldCode, 302, 'MEPN-INV-150', null], [$oldCodeDiscordante, 309, 'MEPN-INV-153', 'OTRA-FUENTE']] as [$id, $origen, $occurrence, $oldCode]) {
        DB::table('taxonomia.especimenes')->insert([
            'id' => $id, 'codigo_catalogo' => 'QA-LOCALIDAD-'.Str::uuid(), 'taxon_id' => $f['taxones'][0],
            'fila_origen_excel' => $origen, 'occurrence_id' => $occurrence, 'old_code' => $oldCode,
            'localidad' => 'Sitio curado', 'localidad_verbatim' => null,
        ]);
    }
    $ids = [...$f['ids'], $vacio, $sinOldCode, $oldCodeDiscordante];
    $campos = ['id', 'occurrence_id', 'old_code', 'localidad', 'locality_name', 'country', 'state_province',
        'decimal_latitude', 'decimal_longitude', 'sampling_protocol'];
    $antes = DB::table('taxonomia.especimenes')->whereIn('id', $ids)->orderBy('id')->get($campos)->toArray();
    $migracion = require base_path('Modules/CatalogoPublico/database/migrations/2026_10_02_000015_restore_original_locality_verbatim.php');
    $migracion->up();
    $primera = DB::table('taxonomia.especimenes')->whereIn('id', $ids)->orderBy('id')->pluck('localidad_verbatim', 'id')->all();
    expect($primera[$f['ids'][0]])->toBe('Parque Nacional Yasuní, Onkonegare')
        ->and($primera[$f['ids'][1]])->toBe('')
        ->and($primera[$f['ids'][2]])->toBe('Texto corregido por curatoría')
        ->and($primera[$vacio])->toBe('Parque Nacional Yasuní, Afluente Río Rumiyacu, Bloque 31 (Petrobras), Localidad 1 (ex Apaika 2), Cononaco')
        ->and($primera[$sinOldCode])->toBe('Parque Nacional Yasuní, Estación de Biodiversidad Tiputini')
        ->and($primera[$oldCodeDiscordante])->toBeNull();
    $migracion->up();
    expect(DB::table('taxonomia.especimenes')->whereIn('id', $ids)->orderBy('id')->pluck('localidad_verbatim', 'id')->all())->toBe($primera)
        ->and(DB::table('taxonomia.especimenes')->whereIn('id', $ids)->orderBy('id')->get($campos)->toArray())->toEqual($antes);
});

test('una jerarquía parcial muestra sus especies debajo del filo sin inventar rangos intermedios', function (): void {
    $f = cartografiaRealFixture();
    Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('navegar', 'phylum', $f['prefijo'])->assertViewHas('totalTarjetas', 2)
        ->assertSee($f['prefijo'].' alfa')->assertSee($f['prefijo'].' beta')
        ->call('navegar', 'species', $f['prefijo'].' alfa')->assertViewHas('totalEspecimenes', 2);
});
