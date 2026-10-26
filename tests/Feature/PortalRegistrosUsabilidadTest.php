<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ColumnasRegistroPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

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
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    registrosParaContratoChat();
    DB::table('taxonomia.columnas_portal_publico')->delete();
    $servicio = app(ColumnasRegistroPublico::class);
    expect($servicio->visibles())->toHaveCount(31)
        ->and(array_column($servicio->visibles(), 'campo'))->toContain('disposition');
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['colector' => 'COLECTOR-RESERVADO-FICHA',
        'locality_name' => 'LOCALIDAD-PUBLICA-FICHA', 'localidad_verbatim' => 'ORIGINAL-PUBLICO-FICHA']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['recorded_by_visible' => false]);

    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA3-CHAT-1'])->test(PortalCatalogo::class);
    $dom = domRegistrosPublicos($catalogo->html());
    expect($dom->query('//table[@class="atlas-record-table"]/thead/tr/th')->length)->toBe(31)
        ->and($dom->query('//table[@class="atlas-record-table"]/thead/tr/th[normalize-space(.)="disposition"]')->length)->toBe(1)
        ->and($dom->query('//dialog[contains(@class,"portal-record-dialog")]')->length)->toBe(1);

    $servicio->actualizar(['occurrence_id', 'scientific_name', 'recorded_by']);
    $catalogo->call('$refresh')->assertDontSee('COLECTOR-RESERVADO-FICHA');
    $dom = domRegistrosPublicos($catalogo->html());
    $titulos = array_map(fn (DOMNode $n): string => trim($n->textContent), iterator_to_array($dom->query('//table[@class="atlas-record-table"]/thead/tr/th')));
    expect($titulos)->toBe(['occurrenceID', 'scientificName', 'recordedBy'])
        ->and($dom->query('//table[@class="atlas-record-table"]//tr[@tabindex="0"]')->length)->toBe(1)
        ->and($dom->query('//table[@class="atlas-record-table"]//th[@scope="row"]')->item(0)->textContent)->toBe('QA3-CHAT-1');

    $catalogo->call('abrirFichaRegistro', $id)->assertSet('registroFichaId', $id)->assertDispatched('abrir-ficha-registro')
        ->assertDontSee('COLECTOR-RESERVADO-FICHA')->assertSee('localityName')->assertSee('LOCALIDAD-PUBLICA-FICHA')
        ->assertSee('verbatimLocality')->assertSee('ORIGINAL-PUBLICO-FICHA');
    expect($catalogo->instance()->fichaRegistro['registro']->recorded_by)->toBeNull();
    $catalogo->call('cerrarFichaRegistro')->assertSet('registroFichaId', null);
});

test('las columnas taxonómicas completan nombres y conservan las reservas y la identificación provisional', function (): void {
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    jerarquiaGenerosChat();
    DB::table('taxonomia.columnas_portal_publico')->delete();
    $ejemplar = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->first();
    $genero = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius')->first();
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA3-CHAT-1'])->test(PortalCatalogo::class);
    $fila = $catalogo->viewData('registrosVista')[0];
    expect([$fila->phylum, $fila->class, $fila->order, $fila->family, $fila->genus, $fila->scientific_name])
        ->toBe(['Chatarthropoda', 'Chatinsecta', 'Chathymenoptera', 'Chatformicidae', 'Chatobius', 'Chatobius alpha']);

    DB::table('taxonomia.taxones')->where('id', $ejemplar->taxon_id)->update(['nombre_cientifico' => 'OtroGenero alpha']);
    $catalogo->call('$refresh');
    expect($catalogo->viewData('registrosVista')[0]->scientific_name)->toBe('OtroGenero alpha');
    DB::table('taxonomia.taxones')->where('id', $ejemplar->taxon_id)->update(['nombre_cientifico' => 'alpha']);
    $catalogo->call('$refresh');
    expect($catalogo->viewData('registrosVista')[0]->scientific_name)->toBe('Chatobius alpha');
    DB::table('taxonomia.especimenes')->where('id', $ejemplar->id)->update([
        'taxon_id' => $genero->id, 'taxon_verbatim' => 'Chatobius_ACY2018', 'estado_revision' => 'pendiente',
    ]);
    $catalogo->call('$refresh')->assertSee('Chatobius ACY2018')->assertSee('clasificación pendiente de revisión');
    expect($catalogo->viewData('registrosVista')[0]->taxon_en_revision)->toBeTrue();
    expect(DB::table('taxonomia.especimenes')->where('id', $ejemplar->id)->value('taxon_id'))->toBe($genero->id);

    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $ejemplar->id)->update(['family_visible' => false, 'genus_visible' => false]);
    $catalogo->call('$refresh');
    $fila = $catalogo->viewData('registrosVista')[0];
    expect(get_object_vars($fila))->not->toHaveKey('family')->not->toHaveKey('genus')
        ->and($fila->phylum)->toBe('Chatarthropoda');
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $ejemplar->id)->update(['scientific_name_visible' => false]);
    $catalogo->call('$refresh')->assertDontSee('Chatobius ACY2018');
    expect($catalogo->viewData('registrosVista'))->toBe([]);
});

test('la ficha usa la selección aplicada y comprueba de nuevo coordenadas y publicación sin revelar otro registro', function (string $caso): void {
    $this->actingAs(\App\Models\User::factory()->curador()->create());
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
    $this->actingAs(\App\Models\User::factory()->curador()->create());
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

test('latitud y longitud simples se aplican al cambiar conservando cero, validación e historial', function (): void {
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    registrosParaContratoChat();
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->update(['decimal_latitude' => 0]);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update(['decimal_longitude' => -79.2]);
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'flat' => '-1', 'flax' => '1', 'flon' => '-80', 'flox' => '-78'])->test(PortalCatalogo::class)
        ->assertSet('filtroLatitud', '')->assertSet('filtroLongitud', '');
    $catalogo->set('borradorFiltros.filtroLatitud', '0');
    expect(seleccionChatRenderizada($catalogo))->toBe(['flat' => '0', 'flax' => '0', 'flon' => '-80', 'flox' => '-78']);
    $catalogo->assertHasNoErrors()->assertSet('filtroLatitud', '0')
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
    $this->actingAs(\App\Models\User::factory()->curador()->create());
    registrosParaContratoChat();
    $filo = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius')->value('padre_id');
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    $catalogo = Livewire::withQueryParams(['vista' => 'registros', 'fph' => $filo, 'fprov' => 'Pichincha', 'ffd' => '2000-05-01'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $id)->call('retirarCriterio', 'filtroFiloId')->assertSet('filtroFiloId', '')
        ->assertSet('filtroProvincia', 'Pichincha')->assertSet('filtroFechaDesde', '2000-05-01')
        ->assertSet('registroFichaId', null)->assertViewHas('totalRegistrosVista', 2);
    expect(seleccionChatRenderizada($catalogo))->toBe(['ffd' => '2000-05-01', 'fprov' => 'Pichincha']);
});
