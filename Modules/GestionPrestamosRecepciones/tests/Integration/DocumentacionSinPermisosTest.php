<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;
use Tests\DatabaseFeatureTestCase;
use App\Models\User;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

uses(DatabaseFeatureTestCase::class);

test('el estado documental respeta la carta requerida sin permisos y conserva el requisito con permisos', function (string $situacion, array $documentos, EstadoDocumental $esperado): void {
    $modelo = solicitudColaR2ParaPruebas();
    $modelo->forceFill(['origen_recoleccion' => 'Nacional (Ecuador)', 'situacion_regulatoria' => $situacion,
        'provincia_origen' => 'Santo Domingo de los Tsáchilas'])->save();
    $resultado = app(ValidarDocumentacionInicialHandler::class)(new ValidarDocumentacionInicialInput(
        $modelo->id, $modelo->provincia_origen, array_fill_keys($documentos, 'depositos/qa/documento.pdf'),
    ));
    expect($resultado->estadoDocumental)->toBe($esperado);
})->with([
    ['Sin permisos del MAE', ['Documento de explicación de motivos y/o carta de justificación (institucional o personal)'], EstadoDocumental::Valido],
    ['Sin permisos del MAE', [], EstadoDocumental::RequiereCorreccion],
    ['Posee permisos del MAE', ['Copia de la autorización de recolección (MAE)'], EstadoDocumental::RequiereCorreccion],
    ['Posee permisos del MAE', ['Copia de la autorización de recolección (MAE)', 'Copia del permiso de movilización'], EstadoDocumental::Valido],
]);

test('reanuda datos y retrocede sin pedir permisos inexistentes ni quitar cantidades obligatorias', function (string $situacion, bool $conPermisos): void {
    configurarR2FalsoParaPruebas();
    $modelo = solicitudColaR2ParaPruebas();
    $rutaOficio = 'depositos/qa/'.$modelo->id.'/oficio.pdf';
    // Fixture de reanudación; la criptografía real tiene sus propios casos y aceptación manual.
    $huellaOficio = app(AlmacenamientoDepositos::class)->guardarContenido(
        $rutaOficio, '%PDF-fixture de oficio conservado para navegación', 'application/pdf',
    );
    $modelo->forceFill([
        'solicitud_firmada_en' => now(), 'solicitud_firmada_ruta' => $rutaOficio,
        'solicitud_firmada_sha256' => $huellaOficio, 'solicitud_firma_metadata' => ['pdf_sha256' => $huellaOficio],
        'origen_recoleccion' => 'Nacional (Ecuador)', 'situacion_regulatoria' => $situacion,
        'provincia_origen' => 'Santo Domingo de los Tsáchilas', 'paso_actual' => 4,
        'extraccion_estado' => 'completada', 'datos_faltantes' => ['N.º Permiso Recolección', 'N.º Permiso Movilización'],
        'documentos_requeridos' => ['Documento de explicación de motivos y/o carta de justificación (institucional o personal)'],
    ])->save();
    $solicitud = app(SolicitudDepositoRepositoryInterface::class)->buscarPorId(SolicitudDepositoId::from($modelo->id));
    expect($solicitud->datosFaltantesParaPersistir())->toBe($conPermisos
        ? ['N.º Permiso Recolección', 'N.º Permiso Movilización'] : []);
    $componente = Livewire::actingAs(User::findOrFail($modelo->investigador_id))
        ->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->assertSet('solicitudFirmada', true)->assertSet('paso', 4);
    $datos = $componente->get('datosExtraidos');
    expect(array_key_exists('N.º Permiso Recolección', $datos))->toBe($conPermisos)
        ->and(array_key_exists('N.º Permiso Movilización', $datos))->toBe($conPermisos)
        ->and(in_array('N.º Permiso Movilización', $componente->get('datosFaltantes'), true))->toBe($conPermisos);
    $componente->assertSet('datosFaltantes', $conPermisos
        ? ['N.º Permiso Recolección', 'N.º Permiso Movilización', 'N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes']
        : ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes']);
    $componente->call('abrirEditorManual', 'N.º Morfoespecies')->set('valorEditorManual', '-1')
        ->call('guardarEditorManual')->assertHasErrors('valorEditorManual')
        ->assertSet('campoEditorManual', 'N.º Morfoespecies');
    expect($modelo->fresh()->nro_morfoespecies)->toBeNull();
    $componente->set('valorEditorManual', '0')->call('guardarEditorManual')->assertHasErrors('valorEditorManual');
    expect($modelo->fresh()->nro_morfoespecies)->toBeNull();
    $componente->set('valorEditorManual', '1')->call('guardarEditorManual')
        ->assertHasNoErrors()->assertSet('campoEditorManual', '');
    // «N.º» contiene un punto literal, no una ruta anidada de Livewire.
    expect($componente->get('datosExtraidos')['N.º Morfoespecies'])->toBe('1')
        ->and((string) $modelo->fresh()->nro_morfoespecies)->toBe('1');
    // Los borradores guardados antes de exigir cantidades positivas también deben corregirse.
    $modelo->forceFill(['nro_morfoespecies' => '0'])->save();
    $reanudada = app(SolicitudDepositoRepositoryInterface::class)->buscarPorId(SolicitudDepositoId::from($modelo->id));
    expect($reanudada->datosFaltantesParaPersistir())->toContain('N.º Morfoespecies');
    Livewire::actingAs(User::findOrFail($modelo->investigador_id))
        ->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->assertSet('datosFaltantes', fn (array $campos) => in_array('N.º Morfoespecies', $campos, true));
    $modelo->forceFill(['nro_morfoespecies' => '1'])->save();
    $componente->call('retroceder')->assertSet('paso', 3);
    expect(in_array('Copia del permiso de movilización', $componente->get('documentosRequeridos'), true))->toBe($conPermisos);
    expect($modelo->fresh()->paso_actual)->toBe(3);
})->with([
    ['Sin permisos del MAE', false],
    ['Posee permisos del MAE', true],
]);
