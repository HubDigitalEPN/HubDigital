<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;

uses(Tests\DatabaseFeatureTestCase::class);

test('QA6 el chat responde cantidad y uso del mapa preservando todos los filtros de la selección aplicada', function (): void {
    registrosParaContratoChat();
    $seleccion = ['nivel' => 'species', 'taxon' => 'Chatobius alpha', 'fpais' => 'Ecuador', 'fprov' => 'Esmeraldas', 'fg' => ['Playa de oro'], 'ffd' => '2000-05-01', 'ffh' => '2000-05-05'];
    $respuesta = app(AsistentePortal::class)->responder('¿Cuántos registros hay en esta selección y cómo uso el mapa?', app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: $seleccion);
    expect($respuesta['intent'])->toBe('catalogo.count')
        ->and($respuesta['datos']['total'])->toBe(1)
        ->and($respuesta['texto'])->toContain('1 registro publicado', 'Cómo usar el mapa', 'clústeres', 'seis ejemplares por página', 'advertencias');
    $mapa = array_values(array_filter($respuesta['opciones'], fn (array $opcion): bool => $opcion['label'] === 'Abrir mapa de esta consulta'))[0];
    parse_str((string) parse_url($mapa['url'], PHP_URL_QUERY), $parametros);
    expect($parametros)->toEqual($seleccion + ['vista' => 'mapa']);
});

test('QA6 el enlace al mapa de una pregunta compuesta nueva conserva su consulta y no hereda otra selección', function (): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder('¿Cuántos registros de Chatobius hay en Pichincha y cómo puedo usar el mapa?', app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['codigo' => 'QA3-CHAT-4'], seleccionPortal: ['fc' => 'QA3-CHAT-4']);
    expect($respuesta['datos']['total'])->toBe(3)
        ->and($respuesta['texto'])->toContain('3 registros publicados', 'Cómo usar el mapa');
    $mapa = array_values(array_filter($respuesta['opciones'], fn (array $opcion): bool => $opcion['label'] === 'Abrir mapa de esta consulta'))[0];
    parse_str((string) parse_url($mapa['url'], PHP_URL_QUERY), $parametros);
    expect($parametros)->toEqual(['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'vista' => 'mapa']);
});
