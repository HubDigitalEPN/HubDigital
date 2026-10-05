<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function qa7ColeccionFixture(string $rango = 'especie', int $cantidad = 3): array
{
    $filo = (string) Str::uuid(); $familia = (string) Str::uuid(); $genero = (string) Str::uuid(); $taxon = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $filo, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Qaheptapoda'],
        ['id' => $familia, 'padre_id' => $filo, 'rango' => 'familia', 'nombre_cientifico' => 'Qaheptidae'],
        ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Qaheptus'],
        ['id' => $taxon, 'padre_id' => $rango === 'familia' ? $filo : ($rango === 'genero' ? $familia : $genero), 'rango' => $rango, 'nombre_cientifico' => 'Qaheptus alpha'],
    ]);
    $ids = [];
    foreach (range(0, $cantidad - 1) as $i) {
        $ids[$i] = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $taxon, 'codigo_catalogo' => 'QA7-INV-'.($i + 1), 'occurrence_id' => 'QA7-INV-'.($i + 1),
            'localidad' => 'Sitio QA7', 'locality_name' => 'Sitio QA7', 'country' => 'Ecuador',
            'state_province' => $i === 2 ? 'Napo' : 'Orellana', 'fila_origen_excel' => 70000 + $i,
            'fecha_colecta' => '1994-01-10', 'colector' => 'Colectora QA7', 'sampling_protocol' => 'hand',
            'decimal_latitude' => -1.8910422, 'decimal_longitude' => -77.765439,
            'life_stage' => 'adult', 'type_status' => $i === 0 ? 'paratype' : null,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filo', 'familia', 'genero', 'taxon', 'ids');
}

function qa7ParametrosRespuesta(array $respuesta): array
{
    parse_str((string) parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $parametros);
    return $parametros;
}

test('QA7 001 inclusión conserva conteo enlace y UUID y la exclusión retirada no produce consultas parciales', function (): void {
    $fixture = qa7ColeccionFixture();
    $consulta = app(ConsultaCatalogoPublico::class);
    $positiva = $consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Orellana?');
    expect($positiva['datos']['total'])->toBe(2);
    expect(qa7ParametrosRespuesta($positiva))->toMatchArray(['ft' => 'Qaheptus alpha', 'fprov' => 'Orellana'])->not->toHaveKey('fxprov');
    Livewire::withQueryParams(qa7ParametrosRespuesta($positiva))->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === array_slice($fixture['ids'], 0, 2));
    $napo = $consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Napo?');
    expect($napo['datos']['total'])->toBe(1);
    Livewire::withQueryParams(qa7ParametrosRespuesta($napo))->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === [$fixture['ids'][2]])
        ->call('cambiarVista', 'mapa')->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 1);
    foreach (['¿Cuántos registros de Qaheptus alpha hay fuera de Orellana?', '¿Cuántos registros no son de Orellana?'] as $pregunta) {
        $negativa = $consulta->responder($pregunta, $positiva['entidades']);
        expect($negativa['intent'])->toBe('catalogo.aclaracion')->and($negativa)->not->toHaveKey('datos')
            ->and($negativa['opciones'])->toBe([])->and($negativa['texto'])->toContain('exclusión de provincias ya no está disponible');
    }
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['state_province' => 'Orellana']);
    $vacia = $consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Napo?');
    expect($vacia['datos']['total'])->toBe(0)
        ->and($vacia['entidades'])->toMatchArray(['taxon' => 'Qaheptus alpha', 'provincia' => 'Napo'])
        ->and(qa7ParametrosRespuesta($vacia))->toMatchArray(['ft' => 'Qaheptus alpha', 'fprov' => 'Napo']);
    Livewire::withQueryParams(qa7ParametrosRespuesta($vacia))->test(PortalCatalogo::class)
        ->assertSet('filtroProvincia', 'Napo')->assertViewHas('totalRegistrosVista', 0)->assertViewHas('registrosVista', []);
});

