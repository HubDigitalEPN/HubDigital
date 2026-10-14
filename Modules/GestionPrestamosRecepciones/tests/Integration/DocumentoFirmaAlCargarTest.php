<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\VerificarFirmaDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('la carga conserva el dictamen y guarda bytes cifrados sin esperar R2 ni una cola de validación', function (ResultadoValidacionFirma $estado): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.signature_async', true);
    Queue::fake([VerificarFirmaDocumentoJob::class]);
    $usuario = User::factory()->depositante()->create();
    foreach (['usuarios.cargos_catalogo' => 'Cargo QA firma', 'usuarios.instituciones_catalogo' => 'Institución QA firma'] as $tabla => $nombre) {
        DB::table($tabla)->updateOrInsert(['nombre' => $nombre], ['activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    $nombre = 'Documento de explicación de motivos y/o carta de justificación (institucional o personal)';
    $modelo = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(), 'numero' => sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999)),
        'investigador_id' => (string) $usuario->id, 'tipo_tramite' => 'Depósito',
        'estado' => 'En Borrador', 'paso_actual' => 3, 'documentos_requeridos' => [$nombre],
        'documentos_cargados' => [], 'solicitud_nombre_permiso' => 'Ana Perez',
        'solicitud_tipo_identificacion' => 'cedula', 'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => 'Cargo QA firma', 'solicitud_institucion' => 'Institución QA firma',
        'solicitud_grupo' => 'Macroinvertebrados acuáticos', 'solicitud_proyecto' => 'Proyecto QA',
        'solicitud_correo' => $usuario->email, 'solicitud_oficio' => 'QA-FIRMA-LOCAL',
        'solicitud_firmada_en' => now(), 'solicitud_firmada_ruta' => 'depositos/qa/oficio.pdf',
        'solicitud_firmada_sha256' => str_repeat('a', 64),
    ]);
    $validador = new class($estado) implements ValidacionFirmaElectronicaPort
    {
        public ?string $huella = null;
        public function __construct(private readonly ResultadoValidacionFirma $estado) {}
        public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
        {
            Http::assertNotSent(fn ($request): bool => $request->method() === 'PUT');
            $this->huella = hash_file('sha256', $rutaAbsoluta);
            return $this->estado;
        }
        public function verificarFirmaDetallada(string $rutaFirmadaAbsoluta, string $rutaOriginalAbsoluta): DetalleValidacionFirma
        {
            throw new LogicException('La carga externa no compara con un oficio original.');
        }
    };
    app()->instance(ValidacionFirmaElectronicaPort::class, $validador);
    $pdf = pdfValidoParaDepositos('Carta de justificacion. Ana Perez. Proyecto QA. Especimenes para deposito. No dispongo de permisos.');
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->set('archivoCartaJustificacion', UploadedFile::fake()->createWithContent('documento.pdf', $pdf))
        ->assertSet('firmasElectronicas.'.$nombre, $estado->value);
    $actual = $modelo->fresh();
    $previa = $actual->validacion_previa_documentos[$nombre];
    expect($previa['sha256'])->toBe($validador->huella)
        ->and($previa['firma_estado'])->toBe($estado->value)
        ->and($previa['firma_verificada'])->toBe($estado !== ResultadoValidacionFirma::VerificacionNoDisponible)
        ->and($actual->paso_actual)->toBe(3);
    Queue::assertNotPushed(VerificarFirmaDocumentoJob::class);
    Http::assertNothingSent();
    $registro = DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $previa['r2_pendiente_id'])->first();
    expect($registro->estado)->toBe('pendiente')->and($registro->contenido_cifrado)->not->toContain('%PDF-');
    expect(DB::table('jobs')->where('queue', 'r2-signatures')->where('payload', 'like', '%'.$registro->id.'%')->exists())->toBeTrue();
    $copia = app(AlmacenamientoDepositos::class)->copiaLocal($registro->ruta);
    try { expect(hash_file('sha256', $copia->ruta()))->toBe(hash('sha256', $pdf)); }
    finally { $copia->limpiar(); }
    expect(app(AlmacenamientoDepositos::class)->sha256($registro->ruta))->toBe(hash('sha256', $pdf));
    Http::assertNothingSent();
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->assertSet('documentosCargados.'.$nombre, $registro->ruta);
    Http::assertNothingSent();
    $cola = app(ColaFirmasSolicitudR2::class);
    // Volver a verificar la firma no cambia la identidad del ejemplar en cola.
    $previas = $actual->validacion_previa_documentos;
    $previas[$nombre]['verificacion_id'] = (string) Str::uuid();
    $actual->forceFill(['validacion_previa_documentos' => $previas])->save();
    $cola->entregar($registro->id, app(AlmacenamientoDepositos::class));
    expect(DB::table(ColaFirmasSolicitudR2::TABLA)->where('id', $registro->id)->value('estado'))->toBe('confirmado');
    expect(app(AlmacenamientoDepositos::class)->sha256($registro->ruta))->toBe(hash('sha256', $pdf));
})->with([ResultadoValidacionFirma::Firmado, ResultadoValidacionFirma::SinFirma, ResultadoValidacionFirma::VerificacionNoDisponible, ResultadoValidacionFirma::FirmaInvalida]);
