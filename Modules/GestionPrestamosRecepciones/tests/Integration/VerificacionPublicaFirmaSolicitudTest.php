<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

function solicitudConDictamenPublicoParaPruebas(): array
{
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarBytesValidadosParaPruebas($modelo, '%PDF-transporte validado para consulta pública');
    $modelo->forceFill(['solicitud_firma_metadata' => [
        'r2_pendiente_id' => $id, 'pdf_sha256' => $modelo->solicitud_firmada_sha256,
        'aceptable' => true, 'integridad_criptografica' => true, 'contenido_oficial_coincide' => true,
    ]])->save();
    config()->set('app.url', 'https://dev.labinvepn.org');
    return [$modelo, $id, GeneradorPdfSolicitudDeposito::urlVerificacion($modelo)];
}

test('el enlace del QR permite consultar sin sesión y distingue pendiente de confirmado sin publicar el oficio', function (): void {
    [$modelo, $id, $url] = solicitudConDictamenPublicoParaPruebas();
    expect($url)->toStartWith('https://dev.labinvepn.org/verificar-firma/');
    $this->get($url)->assertStatus(202)->assertSee('Guardado pendiente')
        ->assertDontSee($modelo->solicitud_nombre_permiso)->assertDontSee($modelo->solicitud_correo)
        ->assertDontSee($modelo->solicitud_cedula);
    Http::assertNothingSent();
    app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class));
    $respuesta = $this->get($url)->assertOk()->assertSee('Firma validada')->assertSee($modelo->numero)
        ->assertDontSee($modelo->solicitud_correo)->assertDontSee('%PDF-');
    expect($respuesta->headers->get('Cache-Control'))->toContain('no-store');
    expect($respuesta->headers->get('Referrer-Policy'))->toBe('no-referrer');
});

test('alterar la dirección del QR es rechazado y una versión antigua deja de verificarse', function (): void {
    [$modelo, , $url] = solicitudConDictamenPublicoParaPruebas();
    $this->get(str_replace('/1?', '/2?', $url))->assertForbidden();
    $modelo->forceFill(['solicitud_documento_version' => 2])->save();
    $this->get($url)->assertNotFound()->assertSee('Documento no verificable');
    Http::assertNothingSent();
});

test('la consulta pública no acredita un archivo distinto al validado aunque exista en R2', function (): void {
    [$modelo, $id, $url] = solicitudConDictamenPublicoParaPruebas();
    app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class));
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(fn () => Http::response('%PDF-alterado', 200, ['Content-Length' => '13', 'Content-Type' => 'application/pdf']));
    $this->get($url)->assertUnprocessable()->assertSee('No se pudo confirmar la integridad')
        ->assertDontSee('Firma validada');
});
