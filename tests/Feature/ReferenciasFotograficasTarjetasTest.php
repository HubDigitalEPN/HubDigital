<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(Tests\InfrastructureTestCase::class);

test('las miniaturas externas se convierten a WebP y se reutilizan sin consultar otra vez la fuente', function (): void {
    Cache::forget('portal-referencia-webp-v1:738636165:jpg');
    $imagen = imagecreatetruecolor(40, 30); ob_start(); imagejpeg($imagen); $binario = ob_get_clean(); imagedestroy($imagen);
    Http::fake(['https://inaturalist-open-data.s3.amazonaws.com/photos/738636165/medium.jpg' => Http::response($binario, 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => (string) strlen($binario)])]);
    $respuesta = $this->get('/portal/referencias-fotograficas/738636165/jpg.webp')->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect(substr($respuesta->getContent(), 0, 4))->toBe('RIFF')->and(substr($respuesta->getContent(), 8, 4))->toBe('WEBP');
    $this->get('/portal/referencias-fotograficas/738636165/jpg.webp')->assertOk();
    Http::assertSentCount(1);
});

test('el servicio de miniaturas rechaza identificadores ajenos, redirecciones y archivos enormes sin almacenarlos', function (): void {
    $rechazadas = [
        Http::response('', 302, ['Location' => 'http://127.0.0.1/private']),
        Http::response('no es imagen', 200, ['Content-Type' => 'text/html']),
        Http::response('', 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '2000001']),
    ];
    $fuentes = [];
    foreach ($rechazadas as $i => $falsa) $fuentes['https://inaturalist-open-data.s3.amazonaws.com/photos/'.(800000000 + $i).'/medium.jpg'] = $falsa;
    Http::fake($fuentes);
    foreach (['0/jpg', '-1/png', 'url/jpeg', '123/svg', '123/html'] as $ruta) $this->get('/portal/referencias-fotograficas/'.$ruta.'.webp')->assertNotFound();
    Http::assertNothingSent();
    foreach ($rechazadas as $i => $falsa) {
        $id = (string) (800000000 + $i); $clave = 'portal-referencia-webp-v1:'.$id.':jpg'; Cache::forget($clave);
        $this->get('/portal/referencias-fotograficas/'.$id.'/jpg.webp')->assertNotFound();
        expect(Cache::has($clave))->toBeFalse();
    }
    Http::assertSentCount(3);
});
