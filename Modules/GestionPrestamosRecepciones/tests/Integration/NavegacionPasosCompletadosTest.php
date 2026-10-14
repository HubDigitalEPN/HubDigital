<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('volver a un paso completado conserva el progreso durable y bloquea un destino pendiente', function (): void {
    $usuario = User::factory()->depositante()->create();
    $modelo = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(), 'numero' => sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999)),
        'investigador_id' => (string) $usuario->id, 'tipo_tramite' => 'Depósito', 'estado' => 'En Borrador', 'paso_actual' => 3,
        'solicitud_nombre_permiso' => 'Ana Perez', 'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => 'Investigadora', 'solicitud_grupo' => 'Insecta', 'solicitud_proyecto' => 'Proyecto QA',
        'solicitud_institucion' => 'Institución QA', 'solicitud_correo' => $usuario->email, 'solicitud_oficio' => 'QA-NAV',
        'solicitud_firmada_en' => now(), 'solicitud_firmada_ruta' => 'depositos/qa/oficio.pdf', 'solicitud_firmada_sha256' => str_repeat('a', 64),
        'extraccion_metadatos' => ['wizard_pasos_completados' => [1, 2], 'evidencia_previa' => 'conservar'],
    ]);
    $this->actingAs($usuario);
    $componente = new RegistroSolicitudDeposito;
    $componente->solicitudId = $modelo->id;
    $componente->paso = 3;
    $componente->pasosCompletados = [1, 2];
    $componente->volverAPasoCompletado(3);
    expect($componente->paso)->toBe(2)->and($modelo->fresh()->paso_actual)->toBe(2)
        ->and($modelo->fresh()->extraccion_metadatos)->toMatchArray(['wizard_pasos_completados' => [1, 2], 'evidencia_previa' => 'conservar']);
    $componente->volverAPasoCompletado(5);
    expect($componente->paso)->toBe(2)->and($modelo->fresh()->paso_actual)->toBe(2);
    $this->actingAs(User::factory()->depositante()->create());
    expect(fn () => $componente->volverAPasoCompletado(1))->toThrow(ModelNotFoundException::class);
    expect($modelo->fresh()->paso_actual)->toBe(2);
});
