<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisDepositos;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RegistroEspecimenEloquentModel;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);



test('datos avanza directamente a detalle sin comparar nombres y confirma la extracción', function (string $nombrePerfil): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    [$nombres, $apellidos] = array_pad(explode(' ', $nombrePerfil, 2), 2, '');
    $usuario->update(['first_name' => $nombres, 'last_name' => $apellidos]);
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSet('paso', 4)->assertSee('(6) Detalle')->assertSee('(7) Envío')
        ->assertDontSee('(8) Envío')->assertDontSee('Validación de identidad')
        ->call('guardarPasoCuatro')->assertSet('paso', 7)
        ->assertSee('Especímenes y lotes')->assertDontSee('Discrepancia')
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
    $componente->assertSet('paso', 7)->assertSee('Especímenes y lotes');
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

test('datos continúa sin localidad y retira el requisito antiguo sin retirar otros campos', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $modelo->forceFill(['localidad' => null, 'localidad_origen_codigo' => null, 'datos_faltantes' => ['Localidad']])->save();
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSee('Localidad (opcional)')->call('guardarPasoCuatro')->assertSet('paso', 7);
    expect($componente->get('datosFaltantes'))->not->toContain('Localidad')
        ->and($componente->get('registroNativo')['verbatimLocality'])->toBe('');
});

test('el avance siempre identifica el siguiente paso y no ofrece agregar registros', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->assertSee('Ir al Paso 6 Detalle')->call('guardarPasoCuatro')->assertSet('paso', 7)
        ->assertSee('Ir al Paso 7 Envío')->assertDontSee('Agregar registro')
        ->assertSee('Identificación del material')->assertSee('Lugar de recolección')
        ->assertSee('Datos de colecta')->assertSee('Conservación y observaciones');
});

test('el editor permite borrar la localidad sin borrar los otros datos ni bloquear el avance', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $componente = Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id]);
    $componente->call('abrirEditorManual', 'Localidad')->set('valorEditorManual', '')
        ->call('guardarEditorManual')->assertHasNoErrors()->assertSet('localidad', '')
        ->assertSee('No indicada')->call('guardarPasoCuatro')->assertSet('paso', 7);
    expect($modelo->fresh()->localidad)->toBeNull()->and($modelo->fresh()->localidad_origen_codigo)->toBeNull()
        ->and($modelo->fresh()->nro_individuos)->toBe(12)->and($modelo->fresh()->provincia_origen)->toBe('Pichincha');
});



test('el avance guarda un detalle sin localidad y permite revisar editar y reanudar el mismo registro', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $modelo->forceFill(['localidad' => null, 'localidad_origen_codigo' => null])->save();
    $componente = detalleNativoWizardParaPruebas($modelo, $usuario);
    $componente->call('guardarPasoCinco')->assertHasNoErrors()->assertSet('paso', 8)
        ->assertSee('Paso 7 Revisar y Enviar')->assertSet('registroNativo.scientificName', 'Atta cephalotes');
    $matrizId = $componente->get('matrizId');
    $registros = RegistroEspecimenEloquentModel::where('matriz_id', $matrizId)->get();
    expect($registros)->toHaveCount(1)->and($registros[0]->datos_dwc['individualCount'])->toBe(12)
        ->and($registros[0]->datos_dwc['verbatimLocality'] ?? null)->toBeNull()
        ->and($modelo->fresh()->matriz_id)->toBe($matrizId);
    $componente->call('retroceder')->assertSet('paso', 7)
        ->set('registroNativo.individualCount', 13)->call('guardarPasoCinco')->assertHasNoErrors()->assertSet('paso', 8);
    $matrizId = $componente->get('matrizId');
    expect(MatrizEspeciesEloquentModel::where('solicitud_id', $modelo->id)->count())->toBe(1)
        ->and($componente->get('estadosRegistros'))->toHaveCount(1)
        ->and(RegistroEspecimenEloquentModel::where('matriz_id', $matrizId)->firstOrFail()->datos_dwc['individualCount'])->toBe(13);
    Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->assertSet('paso', 8)->call('retroceder')->assertSet('paso', 7)
        ->assertSet('registroNativo.scientificName', 'Atta cephalotes')
        ->assertSet('registroNativo.individualCount', 13)->assertSet('registroNativo.verbatimLocality', '');
});

test('un detalle inválido conserva la matriz anterior y puede corregirse sin otro botón', function (): void {
    [$modelo, $usuario] = borradorWizardSinIdentidadParaPruebas();
    $componente = detalleNativoWizardParaPruebas($modelo, $usuario);
    $componente->call('guardarPasoCinco')->assertHasNoErrors()->assertSet('paso', 8)->call('retroceder');
    $matrizId = $componente->get('matrizId');
    $componente->set('registroNativo.individualCount', 0)->call('guardarPasoCinco')
        ->assertHasErrors('registroNativo.individualCount')->assertSet('paso', 7);
    expect(RegistroEspecimenEloquentModel::where('matriz_id', $matrizId)->firstOrFail()->datos_dwc['individualCount'])->toBe(12);
    $componente->set('registroNativo.individualCount', 12);
    $fila = $componente->get('registrosNativos')[0];
    unset($fila['decimalLatitude']);
    $componente->set('registrosNativos', [$fila])->call('guardarMatrizNativa')->assertSet('paso', 7);
    expect($componente->get('errorMatriz'))->not->toBe('')
        ->and($modelo->fresh()->matriz_id)->toBe($matrizId)
        ->and(MatrizEspeciesEloquentModel::whereKey($matrizId)->exists())->toBeTrue()
        ->and(RegistroEspecimenEloquentModel::where('matriz_id', $matrizId)->count())->toBe(1);
    $componente->call('guardarPasoCinco')->assertHasNoErrors()->assertSet('errorMatriz', '')->assertSet('paso', 8);
    expect(MatrizEspeciesEloquentModel::where('solicitud_id', $modelo->id)->count())->toBe(1);
});
