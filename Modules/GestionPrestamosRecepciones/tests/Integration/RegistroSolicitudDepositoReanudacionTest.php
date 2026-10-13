<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('reanuda el oficio firmado solo con valores vigentes y conserva el PDF anterior sin editarlo', function (?string $campo, ?string $valor): void {
    configurarR2FalsoParaPruebas();
    foreach (['usuarios.cargos_catalogo' => 'Investigador de reanudación', 'usuarios.instituciones_catalogo' => 'Institución de reanudación'] as $tabla => $nombre) {
        DB::table($tabla)->updateOrInsert(['nombre' => $nombre], ['activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();
    $ruta = 'depositos/'.$solicitudId.'/oficio-anterior.pdf';
    $contenido = '%PDF-oficio anterior: fixture de persistencia, no de criptografía';
    $almacenamiento = app(AlmacenamientoDepositos::class);
    $huella = $almacenamiento->guardarContenido($ruta, $contenido, 'application/pdf');
    $datos = [
        'id' => $solicitudId,
        'numero' => sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999)),
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'En Borrador',
        'paso_actual' => 1,
        'documentos_cargados' => [],
        'solicitud_nombre_permiso' => 'Titular de reanudación',
        'solicitud_tipo_identificacion' => 'cedula',
        'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => 'Investigador de reanudación',
        'solicitud_institucion' => 'Institución de reanudación',
        'solicitud_grupo' => 'Macroinvertebrados acuáticos',
        'solicitud_proyecto' => 'Proyecto de reanudación',
        'solicitud_correo' => $depositante->email,
        'solicitud_oficio' => 'QA-REANUDAR-001',
        'solicitud_documento_version' => 3,
        'solicitud_firmada_en' => now(),
        'solicitud_firmada_ruta' => $ruta,
        'solicitud_firmada_sha256' => $huella,
        'solicitud_firma_metadata' => ['pdf_sha256' => $huella],
    ];
    if ($campo !== null) $datos[\Illuminate\Support\Str::snake($campo)] = $valor;
    $modelo = SolicitudDepositoEloquentModel::query()->create($datos);
    $antes = $modelo->fresh()->getAttributes();
    $component = Livewire::actingAs($depositante)->test(RegistroSolicitudDeposito::class);
    if ($campo !== null) {
        $component->assertSet('paso', 0)
            ->assertSet('solicitudFirmada', false)
            ->assertSet('solicitudCambiosPendientes', true)
            ->assertSee('PDF Firmado, versión anterior')
            ->assertSee('hub-request-fields--locked', false)
            ->assertHasErrors($campo)
            ->call('continuarDesdeSolicitud')
            ->assertHasErrors($campo)->assertSet('paso', 0);
        expect($modelo->fresh()->getAttributes())->toBe($antes);
    } else {
        $component->assertSet('paso', 1)
            ->assertSet('solicitudFirmada', true)
            ->assertSet('solicitudCambiosPendientes', false);
        // El catálogo puede cambiar después de montar el formulario. La
        // validación del servidor también debe bloquear esa petición posterior.
        DB::table('usuarios.instituciones_catalogo')->where('nombre', 'Institución de reanudación')->update(['activo' => false]);
        $component->call('continuarDesdeSolicitud')
            ->assertHasErrors('solicitudInstitucion')->assertSet('paso', 0);
    }
    expect($modelo->fresh()->solicitud_firmada_ruta)->toBe($ruta)
        ->and($modelo->fresh()->solicitud_firmada_sha256)->toBe($huella)
        ->and($modelo->fresh()->solicitud_documento_version)->toBe(3)
        ->and($almacenamiento->obtener($ruta))->toBe($contenido);
    Http::assertNotSent(static fn (\Illuminate\Http\Client\Request $request): bool => $request->method() === 'DELETE');
})->with([
    'cargo anterior' => ['solicitudCargo', 'Cargo fuera del catálogo'],
    'institución anterior' => ['solicitudInstitucion', 'Institución fuera del catálogo'],
    'grupo anterior' => ['solicitudGrupo', 'Grupo fuera del catálogo'],
    'oficio vigente y catálogo desactivado después' => [null, null],
]);

test('conserva la matriz pero exige firmar la solicitud al reanudar una corrección antigua', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();
    $matrizId = (string) Str::uuid();
    $numero = sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999));

    SolicitudDepositoEloquentModel::query()->create([
        'id' => $solicitudId,
        'numero' => $numero,
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Requiere Corrección',
        'paso_actual' => 4,
        'documentos_adjuntos' => [],
        'documentos_cargados' => [],
        'datos_faltantes' => [],
        'datos_ingresados_manualmente' => [],
        'nro_permiso_recoleccion' => 'MAATE-QA-2026-001',
        'nro_permiso_movilizacion' => 'GUIA-QA-2026-001',
        'localidad' => 'Quito, Pichincha',
        'provincia_origen' => 'Pichincha',
    ]);

    MatrizEspeciesEloquentModel::query()->create([
        'id' => $matrizId,
        'solicitud_id' => $solicitudId,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Validada Técnicamente',
        'campos_dwc_presentes' => ['scientificName' => true],
        'identificacion_original_conservada' => false,
    ]);

    SolicitudDepositoEloquentModel::query()
        ->whereKey($solicitudId)
        ->update(['matriz_id' => $matrizId]);

    $this->actingAs($depositante);
    $component = app(RegistroSolicitudDeposito::class);
    $component->mount($solicitudId);

    expect($component->paso)->toBe(0)
        ->and($component->matrizId)->toBe($matrizId)
        ->and($component->matrizCargada)->toBeTrue();
});

