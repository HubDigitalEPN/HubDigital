<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\RegistrosTaxonExplorador;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

test('un nombre hecho de NBSP detiene el linaje público antes de dibujar nodos vacíos', function (): void {
    $f = exploradorTaxonomicoFixture(1); $rama = $f['ramas'][0];
    DB::table('taxonomia.taxones')->where('id', $rama['genero']['id'])->update(['nombre_cientifico' => "\u{00A0}"]);
    $respuesta = $this->getJson(route('portal.explorador', ['completo' => 1]))->assertOk()->json();
    expect(array_column($respuesta['nodos'], 'id'))->toContain($rama['familia']['id'])
        ->not->toContain($rama['genero']['id'], $rama['especie']['id']);
    expect(array_filter($respuesta['nodos'], fn ($n) => trim($n['nombre']) === '' || $n['nombre'] === "\u{00A0}"))->toBeEmpty();
    expect(DB::table('taxonomia.taxones')->where('id', $rama['genero']['id'])->value('nombre_cientifico'))->toBe("\u{00A0}");
});

test('las sugerencias traducen fragmentos españoles con tildes sin salir de la rama ni revelar taxones ocultos', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1];
    DB::table('taxonomia.taxones')->where('id', $rama['orden']['id'])->update(['nombre_cientifico' => 'Hymenoptera']);
    foreach (['himenópt', 'HIMENOPT', 'Hymenop'] as $busqueda) {
        $respuesta = $this->getJson(route('portal.explorador', ['busqueda' => $busqueda]))->assertOk()->json();
        expect(array_column(array_filter($respuesta['nodos'], fn ($n) => $n['coincide']), 'id'))->toBe([$rama['orden']['id']]);
    }
    $fuera = $this->getJson(route('portal.explorador', ['busqueda' => 'himenópt', 'taxon' => $f['ramas'][0]['clase']['id']]))->assertOk()->json();
    expect(array_filter($fuera['nodos'], fn ($n) => $n['coincide']))->toBeEmpty();
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][1])->update(['scientific_name_visible' => false]);
    $oculto = $this->getJson(route('portal.explorador', ['busqueda' => 'himenópt']))->assertOk()->json();
    expect(array_column($oculto['nodos'], 'id'))->not->toContain($rama['orden']['id']);
});

test('la apertura HTTP del árbol es pública e independiente y las sugerencias permiten filtrar por identidad', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1];
    $this->getJson(route('portal.explorador', ['fprov' => 'Pichincha', 'fti' => $f['ramas'][0]['especie']['id']]))
        ->assertOk()->assertHeader('X-Portal-Public-Cache', 'data')->assertJsonPath('total', 3)->assertJsonCount(2, 'nodos');
    $respuesta = $this->getJson(route('portal.explorador', ['busqueda' => $rama['especie']['nombre']]))->assertOk()->json();
    expect(array_column(array_filter($respuesta['nodos'], fn ($n) => $n['coincide']), 'id'))->toBe([$rama['especie']['id']]);
    $seleccion = $this->getJson(route('portal.explorador', ['taxon' => $rama['especie']['id']]))->assertOk()
        ->assertJsonPath('total', 2)->assertJsonPath('seleccionado', $rama['especie']['id'])->json();
    expect($seleccion['expandidos'])->toHaveCount(5)->and(array_column($seleccion['nodos'], 'id'))->toContain($rama['especie']['id']);
    $familia = $this->getJson(route('portal.explorador', ['taxon' => $rama['familia']['id']]))->assertOk()->json();
    $porId = array_column($familia['nodos'], null, 'id');
    expect($familia['expandidos'])->toHaveCount(3)->not->toContain($porId[$rama['familia']['id']]['clave'], $porId[$rama['genero']['id']]['clave']);
    $this->getJson(route('portal.explorador', ['taxon' => (string) Str::uuid()]))->assertNotFound();
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][1])->update(['scientific_name_visible' => false]);
    $this->getJson(route('portal.explorador', ['taxon' => $rama['especie']['id']]))->assertNotFound();
});

