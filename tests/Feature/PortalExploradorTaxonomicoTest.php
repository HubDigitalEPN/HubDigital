<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function exploradorTaxonomicoFixture(int $cantidad = 4): array
{
    $prefijo = 'Explorador'.Str::lower(Str::random(16));
    $ramas = $registros = [];
    foreach (range(0, $cantidad - 1) as $i) {
        $padre = null; $rama = [];
        foreach (['phylum', 'clase', 'orden', 'familia', 'genero', 'especie', 'subespecie'] as $rango) {
            $id = (string) Str::uuid();
            $nombre = $prefijo.$i.' '.($rango === 'especie' ? 'alfa' : $rango);
            DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'rango' => $rango, 'nombre_cientifico' => $nombre]);
            $rama[$rango] = ['id' => $id, 'nombre' => $nombre]; $padre = $id;
        }
        $ramas[] = $rama;
        for ($j = 0; $j <= $i; $j++) $registros[$i][] = exploradorInsertarRegistro($rama['especie']['id'], $prefijo, $i);
    }
    return compact('prefijo', 'ramas', 'registros');
}

function exploradorInsertarRegistro(string $taxon, string $prefijo, int $i = 0, array $permisos = []): string
{
    $id = (string) Str::uuid(); $codigo = 'QA-EXPLORADOR-'.Str::uuid();
    DB::table('taxonomia.especimenes')->insert([
        'id' => $id, 'taxon_id' => $taxon, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
        'localidad' => $prefijo, 'locality_name' => $prefijo, 'state_province' => $i === 0 ? 'Pichincha' : 'Napo',
        'country' => 'Ecuador', 'colector' => 'QA explorador', 'fecha_colecta' => '2025-01-10',
        'decimal_latitude' => -.273 - $i, 'decimal_longitude' => -79.024 + $i,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert(array_replace([
        'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
    ], $permisos));
    return $id;
}

test('el explorador entrega cuatro o cinco raíces públicas independientes y sólo los hijos solicitados', function (): void {
    $f = exploradorTaxonomicoFixture(); $servicio = app(ExploradorTaxonomicoPublico::class);
    $inicial = $servicio->consultar(FiltrosBusqueda::vacio());
    expect($inicial['nodos'])->toHaveCount(4)->and($inicial['expandidos'])->toBe([])
        ->and(array_column($inicial['nodos'], 'padre'))->toBe([null, null, null, null])
        ->and(array_column($inicial['nodos'], 'rango'))->toBe(['phylum', 'phylum', 'phylum', 'phylum'])
        ->and(array_column($inicial['nodos'], 'total'))->toBe([1, 2, 3, 4]);
    $hijos = $servicio->consultar(FiltrosBusqueda::vacio(), $inicial['nodos'][0]['clave']);
    expect($hijos['nodos'])->toHaveCount(1)->and($hijos['nodos'][0]['rango'])->toBe('clase')
        ->and($hijos['nodos'][0]['padre'])->toBe($inicial['nodos'][0]['clave']);
    exploradorTaxonomicoFixture(1);
    expect($servicio->consultar(FiltrosBusqueda::vacio())['nodos'])->toHaveCount(5);
    $acotado = $servicio->consultar(FiltrosBusqueda::desde(['filtroFilos' => [$f['ramas'][0]['phylum']['id']]]));
    expect($acotado['nodos'])->toHaveCount(1)->and($acotado['nodos'][0]['padre'])->toBeNull();
});

test('buscar consulta nombres publicados y devuelve sólo sus rutas con los ancestros expandidos', function (): void {
    $f = exploradorTaxonomicoFixture(); $servicio = app(ExploradorTaxonomicoPublico::class);
    $rama = $f['ramas'][2];
    $busqueda = $servicio->consultar(FiltrosBusqueda::vacio(), null, $rama['especie']['nombre']);
    expect(array_column($busqueda['nodos'], 'id'))->toEqualCanonicalizing(array_column(array_slice($rama, 0, 6), 'id'))
        ->and(array_column(array_filter($busqueda['nodos'], fn ($n) => $n['coincide']), 'id'))->toBe([$rama['especie']['id']])
        ->and($busqueda['expandidos'])->toHaveCount(5);
    foreach ($busqueda['nodos'] as $nodo) if ($nodo['padre'] !== null) expect($busqueda['expandidos'])->toContain($nodo['padre']);
    // PostgreSQL debe tratar los comodines escritos por el visitante como texto literal.
    expect($servicio->consultar(FiltrosBusqueda::vacio(), null, '%')['nodos'])->toBe([])
        ->and($servicio->consultar(FiltrosBusqueda::vacio(), null, '_')['nodos'])->toBe([]);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][2])->update(['scientific_name_visible' => false]);
    expect($servicio->consultar(FiltrosBusqueda::vacio(), null, $rama['especie']['nombre'])['nodos'])->toBe([])
        ->and($servicio->taxon(FiltrosBusqueda::vacio(), $rama['especie']['id']))->toBeNull();
});