test('una provincia territorial sin datos públicos conserva el criterio y no se convierte en taxón', function (string $provincia): void {
    $fixture = qa7ColeccionFixture();
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['state_province_visible' => false]);
    $consulta = app(ConsultaCatalogoPublico::class);
    foreach (['¿Cuántos registros hay en '.$provincia.'?', '¿Cuántos registros hay en la provincia de '.$provincia.'?',
        '¿Cuántos registros hay en '.mb_strtolower($provincia).'?'] as $pregunta) {
        $respuesta = $consulta->responder($pregunta);
        expect($respuesta['entidades'])->toMatchArray(['provincia' => $provincia])->not->toHaveKey('taxon')
            ->and($respuesta['datos']['total'])->toBe(0)
            ->and(qa7ParametrosRespuesta($respuesta))->toMatchArray(['fprov' => $provincia])->not->toHaveKey('ft')
            ->and($respuesta['texto'])->not->toContain('QA7-INV-');
    }
})->with(['Napo', 'El Oro', 'Santo Domingo de los Tsáchilas']);

test('QA7 001 una provincia desconocida o reservada no acredita una exclusión', function (): void {
    $fixture = qa7ColeccionFixture();
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['state_province' => null]);
    $consulta = app(ConsultaCatalogoPublico::class);
    expect($consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Orellana?')['datos']['total'])->toBe(2);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['state_province' => 'desconocida']);
    expect($consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Orellana?')['datos']['total'])->toBe(2);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['state_province' => 'Napo']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $fixture['ids'][2])->update(['state_province_visible' => false]);
    expect($consulta->responder('¿Cuántos registros de Qaheptus alpha hay en Orellana?')['datos']['total'])->toBe(2);
    $ambigua = $consulta->responder('¿Cuántos registros de Qaheptus alpha hay fuera de Provincia inexistente?');
    expect($ambigua['intent'])->toBe('catalogo.aclaracion')->and($ambigua)->not->toHaveKey('datos');
    foreach (['fuera de Orellana y Napo', 'fuera del Ecuador'] as $exclusion) {
        $ambigua = $consulta->responder('¿Cuántos registros de Qaheptus alpha hay '.$exclusion.'?');
        expect($ambigua['intent'])->toBe('catalogo.aclaracion')->and($ambigua)->not->toHaveKey('datos');
    }
});

test('QA7 002 003 las acciones conservan filtros y los formatos nunca se convierten en taxones', function (string $pregunta, string $intent, string $vista): void {
    qa7ColeccionFixture(); Http::preventStrayRequests();
    $seleccion = ['nivel' => 'species', 'taxon' => 'Qaheptus alpha', 'ft' => 'Qaheptus alpha', 'fprov' => 'Orellana', 'fsti' => 'paratype',
        'flat' => '-2', 'flax' => '-1', 'flon' => '-78', 'flox' => '-77', 'fmes' => '1'];
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), seleccionPortal: $seleccion);
    expect($respuesta['intent'])->toBe($intent)->and(qa7ParametrosRespuesta($respuesta))->toEqual($seleccion + ['vista' => $vista])
        ->and($respuesta['texto'])->not->toContain('Taxón = Excel')->not->toContain('Taxón = Después');
    Http::assertNothingSent();
})->with([
    ['dame pasos para descargar estos dos registros en Excel', 'portal.xlsx', 'tarjetas'],
    ['¿Cómo descargo los datos XLSX de esta especie?', 'portal.xlsx', 'tarjetas'],
    ['quiero descargar Excel', 'portal.xlsx', 'tarjetas'],
    ['No quiero quitar los filtros; dame pasos para volver al mapa', 'portal.cambiar_vista', 'mapa'],
    ['Volver al mapa sin quitar filtros', 'portal.cambiar_vista', 'mapa'],
    ['Vuelve al mapa sin quitar ningún filtro', 'portal.cambiar_vista', 'mapa'],
    ['Regresa al mapa sin limpiar los filtros', 'portal.cambiar_vista', 'mapa'],
    ['Abre el mapa sin restablecer los filtros', 'portal.cambiar_vista', 'mapa'],
    ['No quitar los filtros; volver al mapa', 'portal.cambiar_vista', 'mapa'],
    ['No quiero cambiar filtros, solo abrir el mapa', 'portal.cambiar_vista', 'mapa'],
    ['dame pasos para arreglar que no salgan resultados', 'portal.resultados_vacios', 'registros'],
]);

test('QA7 002 los pasos de depósito indican autenticación y el destino de trámite', function (string $pregunta): void {
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'])->toBe('portal.deposito_pasos')
        ->and($respuesta['texto'])->toContain('Inicia sesión', 'Depositante', 'Curaduría')->not->toContain('No necesitas cuenta')
        ->and($respuesta['opciones'][0]['url'])->toBe(route('depositos.portal'));
    Http::assertNothingSent();
})->with(['dame pasos para depositar material biológico', 'Necesito instrucciones paso a paso para un depósito temporal']);

