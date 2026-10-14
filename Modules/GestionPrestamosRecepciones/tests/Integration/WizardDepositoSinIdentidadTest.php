<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisDepositos;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

/** @return array{SolicitudDepositoEloquentModel, User} */
function borradorWizardSinIdentidadParaPruebas(int $paso = 4): array
{
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $usuario = User::findOrFail($modelo->investigador_id);
    $usuario->update(['cargo' => $modelo->solicitud_cargo, 'institucion' => $modelo->solicitud_institucion]);
    $almacenamiento = app(AlmacenamientoDepositos::class);
    $ruta = 'depositos/qa/'.$modelo->id.'/oficio.pdf';
    // Sólo se prepara el estado durable de navegación. La firma real conserva
    // sus pruebas criptográficas y no se sustituye por estos bytes de fixture.
    $huella = $almacenamiento->guardarContenido($ruta, '%PDF-fixture navegación sin identidad', 'application/pdf');
    $carta = 'Documento de explicación de motivos y/o carta de justificación (institucional o personal)';
    $rutaCarta = 'depositos/qa/'.$modelo->id.'/carta.pdf';
    $almacenamiento->guardarContenido($rutaCarta, '%PDF-fixture documental para navegación', 'application/pdf');
    $modelo->forceFill([
        'paso_actual' => $paso,
        'solicitud_firmada_en' => now(), 'solicitud_firmada_ruta' => $ruta,
        'solicitud_firmada_sha256' => $huella, 'solicitud_firma_metadata' => ['pdf_sha256' => $huella],
        'origen_recoleccion' => 'Nacional (Ecuador)', 'situacion_regulatoria' => 'Sin permisos del MAE',
        'provincia_origen' => 'Pichincha', 'localidad' => 'Quito', 'grupo_animal' => 'Crustáceos',
        'nro_individuos' => 12, 'nro_morfoespecies' => 1, 'nro_lotes' => 2,
        'nombre_investigador_documento' => 'Persona del documento',
        'extraccion_estado' => 'completada', 'datos_faltantes' => [],
        'documentos_requeridos' => [$carta], 'documentos_cargados' => [$carta => $rutaCarta],
        'validacion_archivos' => [$carta => 'valido'], 'firmas_electronicas' => [$carta => 'firmado'],
        'extraccion_metadatos' => ['wizard_pasos_completados' => range(1, $paso - 1), 'evidencia_previa' => 'conservar'],
    ])->save();

    return [$modelo, $usuario->refresh()];
}

test('datos avanza directamente a detalle sin comparar nombres y confirma la extracción', function (string $nombrePerfil): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    [$nombres, $apellidos] = array_pad(explode(' ', $nombrePerfil, 2), 2, '');
    $usuario->update(['first_name' => $nombres, 'last_name' => $apellidos]);
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSet('paso', 4)->assertSee('(6) Detalle')->assertSee('(7) Envío')
        ->assertDontSee('(8) Envío')->assertDontSee('Validación de identidad')
        ->call('guardarPasoCuatro')->assertSet('paso', 7)
        ->assertSee('Detalle biológico asistido')->assertDontSee('Discrepancia')
        ->assertDontSee('Carta de delegación / justificación de tercero');
    expect($componente->instance()->pasoVisible())->toBe(6)
        ->and($componente->instance()->pasosCompletadosVisibles())->toBe([1, 2, 3, 4, 5]);
    $modelo->refresh();
    $datos = $componente->get('datosExtraidos');
    ksort($datos);
    expect($modelo->paso_actual)->toBe(7)
        ->and($modelo->extraccion_metadatos['confirmacion_humana']['estado'])->toBe('confirmada')
        ->and($modelo->extraccion_metadatos['confirmacion_humana']['usuario_id'])->toBe((string) $usuario->id)
        ->and($modelo->extraccion_metadatos['confirmacion_humana']['valores_sha256'])->toBe(hash('sha256', json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))
        ->and($modelo->extraccion_metadatos['evidencia_previa'])->toBe('conservar')
        ->and($componente->get('registroNativo')['verbatimLocality'])->toBe('Quito')
        ->and($componente->get('registroNativo')['recordedBy'])->toBe($nombrePerfil)
        ->and($modelo->solicitud_firmada_ruta)->not->toBeNull();
    $componente->call('retroceder')->assertSet('paso', 4)->assertSee('Datos de la solicitud');
    expect($modelo->fresh()->paso_actual)->toBe(4);
})->with(['Persona del documento', 'Persona', 'Otra persona completamente distinta']);

