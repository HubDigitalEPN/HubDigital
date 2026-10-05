<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

test('QA9 H01 la exclusión coloquial retirada se aclara antes de contar o enlazar', function (string $pregunta): void {
    qa8HormigasFixture(); Http::preventStrayRequests();
    foreach ([[], ['taxon' => 'Camponotus', 'provincia' => 'Orellana']] as $contexto) {
        $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class),
            contextoCatalogo: $contexto, seleccionPortal: ['ft' => 'Camponotus', 'fprov' => 'Orellana']);
        expect($respuesta['intent'])->toBe('catalogo.aclaracion')->and($respuesta['opciones'])->toBe([])
            ->and($respuesta)->not->toHaveKey('datos')->and($respuesta['texto'])->toContain('exclusión de provincias ya no está disponible');
    }
    Http::assertNothingSent();
})->with([
    'No quiero Orellana, enséñame las Camponotus de las otras provincias',
    'No quiero Orellana, enséñame Camponotus',
    'Enséñame Camponotus en las demás provincias',
]);

test('QA9 H02 H03 referencias y criterios coincidentes usan todos los filtros de la página', function (string $pregunta): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos', 'fmes' => '1',
        'flat' => '-2', 'flax' => '-1', 'flon' => '-78', 'flox' => '-77'];
    foreach ([[], ['taxon' => 'Camponotus', 'provincia' => 'Orellana']] as $contexto) {
        $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class),
            contextoCatalogo: $contexto, seleccionPortal: $seleccion);
        expect(array_column($respuesta['partes'], 'accion'))->toBe(['conteo', 'descarga', 'mapa'])
            ->and($respuesta['partes'][0]['datos']['total'])->toBe(1)
            ->and($respuesta['entidades'])->toBe([])->and($respuesta['solicitud_pendiente'])->toBeNull()
            ->and($respuesta['texto'])->not->toContain('Para estos', 'criterios nombrados difieren');
        expect(qa8Enlace($respuesta, 'mapa'))->toEqual($seleccion + ['vista' => 'mapa'])
            ->and(qa8Enlace($respuesta, 'tarjetas'))->toEqual($seleccion + ['nivel' => 'species', 'taxon' => 'Naesiotus eschariferus', 'vista' => 'tarjetas']);
        $contextoChat = app(ContextoChat::class);
        $contextoChat->guardar(['entities' => $contexto], $respuesta);
        $siguiente = app(AsistentePortal::class)->responder('¿Cuántos géneros y cuántas familias hay en esta selección?',
            app(ConsultarChatBotHandler::class), contextoCatalogo: $contextoChat->obtener()['entities'], seleccionPortal: $seleccion);
        expect($siguiente['datos']['conteos'])->toEqual(['generos' => 1, 'familias' => 1]);
    }
    Http::assertNothingSent();
})->with([
    'Para estos dos caracoles de Galápagos: dime cuántas especies hay, cómo descargar Excel conservando las advertencias de coordenadas y cómo volver al mapa sin perder filtros',
    'Con la selección actual de Naesiotus eschariferus en Galápagos, dime cuántas especies hay, cómo descargar XLSX y cómo volver al mapa sin quitar filtros',
]);