test('QA7 un procedimiento compuesto tampoco convierte exclusión en inclusión', function (): void {
    qa7ColeccionFixture(); Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder('Dame pasos para buscar Qaheptus alpha fuera de Orellana y descargar los datos', app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'])->toBe('catalogo.aclaracion')->and($respuesta['opciones'])->toBe([])
        ->and($respuesta['texto'])->toContain('exclusión de provincias ya no está disponible', 'No he preparado filtros parciales');
    Http::assertNothingSent();
});

test('QA7 004 la selección resuelve elipsis, vacío, paratipos y localidad sin fuentes externas', function (): void {
    qa7ColeccionFixture(); Http::preventStrayRequests();
    $chat = Livewire::test(ChatBotWidget::class);
    $seleccion = ['ft' => 'Qaheptus alpha', 'fprov' => 'Orellana'];
    $chat->set('pregunta', '¿Cuántos registros hay en esta selección?')->call('enviar', $seleccion);
    $chat->set('pregunta', 'y cuántas especies son?')->call('enviar', $seleccion);
    expect($chat->get('mensajes')[3]['texto'])->toContain('Hay 1 especie', 'selección aplicada');
    $chat->set('pregunta', 'muéstrame las especies de aquí')->call('enviar', ['fc' => 'QA7-NO-EXISTE']);
    expect($chat->get('mensajes')[5]['texto'])->toContain('Hay 0 especies');
    $chat->set('pregunta', '¿Cuántos paratipos hay en esta selección?')->call('enviar', ['fc' => 'QA7-INV-1', 'fsti' => 'paratype']);
    expect($chat->get('mensajes')[7]['texto'])->toContain('Hay 1 registro', 'no de individuos');
    $chat->set('pregunta', '¿Y dónde se recolectó?')->call('enviar', ['fc' => 'QA7-INV-1']);
    expect($chat->get('mensajes')[9]['texto'])->toContain('Sitio QA7', 'Orellana');
    $chat->set('pregunta', '¿Cuántos hay?')->call('enviar', $seleccion);
    expect($chat->get('mensajes')[11]['texto'])->toContain('Qué deseas contar');
    Http::assertNothingSent();
});

test('QA7 005 006 glosario capacidades y actualidad se atienden localmente', function (string $pregunta, string $intent, string $texto): void {
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'])->toBe($intent)->and($respuesta['texto'])->toContain($texto);
    Http::assertNothingSent();
})->with([
    ['hola, qué puedo hacer aquí?', 'portal.capacidades', 'CSV/XLSX'],
    ['¿Es lo mismo condición de tipo que disposición del material?', 'portal.glosario', 'son campos distintos'],
    ['¿Qué significa tipo nomenclatural?', 'portal.glosario', 'typeStatus'],
    ['las coordenadas recuperadas del Excel son exactas?', 'portal.glosario', 'nunca cero'],
    ['¿Qué noticias ocurrieron hoy en Ecuador?', 'general.sin_actualidad', 'No puedo verificar noticias'],
    ['como filtro por provincia y por metodo de colecta?', 'portal.filtros', 'Ejemplar y colecta'],
]);

test('QA7 006 la clasificación local respeta familia y género reservados', function (): void {
    $fixture = qa7ColeccionFixture(); Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder('¿Qué es Qaheptus alpha?', $handler);
    expect($respuesta['intent'])->toBe('catalogo.clasificacion')->and($respuesta['texto'])->toContain('Qaheptapoda', 'Qaheptidae', 'Qaheptus →');
    $genero = $asistente->responder('¿Qué es Qaheptus?', $handler);
    expect($genero['intent'])->toBe('catalogo.clasificacion')->and($genero['texto'])->toContain('género', '3 registros públicos');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['family_visible' => false, 'genus_visible' => false]);
    $respuesta = $asistente->responder('¿Qué es Qaheptus alpha?', $handler);
    expect($respuesta['texto'])->not->toContain('Qaheptidae')->not->toContain('Qaheptus →');
    Http::assertNothingSent();
});

