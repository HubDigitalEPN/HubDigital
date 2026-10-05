<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function qa8HormigasFixture(): array
{
    $fixture = qa7ColeccionFixture('especie', 4);
    foreach (['filo' => 'Arthropoda', 'familia' => 'Formicidae', 'genero' => 'Camponotus', 'taxon' => 'Camponotus femoratus'] as $clave => $nombre) {
        DB::table('taxonomia.taxones')->where('id', $fixture[$clave])->update(['nombre_cientifico' => $nombre]);
    }
    $otra = (string) Str::uuid(); $cf = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $otra, 'padre_id' => $fixture['genero'], 'rango' => 'especie', 'nombre_cientifico' => 'Camponotus alpha'],
        ['id' => $cf, 'padre_id' => $fixture['genero'], 'rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus cf'],
    ]);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][2])->update(['taxon_id' => $otra]);
    DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][3])->update(['taxon_id' => $cf]);
    return $fixture;
}

function qa8CaracolesFixture(): array
{
    $fixture = qa7ColeccionFixture('especie', 2);
    foreach (['filo' => 'Mollusca', 'familia' => 'Orthalicidae', 'genero' => 'Naesiotus', 'taxon' => 'Naesiotus eschariferus'] as $clave => $nombre) {
        DB::table('taxonomia.taxones')->where('id', $fixture[$clave])->update(['nombre_cientifico' => $nombre]);
    }
    DB::table('taxonomia.especimenes')->whereIn('id', $fixture['ids'])->update(['state_province' => 'Galápagos', 'individual_count' => 3]);
    return $fixture;
}

function qa8Enlace(array $respuesta, string $vista): array
{
    foreach ($respuesta['opciones'] as $opcion) {
        parse_str((string) parse_url($opcion['url'] ?? '', PHP_URL_QUERY), $p);
        if (($p['vista'] ?? '') === $vista) return $p;
    }
    throw new RuntimeException('Falta el enlace de la vista '.$vista);
}

test('QA8 001 las paráfrasis conservan exclusión, conjunto vacío y control no vacío tras un positivo', function (string $plantilla): void {
    $fixture = qa8HormigasFixture(); Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    foreach (['Camponotus femoratus' => [], 'Camponotus' => [$fixture['ids'][2]]] as $taxon => $esperados) {
        $positiva = $asistente->responder('¿Cuántos registros de '.$taxon.' hay en Orellana?', $handler);
        foreach ([[], $positiva['entidades']] as $contexto) {
            $respuesta = $asistente->responder(sprintf($plantilla, $taxon), $handler, contextoCatalogo: $contexto);
            expect($respuesta['datos']['total'])->toBe(count($esperados))
                ->and($respuesta['texto'])->toContain('Provincia excluida: Orellana', 'provincias públicas informadas')
                ->and($respuesta['entidades'])->toMatchArray(['taxon' => $taxon, 'provincia_excluida' => 'Orellana'])->not->toHaveKey('provincia');
            $p = qa8Enlace($respuesta, 'registros');
            expect($p)->toMatchArray(['ft' => $taxon, 'fxprov' => 'Orellana'])->not->toHaveKey('fprov');
            Livewire::withQueryParams($p)->test(PortalCatalogo::class)
                ->assertViewHas('registrosVista', fn ($r) => array_column($r, 'especimen_id') === $esperados)
                ->assertViewHas('totalRegistrosVista', count($esperados));
        }
    }
    Http::assertNothingSent();
})->with([
    'Q09' => ['%s fuera de Orellana'],
    'Q10' => ['¿Cuántos registros de %s hay fuera de Orellana?'],
    'Q13' => ['Muéstrame %s excepto Orellana'],
    'Q14' => ['Quiero ver %s en cualquier provincia menos Orellana'],
    'Q15 RQ33' => ['No busco registros de Orellana: busco %s en el resto de provincias'],
    'provincia al inicio' => ['Fuera de Orellana, busco %s'],
    'salvo' => ['Quiero ver %s salvo Orellana'],
    'provincias distintas' => ['Quiero ver %s en provincias distintas de Orellana'],
    'provincia diferente' => ['Quiero ver %s en una provincia diferente de Orellana'],
]);

