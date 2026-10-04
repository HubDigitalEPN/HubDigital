<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ColumnasRegistroPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);

function domRegistrosPublicos(string $html): DOMXPath
{
    $anterior = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new DOMXPath($documento);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
    }
}

test('la tabla pública ofrece todas las columnas permitidas y aplica la selección del curador sin saltar flags', function (): void {
    registrosParaContratoChat();
    DB::table('taxonomia.columnas_portal_publico')->delete();
    $servicio = app(ColumnasRegistroPublico::class);
    expect($servicio->visibles())->toHaveCount(24)
        ->and(array_column($servicio->visibles(), 'campo'))->toContain('disposition');
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['colector' => 'COLECTOR-RESERVADO-FICHA',
        'locality_name' => 'LOCALIDAD-PUBLICA-FICHA', 'localidad_verbatim' => 'ORIGINAL-PUBLICO-FICHA']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['recorded_by_visible' => false]);

    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA3-CHAT-1'])->test(PortalCatalogo::class);
    $dom = domRegistrosPublicos($catalogo->html());
    expect($dom->query('//table[@class="atlas-record-table"]/thead/tr/th')->length)->toBe(26)
        ->and($dom->query('//table[@class="atlas-record-table"]/thead/tr/th[normalize-space(.)="Disposición"]')->length)->toBe(1)
        ->and($dom->query('//dialog[contains(@class,"portal-record-dialog")]')->length)->toBe(1);

    $servicio->actualizar(['occurrence_id', 'scientific_name', 'recorded_by']);
    $catalogo->call('$refresh')->assertDontSee('COLECTOR-RESERVADO-FICHA');
    $dom = domRegistrosPublicos($catalogo->html());
    $titulos = array_map(fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($dom->query('//table[@class="atlas-record-table"]/thead/tr/th')));
    expect($titulos)->toBe(['Código de catálogo', 'Identificación científica', 'Colector', 'Fotografías publicadas', 'Ficha'])
        ->and($dom->query('//table[@class="atlas-record-table"]//tr[@tabindex="0"]')->length)->toBe(1)
        ->and($dom->query('//table[@class="atlas-record-table"]//th[@scope="row"]')->item(0)->textContent)->toBe('QA3-CHAT-1');

    $catalogo->call('abrirFichaRegistro', $id)->assertSet('registroFichaId', $id)->assertDispatched('abrir-ficha-registro')
        ->assertDontSee('COLECTOR-RESERVADO-FICHA')->assertSee('Localidad registrada')->assertSee('LOCALIDAD-PUBLICA-FICHA')
        ->assertSee('Localidad original')->assertSee('ORIGINAL-PUBLICO-FICHA');
    expect($catalogo->instance()->fichaRegistro['registro']->recorded_by)->toBeNull();
    $catalogo->call('cerrarFichaRegistro')->assertSet('registroFichaId', null);
});

test('la ficha usa la selección aplicada y comprueba de nuevo coordenadas y publicación sin revelar otro registro', function (string $caso): void {
    registrosParaContratoChat();
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    $idFuera = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->value('id');
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fprov' => 'Pichincha'])->test(PortalCatalogo::class);
    if ($caso === 'fuera de selección') {
        $id = $idFuera;
    } elseif ($caso === 'latitud ausente') {
        DB::table('taxonomia.especimenes')->where('id', $id)->update(['decimal_latitude' => null]);
    } elseif ($caso === 'longitud reservada') {
        DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['decimal_longitude_visible' => false]);
    } elseif ($caso === 'no publicado') {
        $taxonPrivado = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $taxonPrivado, 'nombre_cientifico' => 'Privatobius ficha', 'rango' => 'especie']);
        DB::table('taxonomia.especimenes')->where('id', $id)->update(['taxon_id' => $taxonPrivado]);
        expect((bool) DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->value('publicado'))->toBeFalse();
    } else {
        $id = 'uuid-inválido';
    }
    $catalogo->call('abrirFichaRegistro', $id)->assertStatus(404);
})->with(['fuera de selección', 'latitud ausente', 'longitud reservada', 'no publicado', 'identificador inválido']);