test('QA9 H03 una aclaración retoma la solicitud compuesta sin perder el alcance elegido', function (string $eleccion): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos', 'fc' => 'QA7-INV-1'];
    $pregunta = 'Con la selección actual de Naesiotus eschariferus en Orellana, dime cuántas especies hay, cómo descargar XLSX y cómo volver al mapa sin quitar filtros';
    $chat = Livewire::test(ChatBotWidget::class)->set('pregunta', $pregunta)->call('enviar', $seleccion);
    $aclaracion = $chat->get('mensajes')[1];
    expect(array_column($aclaracion['opciones'], 'pregunta'))->toContain('La selección aplicada de esta página', 'Una consulta nueva con los criterios que indiqué')
        ->and(array_filter($aclaracion['opciones'], static fn ($o) => isset($o['url'])))->toBe([])
        ->and(app(ContextoChat::class)->obtener()['solicitud_pendiente'])->toBe($pregunta);
    // El widget publica texto y opciones; los totales y las partes pertenecen
    // al contrato estructurado del asistente, no al historial de mensajes.
    $contexto = app(ContextoChat::class)->obtener();
    $estructurada = app(AsistentePortal::class)->responder($eleccion, app(ConsultarChatBotHandler::class),
        contextoCatalogo: $contexto['entities'], seleccionPortal: $seleccion, solicitudPendiente: $contexto['solicitud_pendiente']);
    expect(array_column($estructurada['partes'], 'accion'))->toBe(['conteo', 'descarga', 'mapa'])
        ->and($estructurada['partes'][0]['datos']['total'])->toBe($eleccion === 'La selección aplicada de esta página' ? 1 : 0);
    $chat->set('pregunta', $eleccion)->call('enviar', $seleccion);
    $respuesta = $chat->get('mensajes')[3];
    $esperada = $eleccion === 'La selección aplicada de esta página' ? $seleccion : ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Orellana'];
    expect(qa8Enlace($respuesta, 'mapa'))->toEqual($esperada + ['vista' => 'mapa'])
        ->and($respuesta['texto'])->toContain($eleccion === 'La selección aplicada de esta página' ? '1 especie' : '0 especies')
        ->and($respuesta['texto'])->not->toContain('Economía', 'Wikipedia')
        ->and(app(ContextoChat::class)->obtener()['solicitud_pendiente'])->toBeNull();
    $chat->call('nuevaConversacion');
    expect(app(ContextoChat::class)->obtener())->toBe([]);
    Http::assertNothingSent();
})->with(['La selección aplicada de esta página', 'Una consulta nueva con los criterios que indiqué']);

test('QA9 H04 ambos accesos al menú muestran depósito y préstamo y orientan cada destino', function (string $entrada): void {
    Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $menu = $asistente->responder($entrada, $handler);
    expect($menu['intent'])->toBe('portal.tramites')->and(array_column($menu['opciones'], 'label'))->toBe(['Depositar o donar', 'Solicitar préstamo']);
    $deposito = $asistente->responder($menu['opciones'][0]['pregunta'], $handler);
    $prestamo = $asistente->responder($menu['opciones'][1]['pregunta'], $handler);
    expect($deposito['intent'])->toBe('portal.deposito_pasos')
        ->and(array_column($deposito['opciones'], 'url'))->toContain(route('depositos.portal'), route('register', ['rol' => 'DEPOSITANTE']))
        ->and($prestamo['intent'])->toBe('portal.prestamo_pasos')
        ->and(array_column($prestamo['opciones'], 'url'))->toContain(route('prestamos.investigador.mis-solicitudes'));
    $requisitos = $asistente->responder('¿Qué documentos necesito para hacer un depósito?', $handler);
    expect($requisitos['texto'])->toContain('procedencia lícita', 'formularios guiados', 'cuando correspondan', 'No envíes ni traslades material');
    Http::assertNothingSent();
})->with(['Trámites', 'Depósitos y préstamos']);

test('QA9 comparar criterios no borra separadores de un código de catálogo', function (): void {
    qa8CaracolesFixture();
    $consulta = app(ConsultaCatalogoPublico::class);
    $seleccion = ['fc' => 'QA7-INV-1'];
    $coincide = $consulta->responder('¿Cuántos registros del código QA7-INV-1 hay en esta selección?', seleccionPortal: $seleccion);
    expect($coincide['datos']['total'])->toBe(1);
    $distinto = $consulta->responder('¿Cuántos registros del código QA7.INV.1 hay en esta selección?', seleccionPortal: $seleccion);
    expect($distinto['intent'])->toBe('catalogo.aclaracion')->and($distinto['requiere_seleccion'])->toBeTrue()
        ->and($distinto['datos']['total'])->toBeNull()
        ->and(array_filter($distinto['opciones'], static fn ($o) => isset($o['url'])))->toBe([]);
});

test('QA9 H06 el CTA de Depósitos comunica el propósito público al registro', function (): void {
    $this->get(route('depositos.portal'))->assertOk()->assertSee(route('register', ['rol' => 'DEPOSITANTE']), false);
});

