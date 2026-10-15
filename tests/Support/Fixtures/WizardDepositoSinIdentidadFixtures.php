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

/** Guarda de verdad el detalle en PostgreSQL; sólo el catálogo remoto se sustituye. */
function detalleNativoWizardParaPruebas(SolicitudDepositoEloquentModel $modelo, User $usuario)
{
    $validador = Mockery::mock(ValidacionTaxonomicaPort::class);
    $validador->shouldReceive('validarEspecies')->andReturnUsing(fn (array $nombres): array => array_map(
        fn (string $nombre): array => ['nombreCientifico' => $nombre, 'estado' => 'catalogado', 'sugerencia' => null, 'sugerencias' => []], $nombres,
    ));
    app()->instance(ValidacionTaxonomicaPort::class, $validador);

    return Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->call('guardarPasoCuatro')->assertSet('paso', 7)
        ->set('opcionesTaxones', [['nombre' => 'Atta cephalotes', 'rango' => 'species', 'fuente' => 'EPN', 'kingdom' => 'Animalia', 'phylum' => 'Arthropoda']])
        ->call('seleccionarTaxon', 'Atta cephalotes')
        ->set('registroNativo.recordNumber', 'QA-DETALLE-001')->set('registroNativo.eventDate', '2026-10-01')
        ->set('registroNativo.decimalLatitude', '-0.4567')->set('registroNativo.decimalLongitude', '-78.4567')
        ->set('registroNativo.individualCount', 12)->set('registroNativo.samplingProtocol', 'hand_collection');
}
