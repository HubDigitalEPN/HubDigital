<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EvaluadorCorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\RankingIntencionesChat;

test('la negación de un dominio separa depósito de donación', function (): void {
    $ranker = app(RankingIntencionesChat::class);
    $ranking = $ranker->evaluar('no es depósito, es donación');
    expect($ranking['candidatos'][0]['intent'])->toBe('donacion')
        ->and($ranking['features']['negated_domain'])->toBe('deposito')
        ->and($ranking['features']['explicit_domain'])->toBe('donacion')
        ->and($ranker->confianza($ranking)['ambiguo'])->toBeFalse();
    expect(app(ConocimientoPortal::class)->responder('no es depósito, es donación', registrar: false)['intent'])
        ->toBe('donacion');
});

test('una consulta mínima sin contexto pide una aclaración ligada a nodos publicados', function (): void {
    $knowledge = app(ConocimientoPortal::class);
    $answer = $knowledge->responder('documentos', registrar: false);
    expect($answer['intent'])->toBe('ambiguous')
        ->and($answer['opciones'])->toHaveCount(2);
    foreach ($answer['opciones'] as $option) {
        expect(DB::table('divulgacion.chat_nodes')->where('title', $option['label'])
            ->where('status', 'published')->exists())->toBeTrue();
    }
    $depositId = DB::table('divulgacion.chat_nodes')->where('slug', 'deposito')->value('id');
    $donationId = DB::table('divulgacion.chat_nodes')->where('slug', 'donacion')->value('id');
    expect($knowledge->responder('documentos', $depositId, registrar: false)['intent'])->toBe('documentos')
        ->and($knowledge->responder('requisitos', $donationId, registrar: false)['intent'])
        ->toBe('donacion_requisitos');
});

test('la evaluación de desarrollo distingue cobertura, precisión y recuperación de candidatos', function (): void {
    $report = app(EvaluadorCorpusChat::class)->evaluar('calibration');
    expect($report['total'])->toBe(57)
        ->and($report['top3'])->toBeGreaterThanOrEqual($report['top2'])
        ->and($report['top5'])->toBeGreaterThanOrEqual($report['top3'])
        ->and($report['coverage'])->toBeLessThan(1)
        ->and($report['automatic_precision'])->toBeGreaterThan(0.8)
        ->and($report['ood_correct'])->toBe($report['ood_total']);
    expect(CorpusChat::tamanos()['test_final'])->toBeGreaterThanOrEqual(50);
});

test('el nivel HIGH permanece inactivo sin evidencia final suficiente', function (): void {
    $ranker = app(RankingIntencionesChat::class);
    $ranking = $ranker->evaluar('no quiero donar, quiero depositar');
    expect($ranker->confianza($ranking)['nivel'])->toBe('MEDIUM')
        ->and($ranker->confianza($ranking)['valor'])->toBeGreaterThan(0.7);
});

test('una corrección de geografía sustituye el filtro público anterior', function (): void {
    $phylum = (string) Str::uuid();
    $taxon = (string) Str::uuid();
    $specimen = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $phylum, 'nombre_cientifico' => 'Filo QA '.substr($phylum, 0, 8),
        'rango' => 'phylum', 'autor' => 'Prueba', 'anio_descripcion' => 2026]);
    DB::table('taxonomia.taxones')->insert(['id' => $taxon, 'nombre_cientifico' => 'Dynastes',
        'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $phylum]);
    DB::table('taxonomia.especimenes')->insert(['id' => $specimen, 'codigo_catalogo' => 'MEPN-FOURTH-1',
        'occurrence_id' => 'MEPN-FOURTH-1', 'taxon_id' => $taxon, 'localidad' => 'Quito',
        'locality_name' => 'Quito', 'state_province' => 'Pichincha', 'country' => 'Ecuador',
        'fecha_colecta' => '2026-09-26', 'colector' => 'Prueba']);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(),
        'especimen_id' => $specimen]);
    $result = app(ConsultaCatalogoPublico::class)->responder('No Pichincha, Quito',
        ['taxon' => 'Dynastes', 'provincia' => 'Pichincha']);
    expect($result['entidades']['localidad'] ?? null)->toBe('Quito')
        ->and($result['entidades'])->not->toHaveKey('provincia');
});