test('reabre una solicitud antigua sin firma en el primer paso y conserva los datos extraídos', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();

    SolicitudDepositoEloquentModel::query()->create([
        'id' => $solicitudId,
        'numero' => sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999)),
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'En Borrador',
        'paso_actual' => 5,
        'documentos_adjuntos' => [],
        'documentos_cargados' => [],
        'datos_faltantes' => [],
        'datos_ingresados_manualmente' => [],
        'nro_permiso_recoleccion' => 'MAATE-QA-2026-001',
        'nro_permiso_movilizacion' => 'GUIA-QA-2026-001',
        'localidad' => 'Quito, Pichincha',
        'provincia_origen' => 'Pichincha',
    ]);

    $this->actingAs($depositante);
    $component = app(RegistroSolicitudDeposito::class);
    $component->mount($solicitudId);

    expect($component->paso)->toBe(0)
        ->and($component->solicitudFirmada)->toBeFalse()
        ->and($component->datosExtraidos['N.º Permiso Recolección'])->toBe('MAATE-QA-2026-001')
        ->and($component->datosExtraidos['N.º Permiso Movilización'])->toBe('GUIA-QA-2026-001');
});

test('presenta el estado persistido al reabrir una solicitud ya enviada sin instrucciones de corrección', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();
    $numero = sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999));

    SolicitudDepositoEloquentModel::query()->create([
        'id' => $solicitudId,
        'numero' => $numero,
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Pendiente de Revisión por Curaduría',
        'paso_actual' => 6,
        'documentos_adjuntos' => [],
        'documentos_cargados' => [],
        'datos_faltantes' => [],
        'datos_ingresados_manualmente' => [],
    ]);

    $this->actingAs($depositante);
    Livewire::test(RegistroSolicitudDeposito::class, ['id' => $solicitudId])
        ->assertSet('paso', 9)
        ->assertSet('estadoFinal', 'Pendiente de Revisión por Curaduría')
        ->assertSet('mensajeEstadoSincronizado', 'Esta solicitud ya fue enviada y está pendiente de revisión.')
        ->assertSet('modoCorreccion', false)
        ->assertSet('comentarioCurador', '')
        ->assertSee('Esta solicitud ya fue enviada y está pendiente de revisión.')
        ->assertSee('Ver detalle del expediente')
        ->assertDontSee('Estás corrigiendo una solicitud devuelta por la curaduría');
});