test('QA8 Q11 Q12 conserva taxón en la elipsis y distingue coincidencias textuales de determinaciones exactas', function (): void {
    qa8HormigasFixture(); Http::preventStrayRequests();
    $chat = Livewire::test(ChatBotWidget::class);
    $chat->set('pregunta', '¿Cuántos registros de Camponotus femoratus hay en Orellana?')->call('enviar');
    expect($chat->get('mensajes')[1]['texto'])->toContain('3 registros publicados');
    $chat->set('pregunta', '¿Y cuántos no son de Orellana?')->call('enviar');
    expect($chat->get('mensajes')[3]['texto'])->toContain('No encontré registros', 'Provincia excluida: Orellana');
    expect(qa8Enlace(['opciones' => $chat->get('mensajes')[3]['opciones']], 'registros'))->toMatchArray(['ft' => 'Camponotus femoratus', 'fxprov' => 'Orellana'])->not->toHaveKey('fprov');
    Http::assertNothingSent();
});

test('QA8 las provincias vacías o desconocidas no invalidan un operador conocido ni acreditan estar fuera', function (): void {
    $fixture = qa8HormigasFixture();
    $consulta = app(ConsultaCatalogoPublico::class);
    foreach (['', '  ', "\u{00a0}", 'desconocida', null] as $provincia) {
        DB::table('taxonomia.especimenes')->where('id', $fixture['ids'][3])->update(['state_province' => $provincia]);
        expect($consulta->responder('Camponotus femoratus fuera de Orellana')['datos']['total'])->toBe(0)
            ->and($consulta->responder('Camponotus fuera de Orellana')['datos']['total'])->toBe(1);
    }
});

test('QA8 una exclusión ambigua no enlaza silenciosamente la inclusión anterior', function (string $exclusion): void {
    qa8HormigasFixture();
    $respuesta = app(AsistentePortal::class)->responder('Camponotus femoratus '.$exclusion, app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['taxon' => 'Camponotus femoratus', 'provincia' => 'Orellana']);
    expect($respuesta['intent'])->toBe('catalogo.aclaracion')->and($respuesta)->not->toHaveKey('datos')
        ->and($respuesta['opciones'])->toBe([])->and($respuesta['texto'])->toContain('exclusión');
})->with(['fuera de Orellana y Napo', 'excepto Provincia inexistente', 'fuera del Ecuador', 'excepto Orellana salvo Napo', 'en Orellana excepto Orellana']);

test('QA8 002 las preguntas de familia usan el binomio y distinguen las dos autoridades', function (string $pregunta): void {
    $fixture = qa8CaracolesFixture(); Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder($pregunta, $handler);
    expect($respuesta['intent'])->toBe('catalogo.clasificacion')
        ->and($respuesta['texto'])->toContain('Naesiotus eschariferus', 'Familia publicada en el catálogo: Orthalicidae', 'Familia de referencia externa: Bulimulidae', '2026-10-04', 'Pendiente de cotejo')
        ->not->toContain('Donación');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['family_visible' => false]);
    $respuesta = $asistente->responder($pregunta, $handler);
    expect($respuesta['texto'])->not->toContain('Orthalicidae')->not->toContain('Bulimulidae');
    Http::assertNothingSent();
})->with([
    '¿A qué familia pertenece Naesiotus eschariferus y por qué aparece Orthalicidae aquí?',
    '¿Naesiotus eschariferus pertenece a Orthalicidae o a Bulimulidae?',
]);

test('QA8 002 intención científica, coordenada singular y préstamo no se convierten en filtros', function (string $pregunta, string $intencion, string $esperado): void {
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'])->toBe($intencion)->and($respuesta['texto'])->toContain($esperado)
        ->not->toContain('Taxón = Quién', 'No necesitas cuenta');
    Http::assertNothingSent();
})->with([
    ['¿Qué significa cf en un nombre científico?', 'portal.calificador', 'identificationQualifier'],
    ['¿Quién escribió El origen de las especies?', 'general.autoria', 'Charles Darwin'],
    ['¿Qué significa una coordenada recuperada del Excel? ¿Es una ubicación exacta?', 'portal.glosario', 'nunca cero'],
    ['¿Me prestan material? Explícame el trámite paso a paso', 'portal.prestamo_pasos', 'rol autorizado Prestamista'],
]);

test('QA8 RQ30 la función de las hormigas se responde antes de buscar Formicidae', function (): void {
    qa8HormigasFixture(); Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder('¿Para qué sirven las hormigas en un bosque?', app(ConsultarChatBotHandler::class));
    expect($respuesta['texto'])->toContain('función', 'depredan')->not->toContain('registros publicados');
    expect(array_column($respuesta['opciones'], 'url'))->toContain('https://www.nhm.ac.uk/discover/life-in-soil.html');
    Http::assertNothingSent();
});

