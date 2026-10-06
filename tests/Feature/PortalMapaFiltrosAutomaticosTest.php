<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ReferenciaVisualSeleccion;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function mapaFiltrosAutomaticosFixture(): array
{
    $annelida = (string) Str::uuid(); $arthropoda = (string) Str::uuid(); $familia = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $annelida, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Annelida'],
        ['id' => $arthropoda, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Arthropoda'],
        ['id' => $familia, 'padre_id' => $annelida, 'rango' => 'familia', 'nombre_cientifico' => 'FamiliaAnelidaQa'],
    ]);
    $ids = [];
    foreach ([['Pastaza', 'Puyo', $familia], ['Loja', 'Loja centro', $familia], ['NAPO-RESERVADA', 'LOCALIDAD-RESERVADA', $familia], ['Pichincha', 'Quito', $arthropoda]] as $i => [$provincia, $localidad, $taxon]) {
        $ids[$i] = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $taxon, 'codigo_catalogo' => 'QA-AUTO-'.($i + 1), 'occurrence_id' => 'QA-AUTO-'.($i + 1),
            'fila_origen_excel' => 91000 + $i, 'state_province' => $provincia, 'localidad' => $localidad, 'locality_name' => $localidad,
            'fecha_colecta' => '2001-01-10', 'decimal_latitude' => -1.5 - $i, 'decimal_longitude' => -77.5 - $i,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true,
            'state_province_visible' => $i !== 2, 'locality_name_visible' => $i !== 2, 'event_date_visible' => $i !== 2,
        ]);
    }
    return compact('annelida', 'arthropoda', 'familia', 'ids');
}

test('cambiar filo provincia y localidad aplica de inmediato y ofrece solo geografía pública de esa selección', function (): void {
    $f = mapaFiltrosAutomaticosFixture();
    $pagina = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->set('borradorFiltros.filtroFiloId', $f['annelida'])->assertSet('filtroFiloId', $f['annelida'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 3 && count($d['mapa']) === 3)
        ->assertSee('Limpiar Filtros')->assertDontSee('Aplicar filtros')->assertDontSee('Excluir provincia')
        ->assertDontSee('Elegir ubicación original')->assertDontSee('Mostrar todos los filos')->assertDontSee('Diccionario CSV/XLSX');
    expect($pagina->instance()->provinciasDisponibles)->toBe(['Loja', 'Pastaza'])
        ->and($pagina->instance()->localidadesDisponibles)->toBe(['Loja centro', 'Puyo']);
    $pagina->set('borradorFiltros.filtroProvincia', 'Pastaza')->assertSet('filtroProvincia', 'Pastaza')
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 1);
    expect($pagina->instance()->localidadesDisponibles)->toBe(['Puyo']);
    $pagina->set('borradorFiltros.filtroGeografias.0', 'Puyo')->assertSet('filtroGeografias', ['Puyo'])
        ->call('cambiarVista', 'registros')
        ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === [$f['ids'][0]])
        ->call('quitarLocalidad', 0)->assertSet('filtroGeografias', [])->assertSet('filtroProvincia', 'Pastaza');
    $pagina->set('borradorFiltros.filtroFiloId', $f['arthropoda'])->assertSet('filtroProvincia', '')
        ->assertSee('Se retiró Provincia')->assertViewHas('totalRegistrosVista', 1)
        ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === [$f['ids'][3]])
        ->call('limpiarFiltros')->assertSet('filtroFiloId', '')->assertSet('filtroGeografias', [])->assertSet('avisoFiltrosDependientes', '')
        ->assertViewHas('totalRegistrosVista', 4);
});

test('registros identificados hasta familia aparecen en provincia década y en la hoja propia de cada punto', function (): void {
    $f = mapaFiltrosAutomaticosFixture();
    $pagina = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['annelida']])->test(PortalCatalogo::class);
    $datos = $pagina->viewData('datosMapa');
    expect($datos['riqueza'])->toBe([
        ['provincia' => 'Loja', 'especies' => 0, 'registros' => 1],
        ['provincia' => 'Pastaza', 'especies' => 0, 'registros' => 1],
    ])->and($datos['decadas'])->toBe([['decada' => 2000, 'especies' => 0, 'registros' => 2]]);
    foreach ([0, 1, 2] as $i) {
        $pagina->call('abrirCelda', -1.5 - $i, -77.5 - $i);
        $detalle = $pagina->instance()->detalleCelda;
        expect($detalle['registros_arbol'])->toHaveCount(1)
            ->and($detalle['registro_seleccionado']['especimen_id'])->toBe($f['ids'][$i])
            ->and($detalle['registro_seleccionado']['padre_id'])->toBe($f['familia'])
            ->and(array_column($detalle['registros'], 'especimen_id'))->toBe([$f['ids'][$i]])
            ->and($detalle['seleccionado']['rango'])->toBe('familia');
        $pagina->assertDontSee('Páginas de taxones')->assertDontSee('hasta 6 taxones por página')
            ->assertDontSee('NAPO-RESERVADA')->assertDontSee('LOCALIDAD-RESERVADA');
    }
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->update(['occurrence_id_visible' => false, 'scientific_name_visible' => false]);
    $pagina->call('limpiarFiltros')->call('abrirCelda', -3.5, -79.5);
    $reservado = $pagina->instance()->detalleCelda;
    expect($reservado['registro_seleccionado']['nombre'])->toBe('Registro público 1')
        ->and($reservado['registro_seleccionado']['padre_id'])->toBeNull()->and($reservado['seleccionado'])->toBeNull()
        ->and(array_column($reservado['registros'], 'especimen_id'))->toBe([$f['ids'][2]]);
    $pagina->assertDontSee('QA-AUTO-3')->assertSee('Registro público 1');
});