test('QA9 H05 el estado físico permanece original en ficha y XLSX sin convertirse en detección', function (): void {
    $f = qa7ColeccionFixture('especie', 1);
    DB::table('taxonomia.taxones')->where('id', $f['taxon'])->update(['nombre_cientifico' => 'Ectatomma ruidum']);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update([
        'occurrence_status' => 'destroyed', 'disposition' => 'in_collection', 'specimen_notes' => 'Destrucción completa por barcoding.',
    ]);
    $pagina = Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $f['ids'][0])->assertSee('Estado original')->assertSee('destroyed')
        ->assertSee('Destrucción completa por barcoding.')->assertSee('En la colección');
    $leer = static function () use ($f): array {
        $xlsx = app(ExportarRegistrosEspecimenesHandler::class)->handle(new ExportarRegistrosEspecimenesInput('Ectatomma ruidum', $f['ids']));
        $archivo = tempnam(sys_get_temp_dir(), 'pest-qa9-estado-publico-'); $libro = null;
        try {
            file_put_contents($archivo, $xlsx->contenidoXlsx);
            $libro = IOFactory::load($archivo);
            $valores = $libro->getActiveSheet()->toArray(null, false, false);
            expect($valores)->toHaveCount(2)->and($valores[0])->toBe(PerfilExportacionPublica::ENCABEZADOS_XLSX);
            return array_combine($valores[0], array_map(static fn ($v): string => (string) ($v ?? ''), $valores[1]));
        } finally {
            $libro?->disconnectWorksheets(); unlink($archivo);
        }
    };
    $fila = $leer();
    expect($fila['occurrenceStatus'])->toBe('')->and($fila['occurrenceStatusVerbatim'])->toBe('destroyed')
        ->and($fila['disposition'])->toBe('in_collection')->and($fila['specimenNotes'])->toBe('Destrucción completa por barcoding.');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['ids'])->update(['occurrence_status_visible' => false]);
    $pagina->call('abrirFichaRegistro', $f['ids'][0])->assertDontSee('destroyed');
    $fila = $leer();
    expect($fila['occurrenceStatus'])->toBe('')->and($fila['occurrenceStatusVerbatim'])->toBe('')
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('occurrence_status'))->toBe('destroyed');
});

test('QA9 H07 un taxón terminal permite abrir sus registros conservando filtros y ficha', function (string $nivel, string $nombre): void {
    $f = qa7ColeccionFixture('genero', 1);
    $id = $nivel === 'phylum' ? $f['filo'] : $f['taxon'];
    DB::table('taxonomia.taxones')->where('id', $id)->update(['nombre_cientifico' => $nombre]);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['taxon_id' => $id]);
    $pagina = Livewire::withQueryParams(['vista' => 'tarjetas', 'nivel' => $nivel, 'taxon' => $nombre,
        'fprov' => 'Orellana', 'fc' => 'QA7-INV-1', 'fmes' => '1'])->test(PortalCatalogo::class)
        ->assertSee('Ver registros de este taxón')->assertDontSee('Descargar datos XLSX')
        ->call('cambiarVista', 'registros')->assertSet('nivel', $nivel)->assertSet('taxon', $nombre)
        ->assertSet('filtroProvincia', 'Orellana')->assertSet('filtroMes', '1')
        ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === $f['ids'])
        ->call('abrirFichaRegistro', $f['ids'][0])->assertSee('QA7-INV-1');
    // publicado se deriva del linaje mediante un disparador PostgreSQL. Retirar
    // el taxón hace al registro no publicable sin borrar el ejemplar fuente.
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['taxon_id' => null]);
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->value('publicado'))->toBeFalse()
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('codigo_catalogo'))->toBe('QA7-INV-1');
    $pagina->call('cambiarVista', 'tarjetas')->assertDontSee('Ver registros de este taxón')
        ->assertViewHas('totalRegistrosVista', 0)
        ->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 0)
        ->assertViewHas('registrosVista', fn ($r) => $r === []);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['taxon_id' => $id]);
    expect(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->value('publicado'))->toBeTrue();
    $pagina->call('cambiarVista', 'tarjetas')->assertSee('Ver registros de este taxón')
        ->assertViewHas('totalRegistrosVista', 1);
})->with([['genus', 'Tubifex'], ['phylum', 'Nematomorpha']]);

test('QA9 las variantes coloquiales piden la unidad o cuentan especies de la página', function (): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos'];
    expect($asistente->responder('ke bichos ai aki', $handler, seleccionPortal: $seleccion)['texto'])->toContain('registros', 'contar las especies');
    expect($asistente->responder('cuantas espesies ai aki', $handler, seleccionPortal: $seleccion)['datos']['total'])->toBe(1);
    Http::assertNothingSent();
});