test('la estructura completa de ETE agrega sólo taxones públicos y conserva permisos y filtro propio', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1];
    $respuesta = $this->getJson(route('portal.explorador', ['completo' => 1, 'fprov' => 'Sin registros']))
        ->assertOk()->assertHeader('X-Portal-Public-Cache', 'data')->assertJsonPath('completo', true)
        ->assertJsonPath('total', 3)->json();
    expect(array_column($respuesta['nodos'], 'id'))->toContain($f['ramas'][0]['especie']['id'], $rama['especie']['id']);
    $seleccion = $this->getJson(route('portal.explorador', ['completo' => 1, 'taxon' => $rama['familia']['id']]))
        ->assertOk()->assertJsonPath('total', 2)->json();
    $porId = array_column($seleccion['nodos'], null, 'id');
    expect($seleccion['expandidos'])->toContain($porId[$rama['familia']['id']]['clave'])
        ->and($porId)->not->toHaveKey($f['ramas'][0]['especie']['id']);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][1])
        ->update(['genus_visible' => false, 'family_visible' => false]);
    $oculto = $this->getJson(route('portal.explorador', ['completo' => 1]))->assertOk()->json();
    expect(array_column($oculto['nodos'], 'id'))->not->toContain($rama['familia']['id'], $rama['genero']['id']);
});

test('ver en el mapa desde el árbol reemplaza filtros anteriores y usa los puntos del nodo público', function (): void {
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    $f = exploradorTaxonomicoFixture(2); $id = $f['ramas'][1]['especie']['id'];
    Livewire::withQueryParams(['vista' => 'registros', 'fprov' => 'Pichincha', 'fco' => 'No existe'])
        ->test(PortalCatalogo::class)->call('verTaxonExploradorEnMapa', $id)
        ->assertSet('vista', 'mapa')->assertSet('filtroProvincia', '')->assertSet('filtroColector', '')
        ->assertSet('filtroTaxonId', $id)->assertReturned(fn ($d) => $d['total'] === 2)
        ->assertViewHas('datosMapa', fn ($d) => $d['resumen']['registros'] === 2 && array_sum(array_column($d['mapa'], 'total')) === 2);
});

test('el dibujo ETE recibe sólo la jerarquía pública y reutiliza caché sin admitir una rama retirada', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1];
    $estructura = $this->getJson(route('portal.explorador', ['completo' => 1]))->assertOk()->json();
    $porId = array_column($estructura['nodos'], null, 'id');
    $clave = $porId[$rama['familia']['id']]['clave'];
    config(['portal-ete.url' => 'http://127.0.0.1:8001']);
    Http::fake(['http://127.0.0.1:8001/render' => Http::response([
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"/>',
        'viewBox' => [0, 0, 100, 100], 'nodos' => [], 'motor' => 'ETE 3.1.3', 'total' => 0,
    ])]);
    $parametros = ['vista' => 'semicircular', 'escala' => 120, 'rama' => $clave, 'expandidos' => [$clave, 'rama-no-publica'], 'fprov' => 'Sin registros'];
    $this->getJson(route('portal.explorador-dibujo', $parametros))->assertOk()
        ->assertHeader('X-Portal-Public-Cache', 'data')
        ->assertJsonPath('motor', 'ETE 3.1.3');
    Http::assertSent(fn ($solicitud) => $solicitud['rama'] === $clave && $solicitud['expandidos'] === [$clave]
        && count($solicitud['nodos']) === count($estructura['nodos'])
        && in_array($f['ramas'][0]['especie']['id'], array_column($solicitud['nodos'], 'id'), true));
    $this->getJson(route('portal.explorador-dibujo', $parametros))->assertOk();
    Http::assertSentCount(1);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][1])->update(['scientific_name_visible' => false]);
    $this->getJson(route('portal.explorador-dibujo', $parametros))->assertNotFound();
    Http::assertSentCount(1);
});

test('el dibujo ETE devuelve un error recuperable sin publicar el error del servicio interno', function (): void {
    $f = exploradorTaxonomicoFixture(1);
    config(['portal-ete.url' => 'http://127.0.0.1:8001']);
    Http::fake(['http://127.0.0.1:8001/render' => Http::response(['error' => 'Detalle interno reservado'], 503)]);
    $this->getJson(route('portal.explorador-dibujo', ['vista' => 'rectangular', 'escala' => 80, 'taxon' => $f['ramas'][0]['especie']['id']]))
        ->assertStatus(503)->assertJsonPath('error', 'El visor ETE no está disponible. Vuelve a intentarlo.')
        ->assertDontSee('Detalle interno reservado');
});