test('la selección por identidad incluye descendientes y respeta campos taxonómicos y coordenadas reservadas', function (): void {
    $f = exploradorTaxonomicoFixture(1); $rama = $f['ramas'][0]; $servicio = app(ExploradorTaxonomicoPublico::class);
    $subespecie = exploradorInsertarRegistro($rama['subespecie']['id'], $f['prefijo']);
    $sinAncestros = exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo'], permisos: ['family_visible' => false, 'genus_visible' => false]);
    $sinCoordenadas = exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo'], permisos: ['decimal_latitude_visible' => false, 'decimal_longitude_visible' => false]);
    $reservado = exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo'], permisos: ['scientific_name_visible' => false]);
    $consulta = fn (string $id) => app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica(FiltrosBusqueda::desde(['filtroTaxonId' => $id]));
    // La reserva de coordenadas excluye el registro de toda la población pública,
    // también al filtrar por UUID. Reservar familia/género conserva la especie pública.
    expect($consulta($rama['especie']['id'])->pluck('te.id')->all())->toEqualCanonicalizing([$f['registros'][0][0], $subespecie, $sinAncestros])
        ->not->toContain($sinCoordenadas, $reservado)
        ->and($consulta($rama['familia']['id'])->pluck('te.id')->all())->toEqualCanonicalizing([$f['registros'][0][0], $subespecie])
        ->not->toContain($sinAncestros, $sinCoordenadas, $reservado)
        ->and($consulta($rama['genero']['id'])->pluck('te.id')->all())->toEqualCanonicalizing([$f['registros'][0][0], $subespecie])
        ->not->toContain($sinAncestros, $sinCoordenadas, $reservado);
    $encontrados = $servicio->consultar(FiltrosBusqueda::vacio(), null, $rama['especie']['nombre']);
    $especies = array_filter($encontrados['nodos'], fn ($nodo) => $nodo['id'] === $rama['especie']['id']);
    expect($especies)->not->toBeEmpty();
    foreach ($especies as $nodo) expect($nodo['total'])->toBe(3);
    $pagina = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->call('seleccionarTaxonExplorador', $rama['especie']['id'])
        ->assertSet('filtroTaxonId', $rama['especie']['id'])->assertSet('nivel', '')->assertSet('taxon', '')
        ->assertReturned(fn ($d) => $d['total'] === 3 && $d['hoja'] === true)
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 3 && array_sum(array_column($d['mapa'], 'total')) === 3);
    $seleccion = SeleccionPaginaChat::desde($pagina->instance()->seleccionPublicaChat);
    expect($seleccion->filtros->taxonId)->toBe($rama['especie']['id']);
    $pagina->call('retirarCriterio', 'filtroTaxonId')->assertSet('filtroTaxonId', '');
});

test('Enter acepta un nombre exacto público y rechaza coincidencias ambiguas o fuera del contexto actual', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][0];
    $pagina = Livewire::withQueryParams(['vista' => 'mapa', 'fprov' => 'Pichincha'])->test(PortalCatalogo::class)
        ->call('confirmarTaxonExplorador', '  '.mb_strtoupper($rama['especie']['nombre']).'  ')
        ->assertSet('filtroTaxonId', $rama['especie']['id'])->assertSet('vista', 'mapa')
        ->assertReturned(fn ($d) => $d['seleccionado'] === $rama['especie']['id'] && $d['total'] === 1);
    $pagina->call('seleccionarTaxonExplorador', $f['ramas'][1]['especie']['id'])
        ->assertReturned(fn ($d) => isset($d['error']))->assertSet('filtroTaxonId', $rama['especie']['id']);
    $pagina->call('confirmarTaxonExplorador', 'No existe ese taxón')->assertReturned(fn ($d) => isset($d['error']))
        ->assertSet('filtroTaxonId', $rama['especie']['id']);
    // La unicidad permite homónimos entre rangos: el nombre por sí solo no identifica uno.
    $otro = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $otro, 'padre_id' => $rama['genero']['id'], 'rango' => 'subespecie', 'nombre_cientifico' => $rama['especie']['nombre']]);
    exploradorInsertarRegistro($otro, $f['prefijo']);
    $pagina->call('confirmarTaxonExplorador', $rama['especie']['nombre'])->assertReturned(fn ($d) => isset($d['error']))
        ->assertSet('filtroTaxonId', $rama['especie']['id']);
});

test('el explorador conserva otros filtros y su identidad en el enlace sin aceptar un UUID desde el borrador', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][0];
    $pagina = Livewire::withQueryParams(['vista' => 'registros', 'fprov' => 'Pichincha', 'fco' => 'QA explorador'])->test(PortalCatalogo::class)
        ->call('seleccionarTaxonExplorador', $rama['orden']['id'])
        ->assertSet('vista', 'mapa')->assertSet('filtroProvincia', 'Pichincha')->assertSet('filtroColector', 'QA explorador');
    $pagina->call('consultarExploradorTaxonomico')->assertReturned(fn ($d) => count($d['nodos']) === 1 && $d['seleccionado'] === $rama['orden']['id']);
    $pagina->set('borradorFiltros.filtroTaxonId', $f['ramas'][1]['orden']['id'])->assertSet('filtroTaxonId', $rama['orden']['id']);
    $parametros = $pagina->instance()->seleccionPublicaChat;
    expect($parametros['fti'])->toBe($rama['orden']['id']);
    Livewire::withQueryParams($parametros + ['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->assertSet('filtroTaxonId', $rama['orden']['id'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 1);
    Livewire::withQueryParams(array_replace($parametros, ['vista' => 'mapa', 'fti' => strtoupper($rama['orden']['id'])]))->test(PortalCatalogo::class)
        ->assertSet('filtroTaxonId', $rama['orden']['id']);
});

test('una selección agrupada con búsquedas renderiza el mapa y los gráficos con la población seleccionada', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1];
    $pagina = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 3);
    $pagina->update(calls: [
        ['method' => 'consultarExploradorTaxonomico', 'params' => [null, ''], 'path' => ''],
        ['method' => 'seleccionarTaxonExplorador', 'params' => [$rama['especie']['id']], 'path' => ''],
        ['method' => 'consultarExploradorTaxonomico', 'params' => [null, $rama['especie']['nombre']], 'path' => ''],
    ])->assertSet('filtroTaxonId', $rama['especie']['id'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2 && array_sum($d['filos']) === 2);
    expect($pagina->effects)->toHaveKey('html');
});
