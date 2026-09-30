<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

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
