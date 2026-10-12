<?php

declare(strict_types=1);

use App\Services\ConsultaRucSri;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(Tests\InfrastructureTestCase::class);

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    Cache::store('array')->flush();
    Http::preventStrayRequests();
});

test('confirma RUC públicos y privados con el registro devuelto por el SRI', function (string $ruc): void {
    Http::fake([ConsultaRucSri::URL.'*' => Http::response([
        ['numeroRuc' => $ruc, 'razonSocial' => 'Entidad de prueba', 'estadoContribuyenteRuc' => 'ACTIVO'],
    ], 200, ['Content-Type' => 'application/json'])]);
    $servicio = app(ConsultaRucSri::class);
    expect($servicio->consultar($ruc))->toBe(['estado' => 'verificado', 'razon_social' => 'Entidad de prueba', 'estado_ruc' => 'ACTIVO']);
    $servicio->consultar($ruc);
    Http::assertSentCount(1);
    Http::assertSent(static fn ($request): bool => $request['ruc'] === $ruc);
})->with(['1760001550001', '1790016919001']);

test('un registro inexistente se distingue de un servicio caído o una respuesta ajena', function (mixed $body, int $status, string $contentType, string $estado): void {
    Http::fake([ConsultaRucSri::URL.'*' => Http::response($body, $status, ['Content-Type' => $contentType])]);
    expect(app(ConsultaRucSri::class)->consultar('1791234567001')['estado'])->toBe($estado);
})->with([
    [[], 200, 'application/json', 'no_encontrado'],
    ['Servicio no disponible', 503, 'text/plain', 'no_disponible'],
    ['<html>Verificación</html>', 200, 'text/html', 'no_disponible'],
    [['error' => 'Temporal'], 200, 'application/json', 'no_disponible'],
    [[['numeroRuc' => '1760001550001', 'razonSocial' => 'Otro registro', 'estadoContribuyenteRuc' => 'ACTIVO']], 200, 'application/json', 'no_disponible'],
]);

test('un RUC malformado no se envía al servicio externo', function (): void {
    Http::fake();
    expect(app(ConsultaRucSri::class)->consultar('179123')['estado'])->toBe('formato_invalido');
    Http::assertNothingSent();
});
