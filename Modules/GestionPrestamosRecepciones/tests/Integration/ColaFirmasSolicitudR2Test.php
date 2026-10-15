<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ArchivoLocalDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);





test('confirma la firma real y permite descargarla antes de enviar bytes a R2', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.signature_async', true);
    $modelo = solicitudColaR2ParaPruebas();
    $usuario = User::findOrFail($modelo->investigador_id);
    $certificado = certificadoRealDepositoParaPruebas();
    $peticion = (string) Str::uuid();
    $respuesta = $this->actingAs($usuario)->post(route('depositos.solicitud.firmar', $modelo->id), [
        'certificado' => $certificado['archivo'], 'clave_certificado' => $certificado['clave'],
        'documento_version' => 1, 'firma_peticion_id' => $peticion,
    ])->assertOk()->assertJsonPath('almacenamiento', 'encolado');
    expect($respuesta->headers->get('Server-Timing'))->toContain('cola;dur=')->not->toContain('r2;dur=');
    Http::assertNothingSent();
    $modelo->refresh();
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class)
        ->assertSee('data-pdf-generated-label', false)->assertDontSee('data-pdf-generated-link', false)
        ->assertSee('data-pdf-generated-check', false)
        ->assertDontSee('wire:click.prevent="visualizarPdfSolicitud"', false)
        ->assertSee('data-oficio-signed-link', false);
    $registro = app(ColaFirmasSolicitudR2::class)->registro($modelo);
    expect($registro->estado)->toBe('pendiente');
    $pdf = app(ColaFirmasSolicitudR2::class)->contenidoPendiente($modelo);
    expect(hash('sha256', $pdf))->toBe($modelo->solicitud_firmada_sha256);
    expect($registro->contenido_cifrado)->not->toContain('%PDF-');
    $trabajo = DB::table('jobs')->where('queue', 'r2-signatures')->where('payload', 'like', '%'.$registro->id.'%')->first();
    expect($trabajo)->not->toBeNull();
    expect($trabajo->payload)->not->toContain('clave_certificado')->not->toContain('%PDF-');
    $this->get(route('depositos.solicitud.documento', $modelo->id))->assertOk()->assertContent($pdf);
    $this->get(route('depositos.solicitud.estado-firma', ['id' => $modelo->id, 'version' => 1]))->assertOk()->assertJsonPath('firmada', true);
    Http::assertNothingSent();
    $otro = User::factory()->create(['rol' => RolUsuario::DEPOSITANTE]);
    $this->actingAs($otro)->get(route('depositos.solicitud.documento', $modelo->id))->assertForbidden();
    app(ColaFirmasSolicitudR2::class)->entregar($registro->id, app(AlmacenamientoDepositos::class));
    $confirmado = app(ColaFirmasSolicitudR2::class)->registro($modelo);
    expect($confirmado->estado)->toBe('confirmado');
    expect($confirmado->contenido_cifrado)->toBeNull();
    expect(app(AlmacenamientoDepositos::class)->sha256($modelo->solicitud_firmada_ruta))->toBe($modelo->solicitud_firmada_sha256);
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class)
        ->assertSee('data-pdf-generated-label', false)->assertDontSee('data-pdf-generated-link', false);
});

test('un fallo remoto conserva el PDF cifrado y el reintento confirma exactamente los mismos bytes', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.r2.max_attempts', 1);
    $fallar = true;
    $persistido = '';
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(function (Illuminate\Http\Client\Request $request) use (&$fallar, &$persistido) {
        if ($fallar) return Http::response('', 503);
        if ($request->method() === 'PUT') { $persistido = $request->body(); return Http::response('', 200); }
        return Http::response($persistido, 200, ['Content-Length' => (string) strlen($persistido), 'Content-Type' => 'application/pdf']);
    });
    $modelo = solicitudColaR2ParaPruebas();
    $contenido = '%PDF-prueba exclusiva de transporte en cola';
    $id = encolarBytesValidadosParaPruebas($modelo, $contenido);
    $cola = app(ColaFirmasSolicitudR2::class);
    expect(fn () => $cola->entregar($id, app(AlmacenamientoDepositos::class)))->toThrow(RuntimeException::class);
    $pendiente = $cola->registro($modelo);
    expect($pendiente->estado)->toBe('pendiente');
    expect($pendiente->intentos)->toBe(1);
    expect(Crypt::decryptString($pendiente->contenido_cifrado))->toBe($contenido);
    $fallar = false;
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    expect($cola->registro($modelo)->estado)->toBe('confirmado');
    expect($persistido)->toBe($contenido);
    $peticiones = count(Http::recorded());
    $cola->entregar($id, app(AlmacenamientoDepositos::class));
    expect(count(Http::recorded()))->toBe($peticiones);
});

test('revertir la transacción revierte firma, PDF pendiente y trabajo en PostgreSQL', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $trabajosAntes = DB::table('jobs')->where('queue', 'r2-signatures')->count();
    expect(fn () => DB::transaction(function () use ($modelo): void {
        encolarBytesValidadosParaPruebas($modelo, '%PDF-transacción revertida');
        throw new RuntimeException('Reversión controlada');
    }))->toThrow(RuntimeException::class, 'Reversión controlada');
    expect($modelo->fresh()->solicitud_firmada_en)->toBeNull();
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('solicitud_id', $modelo->id)->exists())->toBeFalse();
    expect(DB::table('jobs')->where('queue', 'r2-signatures')->count())->toBe($trabajosAntes);
    Http::assertNothingSent();
});

