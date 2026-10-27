<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\PortalCopy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Documentos\DomPdfGeneradorActaRecepcionAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\BandejaDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\RevisarDeposito;

uses(Tests\DatabaseFeatureTestCase::class);

test('solo el acta final firmada cierra el trámite y sustituye las acciones en escritorio y móvil', function (): void {
    configurarR2FalsoParaPruebas();
    $solicitud = solicitudColaR2ParaPruebas();
    $solicitud->forceFill(['estado' => 'Aprobada Documentalmente', 'codigo_qr' => 'LOTE-CIERRE'])->save();
    $curador = User::factory()->curador()->create();
    $recepcion = RecepcionLoteEloquentModel::create([
        'id' => (string) Str::uuid(), 'solicitud_deposito_id' => $solicitud->id,
        'tipo_tramite' => 'Depósito', 'codigo_qr' => 'LOTE-CIERRE', 'estado' => 'Verificado Físicamente',
        'items_verificacion' => [], 'observaciones' => [], 'verificado_en' => now(),
        'acta_recepcion' => ['nombre' => 'acta.pdf', 'ruta' => 'actas/cierre-original.pdf'],
    ]);
    $bandeja = Livewire::actingAs($curador)->test(BandejaDepositos::class)->call('cambiarVista', 'resueltas');
    $bandeja->assertDontSee('Cerrado')->assertSee('Generar y firmar');
    $ruta = 'actas/qa/'.$solicitud->id.'/firmada.pdf';
    $contenido = '%PDF-fixture descarga del acta ya firmada';
    app(AlmacenamientoDepositos::class)->guardarContenido($ruta, $contenido, 'application/pdf');
    $recepcion->forceFill(['acta_firmada_ruta' => $ruta, 'firmada_en' => now()])->save();
    $bandeja->call('$refresh')->assertSee('Cerrado')->assertSee('Ver detalles')->assertSee('Acta firmada')->assertDontSee('Generar y firmar');
    $detalle = Livewire::actingAs($curador)->test(RevisarDeposito::class, ['id' => $solicitud->id])
        ->assertSee('Cerrado')->assertSee('Acta firmada')
        ->assertDontSee('Seguimiento de recepción y acta final')->assertDontSee('Registrar devolución al depositante');
    $detalle->call('registrarDevolucion')->assertStatus(409);
    expect($solicitud->fresh()->estado)->toBe('Aprobada Documentalmente');
    $this->actingAs($curador)->get(route('prestamos.deposito.acta-recepcion', ['id' => $solicitud->id, 'descargar' => 1]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="Acta-recepcion-'.$solicitud->numero.'.pdf"')
        ->assertContent($contenido);
    $otro = User::factory()->depositante()->create();
    $this->actingAs($otro)->get(route('prestamos.deposito.acta-recepcion', ['id' => $solicitud->id, 'descargar' => 1]))->assertForbidden();
});

test('los dos bloques del portal de depósitos se editan desde el panel del curador', function (): void {
    $curador = User::factory()->curador()->create();
    Livewire::actingAs($curador)->test(\App\Livewire\Administracion\ConfiguracionTextosPortal::class, ['grupo' => 'depositos'])
        ->set('textos.depositos.preparar_titulo', 'Documentación para entregar')
        ->set('textos.depositos.requisito_solicitud', 'Solicitud institucional actualizada')
        ->set('textos.depositos.traslado_titulo', 'Indicaciones previas al traslado')
        ->set('textos.depositos.instrucciones', 'Coordina previamente la entrega')
        ->call('guardar')->assertHasNoErrors();
    $this->actingAs($curador)->get(route('depositos.portal'))->assertOk()
        ->assertSee('Editar textos y requisitos de depósitos')
        ->assertSee('Documentación para entregar')->assertSee('Solicitud institucional actualizada')
        ->assertSee('Indicaciones previas al traslado')->assertSee('Coordina previamente la entrega');
    \App\Support\PortalCopy::forget();
});

test('las nuevas actas usan el responsable designado o su reemplazo sin alterar al operador ni la identidad de firma', function (bool $reemplazo): void {
    PortalCopy::forget();
    $solicitud = solicitudColaR2ParaPruebas();
    $curador = User::factory()->curador()->create(['first_name' => 'Adrian Esteban', 'last_name' => 'Troya Proaño']);
    $responsable = 'Vladimir Carvajal';
    if ($reemplazo) {
        $responsable = 'Responsable de reemplazo QA';
        Livewire::actingAs($curador)->test(\App\Livewire\Administracion\ConfiguracionTextosPortal::class, ['grupo' => 'depositos'])
            ->set('textos.depositos.recepcion_responsable', $responsable)
            ->call('guardar')->assertHasNoErrors();
    }
    $recepcion = RecepcionLoteEloquentModel::create([
        'id' => (string) Str::uuid(), 'solicitud_deposito_id' => $solicitud->id,
        'codigo_qr' => 'LOTE-RES001', 'tipo_tramite' => 'Depósito', 'estado' => 'Verificado Físicamente',
        'items_verificacion' => [], 'observaciones' => [], 'verificado_en' => now(),
        'recibido_por' => $curador->id, 'acta_generada_por' => $curador->id, 'acta_generada_en' => now(),
    ]);
    $datos = app(ConsultarDetalleRecepcionHandler::class)->handle(new ConsultarDetalleRecepcionInput($solicitud->id));
    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('output')->once()->andReturn('acta nueva');
    Pdf::shouldReceive('loadView')->once()->withArgs(function (string $vista, array $datos) use ($responsable, $curador): bool {
        expect($vista)->toBe('gestionprestamosrecepciones::pdf.acta-recepcion')
            ->and($datos['receptor'])->toBe($responsable)
            ->and($datos['nombreFirma'])->toBe('Dr. Adrian Troya')
            ->and($datos['curador'])->toBe($curador->name);
        return true;
    })->andReturn($pdf);
    try {
        expect(app(DomPdfGeneradorActaRecepcionAdapter::class)->generar($datos))->toBe('acta nueva')
            ->and($recepcion->fresh()->recibido_por)->toBe($curador->id)
            ->and($recepcion->fresh()->acta_generada_por)->toBe($curador->id);
    } finally {
        PortalCopy::forget();
    }
})->with(['responsable confirmado' => false, 'reemplazo configurable' => true]);