test('ETE recibe la vista automática y la raíz de segundo nivel sin compartir caché con una vista explícita', function (): void {
    $f = exploradorTaxonomicoFixture(1);
    $estructura = $this->getJson(route('portal.explorador', ['completo' => 1]))->assertOk()->json();
    $porId = array_column($estructura['nodos'], null, 'id');
    $clave = $porId[$f['ramas'][0]['clase']['id']]['clave'];
    config(['portal-ete.url' => 'http://127.0.0.1:8001']);
    Http::fake(['http://127.0.0.1:8001/render' => Http::response(['motor' => 'ETE 3.1.3', 'vista' => 'semicircular', 'raiz' => [50, 80], 'nodos' => [], 'total' => 0])]);
    $parametros = ['vista' => 'auto', 'escala' => 120, 'rama' => $clave, 'expandidos' => [$clave]];
    $this->getJson(route('portal.explorador-dibujo', $parametros))->assertOk()->assertJsonPath('vista', 'semicircular');
    $this->getJson(route('portal.explorador-dibujo', $parametros))->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($solicitud) => $solicitud['vista'] === 'auto' && $solicitud['rama'] === $clave);
    $this->getJson(route('portal.explorador-dibujo', array_replace($parametros, ['vista' => 'circular'])))->assertOk();
    Http::assertSentCount(2);
    $this->getJson(route('portal.explorador-dibujo', array_replace($parametros, ['vista' => 'ajena'])))->assertUnprocessable();
    Http::assertSentCount(2);
});

test('el modal de registros pagina sólo el taxón elegido y oculta los campos reservados de su ficha', function (): void {
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][0];
    foreach (range(1, 25) as $i) exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo']);
    $reservado = exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo'], permisos: ['recorded_by_visible' => false, 'locality_name_visible' => false]);
    DB::table('taxonomia.especimenes')->where('id', $reservado)->update(['colector' => 'Colector secreto exclusivo', 'locality_name' => 'Localidad secreta exclusiva', 'localidad' => 'Localidad secreta exclusiva']);
    $modal = Livewire::test(RegistrosTaxonExplorador::class)->assertViewHas('registros', [])
        ->assertDontSee('class="atlas-record-table"', false)
        ->call('abrir', $rama['especie']['id'])->assertDispatched('registros-taxon-abiertos')
        ->assertSet('taxonId', $rama['especie']['id'])->assertViewHas('paginacion', fn ($p) => $p['total'] === 27 && $p['ultima'] === 2)
        ->assertViewHas('registros', fn ($r) => count($r) === 24 && !in_array($f['registros'][1][0], array_column($r, 'especimen_id')));
    $modal->call('cambiarPagina', 2)->assertViewHas('registros', fn ($r) => count($r) === 3)
        ->call('abrirFichaRegistro', $reservado)->assertSet('registroFichaId', $reservado)
        ->assertDontSee('Colector secreto exclusivo')->assertDontSee('Localidad secreta exclusiva')
        ->assertViewHas('paginacion', fn ($p) => $p['pagina'] === 2);
    $modal->call('cerrarFichaRegistro')->assertSet('registroFichaId', null)->assertSet('pagina', 2);
    $modal->call('ajustarRegistrosPorPagina', 15)->assertSet('pagina', 1)
        ->assertViewHas('registros', fn ($r) => count($r) === 15)
        ->assertSee('Acciones')->assertSee('Paginación de Registros del taxón');
    Livewire::test(RegistrosTaxonExplorador::class)->call('abrir', $rama['especie']['id'])
        ->call('abrirFichaRegistro', $f['registros'][1][0])->assertNotFound();
});

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

