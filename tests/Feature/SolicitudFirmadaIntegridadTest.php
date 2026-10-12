<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\InvalidarFirmaSolicitud;

beforeEach(function (): void {
    configurarR2FalsoParaPruebas();
});

function solicitudFirmadaParaIntegridad(User $depositante): SolicitudDepositoEloquentModel
{
    $ruta = 'solicitudes-deposito/firmadas/'.Str::uuid().'-v1.pdf';
    $contenido = "%PDF-1.7\nsolicitud firmada vigente";
    app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)->guardarContenido($ruta, $contenido, 'application/pdf');

    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'MEPN-INV-DEP-'.random_int(70000, 99999),
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Requiere Corrección',
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
        'solicitud_firmada_ruta' => $ruta,
        'solicitud_firmada_sha256' => hash('sha256', $contenido),
        'solicitud_firmada_en' => now(),
        'solicitud_firma_metadata' => ['resultado' => 'Firmado'],
        'solicitud_documento_version' => 1,
    ]);
}

test('el estado recuperado de firma exige propietario versión e integridad de la copia persistida', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $url = route('depositos.solicitud.estado-firma', (string) $solicitud->id);
    $this->actingAs($depositante)->getJson($url.'?version=1')->assertOk()->assertJsonPath('firmada', true);
    $this->getJson($url.'?version=2')->assertOk()->assertJsonPath('firmada', false);
    $this->actingAs(User::factory()->depositante()->create())->getJson($url.'?version=1')->assertNotFound();
    app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)
        ->guardarContenido($solicitud->solicitud_firmada_ruta, "%PDF-1.7\ncontenido alterado", 'application/pdf');
    $this->actingAs($depositante)->getJson($url.'?version=1')->assertOk()->assertJsonPath('firmada', false);
});

test('un fallo al actualizar metadatos no elimina el documento firmado vigente', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $ruta = $solicitud->solicitud_firmada_ruta;

    $escucha = static function (SolicitudDepositoEloquentModel $modelo) use ($solicitud): void {
        if ((string) $modelo->id === (string) $solicitud->id && $modelo->solicitud_firmada_ruta === null) {
            throw new RuntimeException('Fallo de persistencia inyectado');
        }
    };
    SolicitudDepositoEloquentModel::saving($escucha);

    expect(fn () => app(InvalidarFirmaSolicitud::class)(
        (string) $solicitud->id,
        (string) $depositante->id,
    ))->toThrow(RuntimeException::class, 'Fallo de persistencia inyectado');

    SolicitudDepositoEloquentModel::flushEventListeners();
    $vigente = $solicitud->fresh();
    expect($vigente->solicitud_firmada_ruta)->toBe($ruta)
        ->and($vigente->solicitud_firmada_sha256)->toBe($solicitud->solicitud_firmada_sha256)
        ->and($vigente->solicitud_firmada_en)->not->toBeNull();
    expect(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)->existe($ruta))->toBeTrue();
});

test('la invalidación confirma metadatos antes de retirar la versión anterior', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $ruta = $solicitud->solicitud_firmada_ruta;

    expect(app(InvalidarFirmaSolicitud::class)(
        (string) $solicitud->id,
        (string) $depositante->id,
    ))->toBeTrue();

    $vigente = $solicitud->fresh();
    expect($vigente->solicitud_firmada_ruta)->toBeNull()
        ->and($vigente->solicitud_firmada_sha256)->toBeNull()
        ->and($vigente->solicitud_firmada_en)->toBeNull()
        ->and($vigente->solicitud_documento_version)->toBe(2);
    expect(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)->existe($ruta))->toBeFalse();
});

test('reintentar la invalidación no incrementa otra vez la versión ni revive referencias', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $ruta = $solicitud->solicitud_firmada_ruta;
    $invalidar = app(InvalidarFirmaSolicitud::class);

    expect($invalidar((string) $solicitud->id, (string) $depositante->id))->toBeTrue()
        ->and($invalidar((string) $solicitud->id, (string) $depositante->id))->toBeFalse();

    $vigente = $solicitud->fresh();
    expect($vigente->solicitud_documento_version)->toBe(2)
        ->and($vigente->solicitud_firmada_ruta)->toBeNull()
        ->and($vigente->solicitud_firmada_sha256)->toBeNull()
        ->and($vigente->solicitud_firmada_en)->toBeNull();
    expect(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)->existe($ruta))->toBeFalse();
});