test('QA8 RQ19 RQ25 responde cada unidad y no deduce individuos del número de filas', function (): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos'];
    $comparacion = $asistente->responder('¿Estos dos registros de Naesiotus son dos individuos o dos especies?', $handler, seleccionPortal: $seleccion);
    expect($comparacion['datos']['conteos'])->toEqual(['registros' => 2, 'especies' => 1])
        ->and($comparacion['texto'])->toContain('no equivalen necesariamente a individuos');
    $rangos = $asistente->responder('¿Cuántos géneros y cuántas familias hay en esta selección?', $handler, seleccionPortal: $seleccion);
    expect($rangos['datos']['conteos'])->toEqual(['generos' => 1, 'familias' => 1])
        ->and($rangos['texto'])->toContain('Naesiotus', 'Orthalicidae');
    DB::table('divulgacion.especimenes_divulgables')->update(['family_visible' => false]);
    $reservada = $asistente->responder('¿Cuántos géneros y cuántas familias hay en esta selección?', $handler, seleccionPortal: $seleccion);
    expect($reservada['datos']['conteos'])->toEqual(['generos' => 1, 'familias' => 0])->and($reservada['texto'])->not->toContain('Orthalicidae');
    Http::assertNothingSent();
});

test('QA8 mencionar registros no agrega otra unidad a una pregunta que solo pide especies', function (): void {
    qa8CaracolesFixture();
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos'];
    $respuesta = app(ConsultaCatalogoPublico::class)->responder('¿Cuántas especies representan los registros de esta selección?', seleccionPortal: $seleccion);
    expect($respuesta['intent'])->toBe('catalogo.species')->and($respuesta['datos']['total'])->toBe(1);
});

test('QA8 una petición afirmativa de limpiar filtros sigue abriendo la consulta global', function (string $pregunta): void {
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class),
        seleccionPortal: ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos', 'fmes' => '1']);
    expect($respuesta['intent'])->toBe('portal.limpiar')
        ->and(qa8Enlace($respuesta, 'mapa'))->toBe(['vista' => 'mapa']);
    Http::assertNothingSent();
})->with(['Limpiar todos los filtros', 'Quiero quitar los filtros y volver al mapa']);

test('QA8 003 Q20 mantiene el orden, cuenta especies y enlaza XLSX directamente a la especie', function (): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos', 'fmes' => '1', 'flat' => '-2', 'flax' => '-1', 'flon' => '-78', 'flox' => '-77'];
    $pregunta = 'Estoy mirando estos dos caracoles de Galápagos. Primero dime cuántas especies hay. Después quiero descargar los dos registros en XLSX sin perder las advertencias de precisión. Por último vuelve al mapa sin quitar ningún filtro. Dame los pasos en ese orden.';
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), seleccionPortal: $seleccion);
    expect(array_column($respuesta['partes'], 'accion'))->toBe(['conteo', 'descarga', 'mapa'])
        ->and($respuesta['partes'][0]['datos']['total'])->toBe(1)
        ->and($respuesta['texto'])->toContain('1 especie', 'Descargar datos XLSX', 'advertencias de coordenadas', 'No he descargado archivos');
    expect(qa8Enlace($respuesta, 'tarjetas'))->toEqual($seleccion + ['nivel' => 'species', 'taxon' => 'Naesiotus eschariferus', 'vista' => 'tarjetas'])
        ->and(qa8Enlace($respuesta, 'mapa'))->toEqual($seleccion + ['vista' => 'mapa']);
    Http::assertNothingSent();
});

test('QA8 RQ27 una consulta compuesta nueva sustituye la selección ajena y explica precisión', function (): void {
    qa8CaracolesFixture(); Http::preventStrayRequests();
    $pregunta = 'Primero quiero ver Naesiotus eschariferus en Galápagos. Después quiero descargar los dos registros en Excel. Dame los pasos y explica la precisión de las coordenadas.';
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), seleccionPortal: ['fc' => 'AJENO']);
    expect(array_column($respuesta['partes'], 'accion'))->toBe(['consulta', 'descarga', 'precision'])
        ->and($respuesta['partes'][0]['datos']['total'])->toBe(2)
        ->and(qa8Enlace($respuesta, 'tarjetas'))->toMatchArray(['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos', 'nivel' => 'species', 'taxon' => 'Naesiotus eschariferus'])->not->toHaveKey('fc')
        ->and($respuesta['texto'])->toContain('Descargar datos XLSX', 'incertidumbre desconocida', 'nunca cero');
    Http::assertNothingSent();
});

