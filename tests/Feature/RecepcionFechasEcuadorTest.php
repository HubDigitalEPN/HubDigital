<?php

declare(strict_types=1);

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Documentos\DomPdfGeneradorActaRecepcionAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;

uses(Tests\DatabaseFeatureTestCase::class);

test('el seguimiento del acta existe antes de iniciar la recepción y no crea un lote', function (): void {
    $solicitud = solicitudColaR2ParaPruebas();
    $solicitud->update(['estado' => 'Aprobada Documentalmente', 'codigo_qr' => 'LOTE-ABC123']);

    $this->actingAs(User::factory()->curador()->create())
        ->get(route('prestamos.curador.deposito.acta', $solicitud->id))
        ->assertOk()
        ->assertSeeText('Pendiente de recepción física')
        ->assertDontSeeText('Firmador HubDigital');

    expect(RecepcionLoteEloquentModel::where('solicitud_deposito_id', $solicitud->id)->exists())->toBeFalse();
    $this->get(route('prestamos.curador.deposito.acta', (string) \Illuminate\Support\Str::uuid()))->assertNotFound();
});

test('una entrega de esta noche en Ecuador se guarda en UTC y conserva la hora al editar', function (): void {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-16 01:00:00', 'UTC'));
    try {
        $solicitud = solicitudColaR2ParaPruebas();
        $solicitud->update(['estado' => 'Aprobada Documentalmente', 'codigo_qr' => 'LOTE-ABC124']);
        $usuario = User::findOrFail($solicitud->investigador_id);

        Livewire::actingAs($usuario)->test(DetalleDeposito::class, ['id' => $solicitud->id])
            ->set('mostrarEntrega', true)
            ->assertSeeHtml('min="2026-10-15T20:00"')
            ->set('entregaProgramadaPara', '2026-10-15T21:30')
            ->call('anunciarEntrega')->assertHasNoErrors()
            ->assertSeeText('15/10/2026 21:30');

        expect($solicitud->fresh()->entrega_programada_para->utc()->format('Y-m-d H:i'))->toBe('2026-10-16 02:30');
        Livewire::actingAs($usuario)->test(DetalleDeposito::class, ['id' => $solicitud->id])
            ->assertSet('entregaProgramadaPara', '2026-10-15T21:30')
            ->set('entregaProgramadaPara', '2026-10-15T19:30')
            ->call('anunciarEntrega')->assertHasErrors(['entregaProgramadaPara']);
        expect($solicitud->fresh()->entrega_programada_para->utc()->format('Y-m-d H:i'))->toBe('2026-10-16 02:30');
    } finally {
        $this->travelBack();
    }
});

test('la fecha de identificación predeterminada corresponde al día de Ecuador', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 01:00:00', 'UTC'));
    try {
        [$solicitud, $usuario] = borradorWizardSinIdentidadParaPruebas();
        Livewire::actingAs($usuario)->test(RegistroSolicitudDeposito::class, ['id' => $solicitud->id])
            ->call('guardarPasoCuatro')->assertSet('paso', 7)
            ->assertSet('registroNativo.dateIdentified', '2026-10-15');
    } finally {
        $this->travelBack();
    }
});

test('el acta usa el día de su emisión en Ecuador sin alterar el instante guardado', function (): void {
    $solicitud = solicitudColaR2ParaPruebas();
    $curador = User::factory()->curador()->create();
    $recepcion = RecepcionLoteEloquentModel::create([
        'id' => (string) \Illuminate\Support\Str::uuid(), 'solicitud_deposito_id' => $solicitud->id,
        'codigo_qr' => 'LOTE-ABC125', 'tipo_tramite' => 'Depósito', 'estado' => 'Verificado Físicamente',
        'items_verificacion' => [], 'observaciones' => [],
        'acta_recepcion' => ['ruta' => 'actas/fecha.pdf', 'nombre' => 'Acta de prueba'],
        'recibido_por' => $curador->id, 'acta_generada_por' => $curador->id,
        'verificado_en' => '2026-10-16 01:00:00', 'acta_generada_en' => '2026-10-16 01:30:00',
    ]);
    $datos = app(ConsultarDetalleRecepcionHandler::class)->handle(new ConsultarDetalleRecepcionInput($solicitud->id));
    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('output')->once()->andReturn('contenido del acta');
    Pdf::shouldReceive('loadView')->once()->withArgs(
        fn (string $vista, array $datos): bool => $vista === 'gestionprestamosrecepciones::pdf.acta-recepcion'
            && $datos['fecha'] === '15 de Octubre de 2026',
    )->andReturn($pdf);

    expect(app(DomPdfGeneradorActaRecepcionAdapter::class)->generar($datos))->toBe('contenido del acta')
        ->and($recepcion->fresh()->acta_generada_en->utc()->format('Y-m-d H:i'))->toBe('2026-10-16 01:30');
});
