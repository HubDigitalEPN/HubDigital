<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\R2S3Client;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\TransportePrivadoR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Tests\TestCase;

uses(TestCase::class);

function clienteR2ConSocketDePrueba(): R2S3Client
{
    $transporte = Mockery::mock(TransportePrivadoR2::class);
    $transporte->shouldReceive('socket')->with('cuenta-prueba.r2.cloudflarestorage.com', '/run/hubdigital/r2-transport/r2.sock')->andReturn('/run/hubdigital/r2-transport/r2.sock');
    return new R2S3Client([
        'endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com', 'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA', 'secret_access_key' => 'secreto',
        'transport_socket' => '/run/hubdigital/r2-transport/r2.sock', 'max_attempts' => 3,
    ], new DateTimeImmutable('2026-11-17T03:00:00Z'), $transporte);
}

test('un socket roto o un upstream sin DNS cambia a HTTPS directo conservando método firma ruta y bytes', function (string $metodo, string $fallo): void {
    $peticiones = [];
    Http::fake(function (Request $request, array $opciones) use (&$peticiones, $fallo) {
        $peticiones[] = [$request, $opciones];
        if (str_starts_with($request->url(), 'http:')) {
            if ($fallo === 'conexion') throw new Illuminate\Http\Client\ConnectionException('socket de QA no disponible');
            return Http::response('', 502);
        }
        return Http::response('contenido', 200, ['Content-Length' => '9']);
    });
    $cliente = clienteR2ConSocketDePrueba();
    match ($metodo) {
        'PUT' => $cliente->put('directorio/imagen ñ.png', 'bytes exactos', 'image/png'),
        'GET' => $cliente->get('directorio/imagen ñ.png'),
        'HEAD' => $cliente->head('directorio/imagen ñ.png'),
        'DELETE' => $cliente->delete('directorio/imagen ñ.png'),
    };
    expect(count($peticiones))->toBe(2);
    [$privada, $opcionesPrivadas] = $peticiones[0];
    [$directa, $opcionesDirectas] = $peticiones[1];
    expect($directa->url())->toBe(str_replace('http:', 'https:', $privada->url()))
        ->and($directa->method())->toBe($metodo)->and($directa->body())->toBe($privada->body())
        ->and($directa->header('Authorization'))->toBe($privada->header('Authorization'))
        ->and($directa->header('Host'))->toBe($privada->header('Host'))
        ->and($directa->header('x-amz-content-sha256'))->toBe($privada->header('x-amz-content-sha256'))
        ->and($opcionesPrivadas['curl'][CURLOPT_UNIX_SOCKET_PATH])->toBe('/run/hubdigital/r2-transport/r2.sock')
        ->and($opcionesDirectas['curl'][CURLOPT_UNIX_SOCKET_PATH] ?? null)->toBeNull()
        ->and($opcionesDirectas['verify'])->toBeTrue()->and($opcionesDirectas['allow_redirects'])->toBeFalse();
    // Las siguientes operaciones del mismo cliente no vuelven al socket averiado.
    $cliente->head('otro.pdf');
    expect(count($peticiones))->toBe(3)->and($peticiones[2][0]->url())->toStartWith('https:');
})->with(['PUT', 'GET', 'HEAD', 'DELETE'])->with(['conexion', 'dns']);

test('la caída simultánea del transporte y de R2 termina tras tres intentos y no expone secretos', function (): void {
    $intentos = 0;
    Http::fake(function () use (&$intentos) {
        $intentos++;
        throw new Illuminate\Http\Client\ConnectionException('detalle interno secreto');
    });
    expect(fn () => clienteR2ConSocketDePrueba()->put('documento.pdf', '%PDF-datos', 'application/pdf'))
        ->toThrow(RuntimeException::class, 'No fue posible conectar de forma segura con R2.');
    expect($intentos)->toBe(3);
});

test('un rechazo definitivo no se oculta mediante otro transporte ni se reintenta', function (): void {
    Http::fake(['*' => Http::response('', 403)]);
    expect(fn () => clienteR2ConSocketDePrueba()->put('documento.pdf', '%PDF-datos', 'application/pdf'))
        ->toThrow(RuntimeException::class, 'HTTP 403');
    Http::assertSentCount(1);
});