test('QA7 Q35 la consulta de varios pasos conserva taxón mes y década sin convertir frases en filtros', function (): void {
    $fixture = qa7ColeccionFixture(); Http::preventStrayRequests();
    DB::table('taxonomia.taxones')->where('id', $fixture['familia'])->update(['nombre_cientifico' => 'Formicidae']);
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $hormigas = $asistente->responder('quiero ver hormigas de Orellana', $handler);
    expect($hormigas['datos']['total'])->toBe(2)->and(qa7ParametrosRespuesta($hormigas))->toMatchArray(['ft' => 'Formicidae', 'fprov' => 'Orellana']);
    $pregunta = 'Dame pasos para consultar el catálogo desde cero, buscar hormigas de Orellana, revisar solamente las recolectadas en enero de los años noventa y comparar el mapa con la tabla. Después quiero descargar los datos conservando las advertencias sobre coordenadas. No quiero crear una cuenta ni modificar registros; solo consultar datos públicos. Explícame dónde se encuentra cada control.';
    $respuesta = $asistente->responder($pregunta, $handler);
    expect($respuesta['intent'])->toBe('portal.consulta_pasos')
        ->and(qa7ParametrosRespuesta($respuesta))->toMatchArray(['ft' => 'Formicidae', 'fprov' => 'Orellana', 'fmes' => '1', 'ffd' => '1990-01-01', 'ffh' => '1999-12-31', 'vista' => 'mapa'])
        ->not->toHaveKey('fg')->and($respuesta['texto'])->toContain('Descargar resultados CSV', 'Descargar datos XLSX', 'georeferenceRemarks');
    Http::assertNothingSent();
});

test('QA7 la ayuda intermedia conserva la consulta anterior para el siguiente conteo', function (): void {
    qa7ColeccionFixture(); Http::preventStrayRequests();
    $chat = Livewire::test(ChatBotWidget::class);
    $chat->set('pregunta', '¿Cuántos registros de Qaheptus alpha hay en Orellana?')->call('enviar');
    $chat->set('pregunta', '¿Qué significa tipo nomenclatural?')->call('enviar');
    $chat->set('pregunta', 'y cuántas especies son?')->call('enviar');
    expect(app(ContextoChat::class)->obtener()['entities'])->toMatchArray(['taxon' => 'Qaheptus alpha', 'provincia' => 'Orellana']);
    $mensajes = $chat->get('mensajes');
    expect(end($mensajes)['texto'])->toContain('1 especie');
    Http::assertNothingSent();
});

test('QA7 007 los alias de estadio conservan el conjunto y los permisos de divulgación', function (string $fuente, string $etiqueta): void {
    $fixture = qa7ColeccionFixture();
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][0])->update(['life_stage' => $fuente]);
    DB::table('taxonomia.especimenes')->whereIn('id', array_slice($fixture['ids'], 1))->update(['life_stage' => null]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    foreach ([$fuente, $etiqueta, mb_strtoupper($etiqueta)] as $valor) {
        $seleccion = $repo->consultaPublica(FiltrosBusqueda::desde(['filtroEstadio' => $valor, 'filtroProvincia' => 'Orellana']))->pluck('te.id')->all();
        expect($seleccion)->toBe([$fixture['ids'][0]]);
    }
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $fixture['ids'][0])->update(['life_stage_visible' => false]);
    expect($repo->consultaPublica(FiltrosBusqueda::desde(['filtroEstadio' => $etiqueta]))->count())->toBe(0);
})->with([['adult', 'Adulto'], ['juvenile', 'Juvenil'], ['larval', 'Larva'], ['pupal', 'Pupa'], ['egg', 'Huevo'], ['nymph', 'Ninfa']]);