test('el panel Filo depende del Taxón confirmado y permite seleccionar hijos sin cambiar de vista', function (): void {
    $f = exploradorTaxonomicoFixture(2); $primera = $f['ramas'][0]; $segunda = $f['ramas'][1];
    $pagina = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class);
    expect($pagina->instance()->arbolFiloPanel['nodos'])->toHaveCount(2);
    $pagina->call('seleccionarNodoFilo', $primera['phylum']['id'])->call('seleccionarNodoFilo', $primera['clase']['id'])
        ->assertSet('filtroTaxonId', $primera['clase']['id'])->assertSet('vista', 'mapa');
    $panelAplicado = $pagina->instance()->arbolFiloPanel;
    expect(array_column(array_filter($panelAplicado['nodos'], fn ($n) => $n['padre'] === null), 'id'))
        ->toEqualCanonicalizing([$primera['phylum']['id'], $segunda['phylum']['id']]);
    $pagina->set('borradorFiltros.filtroTaxon', $segunda['especie']['nombre']);
    expect($pagina->instance()->arbolFiloPanel)->toBe($panelAplicado);
    $pagina->assertSet('filtroTaxonId', $primera['clase']['id'])->assertSet('filtroTaxon', '');
    $pagina->call('aplicarBusquedaTexto', 'filtroTaxon', $segunda['especie']['nombre'])
        ->assertSet('filtroTaxonId', '')->assertSet('filtroFiloId', '')->assertSet('filtroFilos', [])
        ->assertViewHas('datosMapa', fn ($d) => $d['resumen']['registros'] === 2);
    $panelTexto = $pagina->instance()->arbolFiloPanel;
    expect(array_column($panelTexto['nodos'], 'id'))
        ->toEqualCanonicalizing(array_column(array_slice($segunda, 0, 6), 'id'))
        ->and($panelTexto['completo'])->toBeTrue()
        ->and($panelTexto['expandidos'])->toHaveCount(5)
        ->and(array_column(array_filter($panelTexto['nodos'], fn ($n) => $n['padre'] === null), 'id'))
        ->toBe([$segunda['phylum']['id']]);
    foreach ($panelTexto['nodos'] as $nodo) {
        expect($nodo['total'])->toBe(2);
        if ($nodo['tieneHijos']) expect($panelTexto['expandidos'])->toContain($nodo['clave']);
    }
});

test('elegir un hijo de otro filo retira las selecciones incompatibles', function (): void {
    $f = exploradorTaxonomicoFixture(2);
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->call('seleccionarNodoFilo', $f['ramas'][0]['phylum']['id'])
        ->call('seleccionarNodoFilo', $f['ramas'][1]['clase']['id'])
        ->assertSet('filtroTaxonId', $f['ramas'][1]['clase']['id'])->assertSet('vista', 'mapa')
        ->assertSet('filtroFiloId', '')->assertSet('filtroFilos', [])
        ->assertViewHas('datosMapa', fn ($d) => $d['resumen']['registros'] === 2);
});

test('la ruta de ramas Filo entrega sólo descendientes públicos compatibles con el texto', function (): void {
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][1]; $servicio = app(ExploradorTaxonomicoPublico::class);
    $raiz = $servicio->taxon(FiltrosBusqueda::vacio(), $rama['phylum']['id']);
    $this->getJson(route('portal.filos', ['busquedaTaxon' => $rama['especie']['nombre'], 'padre' => $raiz['clave']]))
        ->assertOk()->assertHeader('X-Portal-Public-Cache', 'data')->assertJsonCount(1, 'nodos')
        ->assertJsonPath('nodos.0.id', $rama['clase']['id']);
    $this->getJson(route('portal.filos', ['busquedaTaxon' => $f['ramas'][0]['especie']['nombre'], 'padre' => $raiz['clave']]))->assertNotFound();
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['registros'][1])->update(['scientific_name_visible' => false]);
    $this->getJson(route('portal.filos', ['padre' => $raiz['clave']]))->assertNotFound();
});