test('imágenes y documentos se guardan y verifican por R2 directo después de fallar el socket', function (string $tipo): void {
    $objetos = []; $peticiones = [];
    Http::fake(function (Request $request) use (&$objetos, &$peticiones) {
        $peticiones[] = $request;
        if (str_starts_with($request->url(), 'http:')) return Http::response('', 502);
        $ruta = parse_url($request->url(), PHP_URL_PATH);
        if ($request->method() === 'PUT') { $objetos[$ruta] = $request->body(); return Http::response('', 200); }
        if ($request->method() === 'HEAD') return Http::response('', isset($objetos[$ruta]) ? 200 : 404, ['Content-Length' => (string) strlen($objetos[$ruta] ?? '')]);
        return Http::response($objetos[$ruta] ?? '', isset($objetos[$ruta]) ? 200 : 404);
    });
    config(['deposit-storage.driver' => 'r2', 'deposit-storage.require_remote' => true, 'deposit-storage.verify_after_write' => true,
        'deposit-storage.r2' => ['endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com', 'bucket' => 'mepn-privado', 'access_key_id' => 'AKIDPRUEBA', 'secret_access_key' => 'secreto']]);
    $almacenamiento = new AlmacenamientoDepositos;
    (new ReflectionProperty($almacenamiento, 'r2'))->setValue($almacenamiento, clienteR2ConSocketDePrueba());
    if ($tipo === 'imagen') {
        $contenido = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
        $archivo = (new StorageImagenesAdapter($almacenamiento))->guardar($contenido, 'imagen.png');
        expect($archivo->sha256)->toBe(hash('sha256', $contenido));
        $ruta = $archivo->ruta;
    } else {
        $contenido = '%PDF-copia sintética de transporte'; $ruta = 'depositos/qa.pdf';
        expect($almacenamiento->guardarContenido($ruta, $contenido, 'application/pdf'))->toBe(hash('sha256', $contenido));
    }
    expect($almacenamiento->obtener($ruta))->toBe($contenido)
        ->and(count($objetos))->toBe(1)
        ->and(array_map(static fn (Request $r): string => $r->method(), $peticiones))->toBe(['PUT', 'PUT', 'HEAD', 'GET', 'HEAD', 'GET']);
})->with(['imagen', 'documento']);

test('firma una operacion R2 con SigV4, ruta RFC3986 y payload SHA-256', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $cliente = new R2S3Client([
        'endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA',
        'secret_access_key' => 'secreto-que-no-debe-salir',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ], new DateTimeImmutable('2026-09-04T12:34:56Z'));

    $contenido = '%PDF-prueba';
    $cliente->put('documentos con espacio/area ñ.pdf', $contenido, 'application/pdf');

    Http::assertSent(function (Request $request) use ($contenido): bool {
        $autorizacion = $request->header('Authorization')[0] ?? '';

        return $request->method() === 'PUT'
            && $request->url() === 'https://cuenta-prueba.r2.cloudflarestorage.com/mepn-privado/documentos%20con%20espacio/area%20%C3%B1.pdf'
            && ($request->header('x-amz-date')[0] ?? '') === '20260904T123456Z'
            && ($request->header('x-amz-content-sha256')[0] ?? '') === hash('sha256', $contenido)
            && str_contains($autorizacion, 'Credential=AKIDPRUEBA/20260904/auto/s3/aws4_request')
            && str_contains($autorizacion, 'SignedHeaders=host;x-amz-content-sha256;x-amz-date')
            && ! str_contains($autorizacion, 'secreto-que-no-debe-salir');
    });
});

test('rechaza un endpoint R2 sin HTTPS antes de enviar credenciales', function (): void {
    Http::fake();
    $cliente = new R2S3Client([
        'endpoint' => 'http://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA',
        'secret_access_key' => 'secreto',
    ]);

    expect(fn () => $cliente->get('objeto.pdf'))
        ->toThrow(RuntimeException::class, 'debe usar HTTPS');
    Http::assertNothingSent();
});

test('pagina el inventario R2 con continuation token y no confunde una pagina corta con el final', function (): void {
    Http::fake([
        '*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult><IsTruncated>true</IsTruncated><NextContinuationToken>cursor+/=</NextContinuationToken><Contents><Key>depositos/qa/uno.pdf</Key><ETag>&quot;abc&quot;</ETag><Size>17</Size></Contents></ListBucketResult>
XML, 200),
    ]);
    $cliente = new R2S3Client([
        'endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA',
        'secret_access_key' => 'secreto',
        'max_attempts' => 1,
    ], new DateTimeImmutable('2026-09-10T18:00:00Z'));

    $pagina = $cliente->listar('depositos/qa/', null, 25);

    expect($pagina['truncado'])->toBeTrue()
        ->and($pagina['cursor'])->toBe('cursor+/=')
        ->and($pagina['objetos'])->toBe([['ruta' => 'depositos/qa/uno.pdf', 'tamano' => 17, 'etag' => 'abc']]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), 'list-type=2')
        && str_contains($request->url(), 'max-keys=25')
        && str_contains($request->url(), 'prefix=depositos%2Fqa%2F'));
});