test('QA7 008 las hojas terminales muestran cada UUID y solo la tabla pagina seis registros públicos', function (string $rango): void {
    $fixture = qa7ColeccionFixture($rango, 7);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'ft' => 'Qaheptus alpha'])->test(PortalCatalogo::class)
        ->call('abrirCelda', -1.8910422, -77.765439)->call('navegarCelda', $fixture['taxon']);
    $detalle = $componente->get('detalleCelda');
    expect($detalle['mostrarRegistros'])->toBeTrue()->and($detalle['registros'])->toHaveCount(6)->and($detalle['ultima'])->toBe(2)
        ->and(array_column($detalle['registros_arbol'], 'especimen_id'))->toBe($fixture['ids'])
        ->and(array_unique(array_column($detalle['registros_arbol'], 'padre_id')))->toBe([$fixture['taxon']]);
    $componente->assertSee('QA7-INV-1')->assertSee('Ejemplar de la selección')
        ->assertDontSee('Páginas de ejemplares de la ubicación')->call('paginarCelda', 2)->assertSet('paginaCelda', 1);
    foreach ($fixture['ids'] as $i => $id) {
        $componente->call('navegarCelda', 'registro:'.$id)->assertSee('QA7-INV-'.($i + 1));
        $seleccionado = $componente->get('detalleCelda');
        expect($seleccionado['registro_seleccionado']['especimen_id'])->toBe($id)
            ->and($seleccionado['seleccionado']['taxon_id'])->toBe($fixture['taxon'])
            ->and(array_column($seleccionado['registros'], 'especimen_id'))->toBe([$id]);
    }
    $componente->call('cambiarVistaCelda', 'registros')->assertSet('paginaCelda', 1)->assertSee('Páginas de ejemplares de la ubicación');
    expect(array_column($componente->get('detalleCelda')['registros'], 'especimen_id'))->toBe(array_slice($fixture['ids'], 0, 6));
    $componente->call('paginarCelda', 2)->assertSet('paginaCelda', 2)->assertSee('QA7-INV-7');
    expect(array_column($componente->get('detalleCelda')['registros'], 'especimen_id'))->toBe([$fixture['ids'][6]]);
})->with(['familia', 'genero', 'especie']);

test('QA7 retirar mes, altitud o caja conserva el resto de filtros y el historial', function (): void {
    qa7ColeccionFixture();
    $componente = Livewire::withQueryParams(['vista' => 'registros', 'ft' => 'Qaheptus alpha', 'fprov' => 'Orellana', 'fmes' => '1',
        'fed' => '0', 'feh' => '499', 'flat' => '-2', 'flax' => '-1', 'flon' => '-78', 'flox' => '-77'])->test(PortalCatalogo::class);
    $componente->call('retirarCriterio', 'filtroMes')->assertSet('filtroMes', '')->assertSet('filtroProvincia', 'Orellana')->assertSet('filtroElevHasta', '499');
    $componente->call('retirarCriterio', 'elevacion')->assertSet('filtroElevDesde', '')->assertSet('filtroLatMin', '-2');
    $componente->call('retirarCriterio', 'latitud')->assertSet('filtroLatMin', '')->assertSet('filtroLonMin', '-78')->assertSet('filtroTaxon', 'Qaheptus alpha');
    $componente->call('retirarCriterio', 'vista')->assertSet('vista', 'registros');
});

test('QA7 el diccionario público distingue perfiles, INEC y vacíos', function (): void {
    $this->get(route('portal.diccionario-exportacion'))->assertOk()->assertSee('hubdigital.portal-publico/3.0')
        ->assertSee('Código INEC')->assertSee('georeferenceRemarks')->assertSee('Vacío no significa cero')->assertSee('typeStatus');
});

test('QA7 la autoridad externa no reemplaza la familia original ni divulga una familia reservada', function (): void {
    $fixture = qa7ColeccionFixture();
    DB::table('taxonomia.taxones')->where('id', $fixture['taxon'])->update(['nombre_cientifico' => 'Naesiotus eschariferus']);
    DB::table('taxonomia.taxones')->where('id', $fixture['familia'])->update(['nombre_cientifico' => 'Orthalicidae']);
    $referencia = app(\Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica::class);
    expect($referencia->para('Naesiotus eschariferus', true))->toMatchArray(['familia_referencia' => 'Bulimulidae', 'decision' => 'Pendiente de cotejo y decisión curatorial'])
        ->and($referencia->para('Naesiotus eschariferus')['familia_referencia'])->toBeNull();
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $fixture['ids'][0])->assertSee('Orthalicidae')->assertSee('Bulimulidae')->assertSee('Pendiente de cotejo');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['family_visible' => false]);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $fixture['ids'][0])->assertDontSee('Orthalicidae')->assertDontSee('Bulimulidae');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['family_visible' => true, 'scientific_name_visible' => false]);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $fixture['ids'][0])->assertDontSee('Orthalicidae')->assertDontSee('Bulimulidae')->assertDontSee('Naesiotus eschariferus');
    expect(DB::table('taxonomia.taxones')->where('id', $fixture['familia'])->value('nombre_cientifico'))->toBe('Orthalicidae');
});
