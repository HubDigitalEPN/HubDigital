<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

/** Simula únicamente el dictamen de admisión ya emitido por el servidor; no prueba seguridad PDF. */
function encolarDocumentoAdmitidoParaPruebas(SolicitudDepositoEloquentModel $modelo, string $contenido): string
{
    $nombre = 'Documento de procedencia de los especimenes';
    $temporal = DirectorioTemporalHubDigital::crearArchivo('prueba-cola-documento', 'pdf-');
    file_put_contents($temporal, $contenido);
    try {
        return DB::transaction(function () use ($modelo, $nombre, $contenido, $temporal): string {
            $ruta = 'depositos/'.$modelo->id.'/'.Str::uuid().'.pdf';
            $anteriores = $modelo->documentos_cargados ?? [];
            $previas = $modelo->validacion_previa_documentos ?? [];
            $token = (string) Str::uuid();
            $previas[$nombre] = ['ruta' => $ruta, 'sha256' => hash('sha256', $contenido),
                'estructura' => ['status' => 'seguro'], 'verificacion_id' => $token];
            $modelo->forceFill(['documentos_requeridos' => [$nombre],
                'documentos_cargados' => [...$anteriores, $nombre => $ruta], 'validacion_previa_documentos' => $previas])->save();
            $id = app(ColaFirmasSolicitudR2::class)->encolarDocumento($temporal, $modelo, $nombre, $anteriores[$nombre] ?? null);
            $previas[$nombre] += ['r2_pendiente_id' => $id, 'r2_verificacion_id' => $token];
            $modelo->forceFill(['validacion_previa_documentos' => $previas])->save();
            return $id;
        });
    } finally { DirectorioTemporalHubDigital::eliminar(dirname($temporal)); }
}

test('una caída de R2 retiene el documento admitido cifrado y el reintento confirma su huella', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.r2.max_attempts', 1);
    $fallar = true;
    $persistido = '';
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(function ($peticion) use (&$fallar, &$persistido) {
        if ($fallar) return Http::response('', 503);
        if ($peticion->method() === 'PUT') { $persistido = $peticion->body(); return Http::response('', 200); }
        return Http::response($persistido, 200, ['Content-Length' => (string) strlen($persistido)]);
    });
    $modelo = solicitudColaR2ParaPruebas();
    $contenido = '%PDF-copia admitida para comprobar solo transporte';
    $id = encolarDocumentoAdmitidoParaPruebas($modelo, $contenido);
    $cola = app(ColaFirmasSolicitudR2::class);
    expect(fn () => $cola->entregar($id, app(AlmacenamientoDepositos::class)))->toThrow(RuntimeException::class);
    $pendiente = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->first();
    expect($pendiente->estado)->toBe('pendiente')->and(Crypt::decryptString($pendiente->contenido_cifrado))->toBe($contenido);
    $fallar = false;
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    $actual = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->first();
    expect($actual->estado)->toBe('confirmado')->and($actual->contenido_cifrado)->toBeNull()->and($persistido)->toBe($contenido);
    $peticiones = count(Http::recorded());
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    expect(count(Http::recorded()))->toBe($peticiones);
});

test('revertir el expediente revierte juntos el documento cifrado y el trabajo R2', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $trabajos = DB::table('jobs')->where('queue', 'r2-signatures')->count();
    expect(fn () => DB::transaction(function () use ($modelo): void {
        encolarDocumentoAdmitidoParaPruebas($modelo, '%PDF-transacción de documento');
        throw new RuntimeException('revertir');
    }))->toThrow(RuntimeException::class, 'revertir');
    expect($modelo->fresh()->documentos_cargados)->toBeEmpty();
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('solicitud_id', $modelo->id)->exists())->toBeFalse();
    expect(DB::table('jobs')->where('queue', 'r2-signatures')->count())->toBe($trabajos);
    Http::assertNothingSent();
});

test('cancelar o sustituir un documento impide una subida tardía de la versión retirada', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarDocumentoAdmitidoParaPruebas($modelo, '%PDF-versión retirada');
    $cola = app(ColaFirmasSolicitudR2::class);
    DB::transaction(fn () => $cola->cancelarDocumento($modelo->id, 'Documento de procedencia de los especimenes'));
    $nuevo = encolarDocumentoAdmitidoParaPruebas($modelo, '%PDF-versión vigente');
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    Http::assertNotSent(fn ($peticion) => $peticion->method() === 'PUT');
    $cola->entregar($nuevo, app(AlmacenamientoDepositos::class));
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $nuevo)->value('estado'))->toBe('confirmado');
    $ruta = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->value('ruta');
    expect(app(AlmacenamientoDepositos::class)->existe($ruta))->toBeFalse();
});

test('una eliminación remota fallida de un documento confirmado conserva su retirada en cola para reintentar', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.r2.max_attempts', 1);
    $contenido = '%PDF-ejemplar confirmado que debe retirarse';
    $fallarBorrado = true;
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(function ($peticion) use (&$fallarBorrado, $contenido) {
        if ($peticion->method() === 'DELETE') return Http::response('', $fallarBorrado ? 503 : 204);
        return Http::response($peticion->method() === 'GET' ? $contenido : '', 200, ['Content-Length' => (string) strlen($contenido)]);
    });
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarDocumentoAdmitidoParaPruebas($modelo, $contenido);
    $cola = app(ColaFirmasSolicitudR2::class);
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    DB::transaction(fn () => $cola->cancelarDocumento($modelo->id, 'Documento de procedencia de los especimenes'));
    expect(fn () => $cola->entregar($id, app(AlmacenamientoDepositos::class)))->toThrow(RuntimeException::class);
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->value('estado'))->toBe('cancelado');
    expect(DB::table('jobs')->where('queue', 'r2-signatures')->where('payload', 'like', '%'.$id.'%')->exists())->toBeTrue();
    $fallarBorrado = false;
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    Http::assertSent(fn ($peticion) => $peticion->method() === 'DELETE');
});

test('alterar los bytes cifrados o la identidad del ejemplar impide confirmar el documento', function (bool $alterarBytes): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarDocumentoAdmitidoParaPruebas($modelo, '%PDF-ejemplar original');
    if ($alterarBytes) {
        DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->update(['contenido_cifrado' => Crypt::encryptString('%PDF-alterado')]);
        expect(fn () => app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class)))->toThrow(RuntimeException::class, 'integridad');
    } else {
        $previas = $modelo->validacion_previa_documentos;
        $previas['Documento de procedencia de los especimenes']['r2_pendiente_id'] = (string) Str::uuid();
        $modelo->forceFill(['validacion_previa_documentos' => $previas])->save();
        app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class));
        expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->value('estado'))->toBe('cancelado');
    }
    Http::assertNotSent(fn ($peticion) => $peticion->method() === 'PUT');
})->with([true, false]);