test('reanuda los pasos antiguos con siete números visibles y sin pantalla de identidad', function (int $pasoAntiguo, int $pasoActual, int $numeroVisible): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas($pasoAntiguo);
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSet('paso', $pasoActual)->assertSee('(6) Detalle')->assertSee('(7) Envío')
        ->assertSee('--wizard-step-count: 7', false)->assertDontSee('(6) Identidad')
        ->assertDontSee('Validación de identidad')->assertDontSee('Discrepancia');
    expect($componente->instance()->pasoVisible())->toBe($numeroVisible)
        ->and($componente->get('pasosCompletados'))->not->toContain(5, 6)
        ->and($componente->get('registroNativo')['verbatimLocality'])->toBe('Quito')
        ->and($modelo->fresh()->solicitud_firmada_ruta)->toBe($modelo->solicitud_firmada_ruta);
    if ($pasoAntiguo === 8) {
        $componente->set('declaracionAceptada', true)->call('retroceder')
            ->assertSet('paso', 7)->assertSet('declaracionAceptada', false);
        expect($componente->instance()->pasoVisible())->toBe(6);
        $componente->call('volverAPasoCompletado', 5)->assertSet('paso', 4)
            ->call('volverAPasoCompletado', 6)->assertSet('paso', 7)
            ->call('volverAPasoCompletado', 7)->assertSet('paso', 7)
            ->call('volverAPasoCompletado', 8)->assertSet('paso', 7);
        expect($modelo->fresh()->paso_actual)->toBe(7);
    }
})->with([[6, 7, 6], [7, 7, 6], [8, 8, 7]]);

test('el borrador que quedó en identidad vuelve a datos si conserva una cantidad cero', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas(6);
    $modelo->forceFill(['nro_individuos' => 0])->save();
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSet('paso', 4)->call('guardarPasoCuatro')->assertSet('paso', 4);
    expect($componente->get('datosFaltantes'))->toContain('N.º Individuos')
        ->and($componente->instance()->pasosCompletadosVisibles())->toBe([1, 2, 3, 4])
        ->and($modelo->fresh()->extraccion_metadatos)->not->toHaveKey('confirmacion_humana');
});

test('recupera el progreso antiguo sin historial de círculos y conserva los bytes del oficio', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas(6);
    $modelo->forceFill(['extraccion_metadatos' => ['evidencia_previa' => 'conservar']])->save();
    $ruta = $modelo->solicitud_firmada_ruta;
    $huella = $modelo->solicitud_firmada_sha256;
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSet('paso', 7)->assertSee('Detalle biológico asistido');
    expect($componente->instance()->pasosCompletadosVisibles())->toBe([1, 2, 3, 4, 5]);
    $componente->call('volverAPasoCompletado', 5)->assertSet('paso', 4);
    expect($modelo->fresh()->extraccion_metadatos)->toMatchArray(['wizard_pasos_completados' => [1, 2, 3, 4], 'evidencia_previa' => 'conservar'])
        ->and($modelo->fresh()->solicitud_firmada_sha256)->toBe($huella)
        ->and(app(AlmacenamientoDepositos::class)->obtener($ruta))->toBe('%PDF-fixture navegación sin identidad');
});

test('retirar identidad no permite avanzar desde datos con una firma documental pendiente', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $carta = array_key_first($modelo->firmas_electronicas);
    $modelo->forceFill(['firmas_electronicas' => [$carta => 'sin_firma']])->save();
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->call('guardarPasoCuatro')->assertSet('paso', 4);
    expect($modelo->fresh()->paso_actual)->toBe(4)
        ->and($modelo->fresh()->extraccion_metadatos)->not->toHaveKey('confirmacion_humana');
});

test('mis depósitos comparte los siete números del wizard al retomar un borrador', function (int $pasoPersistido, int $numeroVisible): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas($pasoPersistido);
    Livewire::actingAs($usuario)->test(MisDepositos::class)
        ->assertSee('Paso '.$numeroVisible.' de 7')->assertDontSee('de 8');
})->with([[4, 5], [6, 6], [7, 6], [8, 7]]);