test('una copia pendiente alterada no se confirma ni se envía a R2', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarBytesValidadosParaPruebas($modelo, '%PDF-contenido original');
    DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->update(['contenido_cifrado' => Crypt::encryptString('%PDF-contenido alterado')]);
    expect(fn () => app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class)))->toThrow(RuntimeException::class, 'integridad');
    expect(app(ColaFirmasSolicitudR2::class)->registro($modelo)->estado)->toBe('pendiente');
    Http::assertNothingSent();
});

test('una versión invalidada mientras está en cola no reaparece como firma vigente', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarBytesValidadosParaPruebas($modelo, '%PDF-versión anterior');
    $modelo->forceFill(['solicitud_documento_version' => 2, 'solicitud_firmada_en' => null])->save();
    app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class));
    $registro = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->first();
    expect($registro->estado)->toBe('cancelado');
    expect($registro->contenido_cifrado)->toBeNull();
    Http::assertNotSent(fn (Illuminate\Http\Client\Request $request): bool => $request->method() === 'PUT');
});

test('eliminar el borrador retira R2, PDF pendiente, trabajo y caché sin permitir una subida tardía', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $id = encolarBytesValidadosParaPruebas($modelo, '%PDF-pendiente para descarte');
    $rutas = ['solicitudes-deposito/'.$modelo->id.'/documento.pdf',
        'solicitudes-deposito/'.$modelo->id.'/adjunto.pdf', 'solicitudes-deposito/'.$modelo->id.'/acta.pdf'];
    foreach ($rutas as $ruta) app(AlmacenamientoDepositos::class)->guardarContenido($ruta, '%PDF-archivo propio del borrador', 'application/pdf');
    app(AlmacenamientoDepositos::class)->guardarContenido($modelo->solicitud_firmada_ruta, '%PDF-pendiente para descarte', 'application/pdf');
    $modelo->forceFill(['documentos_cargados' => ['Documento' => $rutas[0]],
        'documentos_adjuntos' => [['nombre' => 'Adjunto', 'ruta' => $rutas[1]]],
        'acta_transferencia_dominio' => ['nombre' => 'Acta', 'ruta' => $rutas[2]]])->save();
    $documentoPendiente = encolarDocumentoAdmitidoParaPruebas($modelo, '%PDF-documento admitido para descarte');
    $rutas[] = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $documentoPendiente)->value('ruta');
    app(GeneradorPdfSolicitudDeposito::class)->generar($modelo);
    $indice = 'oficio-pdf-indice:'.$modelo->id;
    $claves = Cache::get($indice, []);
    expect($claves)->not->toBeEmpty();
    $usuario = User::findOrFail($modelo->investigador_id);
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class)
        ->assertSet('solicitudId', $modelo->id)->call('descartarBorrador')->assertSet('solicitudId', null);
    expect(SolicitudDepositoEloquentModel::find($modelo->id))->toBeNull();
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $id)->exists())->toBeFalse();
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $documentoPendiente)->exists())->toBeFalse();
    expect(DB::table('jobs')->where('queue', 'r2-signatures')->where('payload', 'like', '%'.$id.'%')->exists())->toBeFalse();
    expect(Cache::has($indice))->toBeFalse();
    foreach ($claves as $clave) expect(Cache::has($clave))->toBeFalse();
    foreach ([...$rutas, $modelo->solicitud_firmada_ruta] as $ruta) expect(app(AlmacenamientoDepositos::class)->existe($ruta))->toBeFalse();
    $peticiones = count(Http::recorded());
    app(ColaFirmasSolicitudR2::class)->entregar($id, app(AlmacenamientoDepositos::class));
    expect(count(Http::recorded()))->toBe($peticiones);
});

test('los datos del oficio firmado se conservan aunque una petición intente editarlos', function (string $campo): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    encolarBytesValidadosParaPruebas($modelo, '%PDF-datos bloqueados');
    $antes = $modelo->fresh()->getAttributes();
    $valor = (string) $modelo->{Str::snake($campo)};
    $usuario = User::findOrFail($modelo->investigador_id);
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class)
        ->set($campo, 'cambio-prohibido')->assertHasErrors($campo)->assertSet($campo, $valor);
    expect($modelo->fresh()->getAttributes())->toBe($antes);
    Http::assertNothingSent();
})->with(['solicitudNombrePermiso', 'solicitudCedula', 'solicitudTipoIdentificacion',
    'solicitudCargo', 'solicitudGrupo', 'solicitudProyecto', 'solicitudInstitucion', 'solicitudCorreo', 'solicitudOficio']);

test('editar oficio no revalida otros campos pero la firma rechaza un catálogo desactivado', function (): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $usuario = User::findOrFail($modelo->investigador_id);
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class);
    DB::table('usuarios.instituciones_catalogo')->where('nombre', $modelo->solicitud_institucion)->update(['activo' => false]);
    $componente->set('solicitudOficio', 'QA-VALIDACION-AL-FIRMAR')
        ->assertHasNoErrors()->assertNotDispatched('show-toast');
    expect($modelo->fresh()->solicitud_oficio)->toBe('QA-VALIDACION-AL-FIRMAR');
    $this->actingAs($usuario)->postJson(route('depositos.solicitud.firmar', $modelo->id), [
        'certificado' => \Illuminate\Http\UploadedFile::fake()->create('certificado.p12', 2, 'application/x-pkcs12'),
        'clave_certificado' => 'clave-falsa-no-utilizada',
        'documento_version' => $modelo->fresh()->solicitud_documento_version,
    ])->assertUnprocessable()->assertJsonValidationErrors('solicitudInstitucion');
    expect($modelo->fresh()->solicitud_firmada_en)->toBeNull();
    Http::assertNothingSent();
});
