<?php

use App\Models\User;
use App\Livewire\Dashboard;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\MisDepositos;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('el depositante retoma su borrador y los accesos del dashboard filtran los estados completos', function () {
    $usuario = User::factory()->depositante()->create();
    $ajeno = User::factory()->depositante()->create();
    $estados = [EstadoSolicitudDeposito::EnBorrador, EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria,
        EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia, EstadoSolicitudDeposito::RequiereCorreccion,
        EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial, EstadoSolicitudDeposito::Rechazada,
        EstadoSolicitudDeposito::RechazoPermanente];
    foreach ([$usuario, $ajeno] as $indice => $propietario) {
        foreach ($estados as $numeroEstado => $estado) {
            SolicitudDepositoEloquentModel::query()->create([
                'id' => (string) Str::uuid(), 'numero' => 'QA-DASH-'.$indice.'-'.$numeroEstado,
                'investigador_id' => (string) $propietario->id, 'tipo_tramite' => 'Donación',
                'estado' => $estado->value, 'paso_actual' => 0, 'documentos_adjuntos' => [], 'datos_faltantes' => [],
            ]);
        }
    }
    $this->actingAs($usuario);
    Livewire::test(Dashboard::class)->assertViewHas('totalEnviadas', 6)
        ->assertViewHas('pendientesRevision', 2)->assertViewHas('rechazadas', 2)
        ->assertViewHas('requierenCorreccion', 1)->assertSee('Retomar borrador')->assertSee('Paso 1 de 8')
        ->assertDontSee('QA-DASH-1-');
    $lista = Livewire::withQueryParams(['estado' => 'por_revisar'])->test(MisDepositos::class)
        ->assertSet('filtroEstado', 'por_revisar')->assertViewHas('activas', fn ($r) => $r->count() === 2)
        ->assertSee('Retomar borrador')->assertDontSee('QA-DASH-1-');
    $lista->set('filtroEstado', 'rechazadas')->assertViewHas('historial', fn ($r) => $r->count() === 2)
        ->call('limpiarFiltros')->set('soloEnProceso', true)->assertViewHas('activas', fn ($r) => $r->count() === 4)
        ->assertViewHas('historial', fn ($r) => $r->isEmpty())
        ->call('limpiarFiltros')->assertSet('soloEnProceso', false)
        ->set('filtroTipo', 'Depósito')->assertViewHas('depositos', fn ($r) => $r->isEmpty());
    $lista->call('limpiarFiltros')->set('filtroDesde', 'fecha-inválida')->assertHasErrors('filtroDesde')
        ->set('filtroDesde', '2026-10-11')->set('filtroHasta', '2026-10-10')->assertHasErrors('filtroHasta')
        ->call('limpiarFiltros')->assertHasNoErrors();
});
