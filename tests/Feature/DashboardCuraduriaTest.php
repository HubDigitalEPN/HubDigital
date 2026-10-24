<?php

declare(strict_types=1);

use App\Models\User;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

test('el panel conserva el conteo por familia de cada espécimen y el orden de las ocho primeras', function (): void {
    $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'rango', 'nombre_cientifico'])->keyBy('id');
    $cantidades = [];
    foreach (DB::table('taxonomia.especimenes')->select('taxon_id')->cursor() as $especimen) {
        $actual = $especimen->taxon_id;
        $visitados = [];
        $familias = [];
        while ($actual && isset($taxones[$actual]) && !isset($visitados[$actual])) {
            $visitados[$actual] = true;
            $taxon = $taxones[$actual];
            if ($taxon->rango === 'familia') $familias[$taxon->nombre_cientifico] = true;
            $actual = $taxon->padre_id;
        }
        foreach (array_keys($familias) as $familia) $cantidades[$familia] = ($cantidades[$familia] ?? 0) + 1;
    }
    $esperado = collect($cantidades)->map(fn ($valor, $etiqueta) => compact('etiqueta', 'valor'))
        ->sort(fn ($a, $b) => ($b['valor'] <=> $a['valor']) ?: strcmp($a['etiqueta'], $b['etiqueta']))
        ->take(8)->values()->all();

    $this->actingAs(User::factory()->administrador()->create());
    Livewire::test(Dashboard::class)->assertViewHas('graficoFamilias', $esperado);
});

test('el administrador ve cantidades estados y tendencia de depósitos', function (): void {
    $administrador = User::factory()->administrador()->create();

    foreach ([
        EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
        EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
    ] as $indice => $estado) {
        SolicitudDepositoEloquentModel::query()->create([
            'id' => (string) Str::uuid(),
            'numero' => 'DEP-DASH-'.($indice + 1),
            'investigador_id' => (string) $administrador->id,
            'tipo_tramite' => 'Depósito',
            'estado' => $estado,
            'documentos_adjuntos' => [],
            'datos_faltantes' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($administrador)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Panel del curador')
        ->assertSee('Tendencia de solicitudes')
        ->assertSee('Estado de los depósitos')
        ->assertSee('Por revisar')
        ->assertSee('Aprobadas');
});

test('el curador y el administrador ven solo las actas constatadas pendientes en su cola', function (): void {
    $pendiente = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'DEP-PEND-1',
        'investigador_id' => (string) Str::uuid(),
        'tipo_tramite' => 'Depósito',
        'estado' => EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);
    $firmada = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'DEP-FIRM-1',
        'investigador_id' => (string) Str::uuid(),
        'tipo_tramite' => 'Depósito',
        'estado' => EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);

    foreach ([[$pendiente, null], [$firmada, 'actas/firmada.pdf']] as [$solicitud, $actaFirmadaRuta]) {
        RecepcionLoteEloquentModel::query()->create([
            'id' => (string) Str::uuid(),
            'solicitud_deposito_id' => $solicitud->id,
            'codigo_qr' => 'QR-'.$solicitud->numero,
            'tipo_tramite' => 'Depósito',
            'estado' => 'Verificado Físicamente',
            'items_verificacion' => [],
            'observaciones' => [],
            'verificado_en' => now(),
            'acta_firmada_ruta' => $actaFirmadaRuta,
        ]);
    }

    foreach ([User::factory()->curador()->create(), User::factory()->administrador()->create()] as $usuario) {
        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('DEP-PEND-1')
            ->assertSee('Acta pendiente de firma')
            ->assertDontSee('DEP-FIRM-1');
    }
});