test('QA8 una selección ambigua deja pendientes descarga y mapa sin enlaces parciales', function (): void {
    qa8HormigasFixture();
    $respuesta = app(AsistentePortal::class)->responder('Primero busca Camponotus excepto Provincia inexistente. Después descarga XLSX. Por último vuelve al mapa sin quitar filtros.', app(ConsultarChatBotHandler::class));
    expect(array_column($respuesta['partes'], 'estado'))->toBe(['pendiente', 'pendiente', 'pendiente'])
        ->and($respuesta['opciones'])->toBe([]);
});

test('QA8 cada descarga compuesta conserva su formato aunque otra parte mencione Excel', function (): void {
    qa8CaracolesFixture();
    $seleccion = ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos'];
    $respuesta = app(AsistentePortal::class)->responder('Primero descarga los registros CSV. Después descarga XLSX. Explica la precisión de la coordenada recuperada del Excel.', app(ConsultarChatBotHandler::class), seleccionPortal: $seleccion);
    expect(array_column($respuesta['partes'], 'accion'))->toBe(['descarga', 'descarga', 'precision'])
        ->and($respuesta['texto'])->toContain('1. Cambia a Registros', '2. Para descargar XLSX', 'nunca cero')
        ->and(qa8Enlace($respuesta, 'registros'))->toEqual($seleccion + ['vista' => 'registros'])
        ->and(qa8Enlace($respuesta, 'tarjetas'))->toMatchArray(['nivel' => 'species', 'taxon' => 'Naesiotus eschariferus']);
});

test('QA8 Q04 el enlace de Excel llega a la especie cuando toda la selección comparte su identidad', function (): void {
    qa8CaracolesFixture();
    $respuesta = app(AsistentePortal::class)->responder('dame pasos para descargar estos dos registros en Excel', app(ConsultarChatBotHandler::class),
        seleccionPortal: ['ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos']);
    $p = qa8Enlace($respuesta, 'tarjetas');
    expect($p)->toMatchArray(['nivel' => 'species', 'taxon' => 'Naesiotus eschariferus', 'ft' => 'Naesiotus eschariferus', 'fprov' => 'Galápagos']);
    Livewire::withQueryParams($p)->test(PortalCatalogo::class)->assertSee('Descargar datos XLSX');
});

test('QA8 XLSX no elige una especie arbitraria ni revela una identificación reservada', function (): void {
    $fixture = qa8HormigasFixture();
    $consulta = app(ConsultaCatalogoPublico::class);
    $mixta = ['ft' => 'Camponotus'];
    expect($consulta->parametrosTarjetasEspecie($mixta))->toEqual($mixta);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $fixture['ids'][0])->update(['scientific_name_visible' => false]);
    $reservada = ['fc' => 'QA7-INV-1'];
    expect($consulta->parametrosTarjetasEspecie($reservada))->toEqual($reservada);
});

test('QA8 004 los nombres anteriores y actuales conservan referencia y revisión pendiente en las fichas', function (string $anterior, string $actual, string $id): void {
    $fixture = qa7ColeccionFixture();
    DB::table('taxonomia.taxones')->where('id', $fixture['taxon'])->update(['nombre_cientifico' => $actual]);
    $referencia = app(ReferenciaTaxonomicaPublica::class);
    expect($referencia->para($actual, true))->toMatchArray(['original' => $actual, 'anterior' => $anterior, 'evidencia' => $id, 'decision' => 'Pendiente de cotejo y decisión curatorial'])
        ->and($referencia->para($anterior)['evidencia'])->toBe($id);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $fixture['ids'][0])->assertSee($anterior)->assertSee($actual)->assertSee($id)->assertSee('Pendiente de cotejo');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $fixture['ids'])->update(['scientific_name_visible' => false]);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => 'QA7-INV-1'])->test(PortalCatalogo::class)
        ->call('abrirFichaRegistro', $fixture['ids'][0])->assertDontSee($anterior)->assertDontSee($actual)->assertDontSee($id);
})->with([
    ['Dolichoderus dEColatus', 'Dolichoderus decollatus', 'TX7-072'],
    ['ECtatoma ruidum', 'Ectatomma ruidum', 'TX7-009'],
]);