test('el índice compartido evita reconstruir agregados y se renueva al cambiar la taxonomía', function (): void {
    $f = exploradorTaxonomicoFixture(1); $rama = $f['ramas'][0]; $filtros = FiltrosBusqueda::vacio();
    (new ExploradorTaxonomicoPublico)->consultar($filtros);
    DB::enableQueryLog(); DB::flushQueryLog();
    try {
        $datos = (new ExploradorTaxonomicoPublico)->consultar($filtros);
        expect(DB::getQueryLog())->toHaveCount(1);
        expect(array_column($datos['precarga'][$datos['nodos'][0]['clave']], 'id'))->toBe([$rama['clase']['id']]);
    } finally {DB::disableQueryLog(); DB::flushQueryLog();}
    $nombre = $rama['phylum']['nombre'].' actualizado';
    DB::table('taxonomia.taxones')->where('id', $rama['phylum']['id'])->update(['nombre_cientifico' => $nombre]);
    expect((new ExploradorTaxonomicoPublico)->consultar($filtros)['nodos'][0]['nombre'])->toBe($nombre);
});

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
    expect($inicial['nodos'][0]['hijos'])->toBe(1)->and($inicial['nodos'][0]['linaje'])->toBe($f['ramas'][0]['phylum']['nombre']);
    expect($hijos['nodos'])->toHaveCount(1)->and($hijos['nodos'][0]['rango'])->toBe('clase')
        ->and($hijos['nodos'][0]['padre'])->toBe($inicial['nodos'][0]['clave'])
        ->and($hijos['nodos'][0]['linaje'])->toBe($f['ramas'][0]['phylum']['nombre'].' → '.$f['ramas'][0]['clase']['nombre']);
    exploradorTaxonomicoFixture(1);
    expect($servicio->consultar(FiltrosBusqueda::vacio())['nodos'])->toHaveCount(5);
    $acotado = $servicio->consultar(FiltrosBusqueda::desde(['filtroFilos' => [$f['ramas'][0]['phylum']['id']]]));
    expect($acotado['nodos'])->toHaveCount(1)->and($acotado['nodos'][0]['padre'])->toBeNull();
});

test('reabrir recupera los nodos cargados con contadores vigentes sin recuperar taxones reservados ni fuera del contexto', function (): void {
    $f = exploradorTaxonomicoFixture(1); $rama = $f['ramas'][0]; $servicio = app(ExploradorTaxonomicoPublico::class);
    $ruta = $servicio->consultar(FiltrosBusqueda::vacio(), null, $rama['especie']['nombre']);
    $claves = [...array_column($ruta['nodos'], 'clave'), 'clave-ajena-no-publicada'];
    $otro = exploradorInsertarRegistro($rama['especie']['id'], $f['prefijo']);
    $reabierto = $servicio->consultar(FiltrosBusqueda::vacio(), null, '', $claves);
    expect(array_column($reabierto['nodos'], 'id'))->toEqualCanonicalizing(array_column($ruta['nodos'], 'id'));
    foreach ($reabierto['nodos'] as $nodo) expect($nodo['total'])->toBe(2);
    expect($servicio->consultar(FiltrosBusqueda::desde(['filtroProvincia' => 'Napo']), null, '', $claves)['nodos'])->toBe([]);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', [$f['registros'][0][0], $otro])->update(['scientific_name_visible' => false]);
    expect($servicio->consultar(FiltrosBusqueda::vacio(), null, '', $claves)['nodos'])->toBe([]);
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
    expect(array_any($especies, fn ($nodo) => !str_contains($nodo['linaje'], $rama['familia']['nombre']) && !str_contains($nodo['linaje'], $rama['genero']['nombre'])))->toBeTrue()
        ->and(array_any($especies, fn ($nodo) => str_contains($nodo['linaje'], $rama['familia']['nombre']) && str_contains($nodo['linaje'], $rama['genero']['nombre'])))->toBeTrue();
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
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    $f = exploradorTaxonomicoFixture(2); $rama = $f['ramas'][0];
    $pagina = Livewire::withQueryParams(['vista' => 'registros', 'fprov' => 'Pichincha', 'fco' => 'QA explorador'])->test(PortalCatalogo::class)
        ->call('seleccionarTaxonExplorador', $rama['orden']['id'])
        ->assertSet('vista', 'mapa')->assertSet('filtroProvincia', 'Pichincha')->assertSet('filtroColector', 'QA explorador');
    $pagina->call('consultarExploradorTaxonomico')->assertReturned(fn ($d) => count($d['nodos']) === 2 && $d['seleccionado'] === '' && $d['total'] === 3);
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