test('una ficha abierta retira datos cuyo permiso cambia y se cierra al restaurar la navegación', function (): void {
    registrosParaContratoChat();
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA3-CHAT-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $id)->assertSet('registroFichaId', $id);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['decimal_latitude_visible' => false]);
    $catalogo->call('$refresh')->assertSee('Este registro ya no está disponible en la selección pública.');
    expect($catalogo->instance()->fichaRegistro)->toBeNull();
    $catalogo->call('restaurarSeleccionUrl', ['vista' => 'registros', 'fprov' => 'Esmeraldas'], 1)
        ->assertSet('registroFichaId', null)->assertSet('filtroProvincia', 'Esmeraldas');
});

test('latitud y longitud simples se aplican independientemente conservando cero, borrador e historial', function (): void {
    registrosParaContratoChat();
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->update(['decimal_latitude' => 0]);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update(['decimal_longitude' => -79.2]);
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'flat' => '-1', 'flax' => '1', 'flon' => '-80', 'flox' => '-78'])->test(PortalCatalogo::class)
        ->assertSet('filtroLatitud', '')->assertSet('filtroLongitud', '');
    $catalogo->set('borradorFiltros.filtroLatitud', '0');
    expect(seleccionChatRenderizada($catalogo))->toBe(['flat' => '-1', 'flax' => '1', 'flon' => '-80', 'flox' => '-78']);
    $catalogo->call('aplicarBorrador')->assertHasNoErrors()->assertSet('filtroLatitud', '0')
        ->assertSet('filtroLatMin', '0')->assertSet('filtroLatMax', '0')->assertViewHas('totalRegistrosVista', 1);
    expect(seleccionChatRenderizada($catalogo))->toBe(['flat' => '0', 'flax' => '0', 'flon' => '-80', 'flox' => '-78'])
        ->and($catalogo->instance()->filtrosAplicados['filtroLatitud'])->toBe('0');
    $catalogo->set('borradorFiltros.filtroLongitud', '-78.5')->call('aplicarBorrador')->assertHasNoErrors()
        ->assertSet('filtroLonMin', '-78.5')->assertSet('filtroLonMax', '-78.5')->assertViewHas('totalRegistrosVista', 1);
    $aplicada = seleccionChatRenderizada($catalogo);
    $catalogo->set('borradorFiltros.filtroLatitud', '91')->call('aplicarBorrador')->assertHasErrors('filtroLatitud')
        ->assertSet('filtroLatitud', '0')->assertSet('filtroLatMin', '0')->assertSet('filtroLatMax', '0');
    expect(seleccionChatRenderizada($catalogo))->toBe($aplicada);
    $catalogo->set('borradorFiltros.filtroLatitud', '')->call('aplicarBorrador')->assertHasNoErrors()
        ->assertSet('filtroLatMin', '')->assertSet('filtroLatMax', '')->assertViewHas('totalRegistrosVista', 3);
    expect(seleccionChatRenderizada($catalogo))->toBe(['flon' => '-78.5', 'flox' => '-78.5']);
    $catalogo->call('restaurarSeleccionUrl', $aplicada + ['vista' => 'registros'], 2)
        ->assertSet('filtroLatitud', '0')->assertSet('filtroLongitud', '-78.5')->assertViewHas('totalRegistrosVista', 1)
        ->call('limpiarFiltros')->assertSet('filtroLatitud', '')->assertSet('filtroLongitud', '');
    expect(seleccionChatRenderizada($catalogo))->toBe([]);
});

test('quitar solo el filo conserva provincia y demás filtros sin mantener la ficha abierta', function (): void {
    registrosParaContratoChat();
    $filo = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius')->value('padre_id');
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $filo, 'fprov' => 'Pichincha', 'ffd' => '2000-05-01'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $id)->call('quitarFiltroFilo')->assertSet('filtroFiloId', '')
        ->assertSet('filtroProvincia', 'Pichincha')->assertSet('filtroFechaDesde', '2000-05-01')
        ->assertSet('registroFichaId', null)->assertViewHas('totalRegistrosVista', 2);
    expect(seleccionChatRenderizada($catalogo))->toBe(['ffd' => '2000-05-01', 'fprov' => 'Pichincha']);
});