test('una hoja conserva su UUID y relee el linaje público antes de cargar su ficha cuando cambia la identificación', function (): void {
    $f = mapaFiltrosAutomaticosFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => -1.5, 'decimal_longitude' => -77.5]);
    $pagina = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['annelida']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -1.5, -77.5)->call('navegarCelda', 'registro:'.$f['ids'][0]);
    expect($pagina->instance()->detalleCelda['seleccionado']['taxon_id'])->toBe($f['familia']);
    $familiaNueva = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $familiaNueva, 'padre_id' => $f['annelida'], 'rango' => 'familia', 'nombre_cientifico' => 'FamiliaAnelidaActualizadaQa']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['taxon_id' => $familiaNueva]);
    $pagina->call('cambiarVistaCelda', 'grupos');
    $detalle = $pagina->instance()->detalleCelda;
    expect($detalle['registro_seleccionado']['especimen_id'])->toBe($f['ids'][0])
        ->and($detalle['registro_seleccionado']['padre_id'])->toBe($familiaNueva)
        ->and($detalle['seleccionado']['taxon_id'])->toBe($familiaNueva)
        ->and(array_column($pagina->get('rutaCelda'), 'taxon_id'))->toBe([$f['annelida'], $familiaNueva])
        ->and(array_column($detalle['registros'], 'especimen_id'))->toBe([$f['ids'][0]])
        ->and($detalle['registros_arbol']['registro:'.$f['ids'][1]]['padre_id'])->toBe($f['familia']);
});

test('la referencia de Nematomorpha explica el grupo para visitantes y usa una imagen comprimida', function (): void {
    $referencia = ReferenciaVisualSeleccion::para(['phylum' => 'Nematomorpha', 'family' => 'Gordiidae']);
    expect($referencia['url'])->toEndWith('nematomorpha-reference-20261006.webp')
        ->and($referencia['texto'])->toContain('gusanos', 'larvas', 'adultos', 'agua')->not->toContain('generada', 'IA', 'acredita una identificación')
        ->and($referencia)->not->toHaveKey('origen')
        ->and(ReferenciaVisualSeleccion::para([]))->toBeNull()
        ->and(ReferenciaVisualSeleccion::para(['phylum' => 'FiloInexistente']))->toBeNull();
});

test('un enlace antiguo de exclusión avisa y el chat rechaza esa selección retirada', function (): void {
    mapaFiltrosAutomaticosFixture();
    Livewire::withQueryParams(['vista' => 'registros', 'fxprov' => 'Pastaza', 'fprov' => 'Loja'])->test(PortalCatalogo::class)
        ->assertSee('El filtro Excluir provincia fue retirado')->assertSet('filtroProvincia', 'Loja')->assertViewHas('totalRegistrosVista', 1);
    expect(SeleccionPaginaChat::desde(['fxprov' => 'Pastaza']))->toBeNull();
});

test('solo una entrada de composición activa su fotografía y otro filtro vuelve a ocultarla', function (string $accion, array $parametros): void {
    $f = mapaFiltrosAutomaticosFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['sampling_protocol' => 'pitfall']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['sampling_protocol_visible' => true]);
    $pagina = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['annelida']])->test(PortalCatalogo::class)
        ->assertSet('mostrarFotoComposicion', false)
        ->set('borradorFiltros.filtroProvincia', 'Pastaza')->assertSet('mostrarFotoComposicion', false)
        ->call('limpiarFiltros')->call('seleccionarFilo', $f['annelida'])->assertSet('mostrarFotoComposicion', true);
    $fotografias = static function (string $html): int {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        return (new DOMXPath($dom))->query('//section[contains(@class,"atlas-taxa-panel")]//div[contains(@x-data,"portalFotografias")]')->length;
    };
    expect($fotografias($pagina->html()))->toBe(1);
    if ($accion === 'borrador') {
        $pagina->set('borradorFiltros.filtroGeografias', ['Puyo'])->assertSet('filtroGeografias', ['Puyo']);
    } else {
        $pagina->call($accion, ...$parametros);
    }
    $pagina->assertSet('mostrarFotoComposicion', false);
    expect($fotografias($pagina->html()))->toBe(0);
})->with([
    'provincia' => ['seleccionarProvincia', ['Pastaza']],
    'década' => ['seleccionarDecada', [2000]],
    'mes' => ['seleccionarMes', [1]],
    'método' => ['seleccionarMetodo', ['pitfall']],
    'elevación' => ['seleccionarAltitud', [0, 499]],
    'área del mapa' => ['seleccionarArea', [-5, 0, -81, -75]],
    'localidad LOV' => ['borrador', []],
    'limpiar' => ['limpiarFiltros', []],
    'retirar el mismo filo' => ['seleccionarFilo', ['Annelida']],
    'historial' => ['restaurarSeleccionUrl', [['vista' => 'mapa', 'fprov' => 'Loja']]],
]);
