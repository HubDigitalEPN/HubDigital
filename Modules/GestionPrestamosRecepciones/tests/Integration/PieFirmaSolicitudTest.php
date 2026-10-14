<?php

declare(strict_types=1);

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

function solicitudParaPieFirma(User $depositante): SolicitudDepositoEloquentModel
{
    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(), 'numero' => 'HUB-DEP-'.random_int(800000, 899999),
        'investigador_id' => $depositante->id, 'tipo_tramite' => 'Depósito', 'estado' => 'En Borrador',
        'documentos_adjuntos' => [], 'documentos_cargados' => [], 'solicitud_documento_version' => 1,
        'solicitud_nombre_permiso' => 'HERNAN AUGUSTO TROYA PROANO', 'solicitud_tipo_identificacion' => 'cedula',
        'solicitud_cedula' => '1707514251', 'solicitud_cargo' => 'Coordinador técnico',
        'solicitud_grupo' => 'Macroinvertebrados acuáticos', 'solicitud_proyecto' => 'Proyecto de prueba',
        'solicitud_institucion' => 'Escuela Politécnica Nacional',
        'solicitud_correo' => $depositante->email, 'solicitud_oficio' => 'QA-PIE-FIRMA',
    ]);
}

test('el pie identifica la institución del depositante y no la del proyecto ni la sesión revisora', function (?string $institucion): void {
    $depositante = User::factory()->depositante()->create(['institucion' => $institucion]);
    $this->actingAs(User::factory()->create(['institucion' => 'Institución de otra cuenta']));
    $solicitud = solicitudParaPieFirma($depositante);
    $solicitud->refresh();
    $antes = $solicitud->getAttributes();
    $html = '';
    $renderizador = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $renderizador->shouldReceive('loadView')->once()->andReturnUsing(function (string $vista, array $datos) use (&$html, $renderizador) {
        $html = view($vista, $datos)->render();
        return $renderizador;
    });
    $renderizador->shouldReceive('addInfo')->once()->andReturnSelf();
    $renderizador->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
    $renderizador->shouldReceive('output')->once()->andReturn('%PDF-fixture-del-renderizador');
    Pdf::swap($renderizador);

    app(GeneradorPdfSolicitudDeposito::class)->generar($solicitud);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $pie = (new DOMXPath($dom))->query('//div[@class="firmante"]')->item(0)->textContent;
    expect($pie)->toContain('Hernan Augusto Troya Proano', $depositante->email)
        ->not->toContain('Escuela Politécnica Nacional', 'Institución de otra cuenta')
        ->and($html)->toContain('en mi calidad de Coordinador Técnico, solicito', 'Escuela Politécnica Nacional')
        ->and($solicitud->fresh()->getAttributes())->toBe($antes);
    if ($institucion !== null) expect($pie)->toContain($institucion);
})->with(['institución del perfil' => ['Kintiflow'], 'perfil sin institución' => [null]]);

test('actualizar la institución del perfil renueva el PDF sin firma almacenado en caché', function (): void {
    $depositante = User::factory()->depositante()->create(['institucion' => 'Empresa inicial']);
    $solicitud = solicitudParaPieFirma($depositante);
    $instituciones = [];
    $renderizador = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $renderizador->shouldReceive('loadView')->twice()->andReturnUsing(function (string $vista, array $datos) use (&$instituciones, $renderizador) {
        $instituciones[] = $datos['institucionFirmante'];
        return $renderizador;
    });
    $renderizador->shouldReceive('addInfo')->twice()->andReturnSelf();
    $renderizador->shouldReceive('setPaper')->twice()->with('a4')->andReturnSelf();
    $renderizador->shouldReceive('output')->twice()->andReturn('%PDF-empresa-inicial', '%PDF-empresa-actualizada');
    Pdf::swap($renderizador);

    $generador = app(GeneradorPdfSolicitudDeposito::class);
    expect($generador->generar($solicitud))->toBe('%PDF-empresa-inicial');
    $depositante->update(['institucion' => 'Empresa actualizada']);
    expect($generador->generar($solicitud))->toBe('%PDF-empresa-actualizada')
        ->and($instituciones)->toBe(['Empresa inicial', 'Empresa actualizada'])
        ->and(Cache::get('oficio-pdf-indice:'.$solicitud->id))->toHaveCount(2)
        ->and($solicitud->fresh()->solicitud_institucion)->toBe('Escuela Politécnica Nacional');
});
