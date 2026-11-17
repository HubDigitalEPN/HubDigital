<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter;
use setasign\Fpdi\Fpdi;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('el diagnóstico con plazo explícito conserva el dictamen del mismo motor y el contrato normal', function (): void {
    config(['firma-electronica.worker_enabled' => false]);
    $ruta = tempnam(sys_get_temp_dir(), 'hd-qa-plazo-');
    try {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'PDF sintetico sin firma');
        file_put_contents($ruta, $pdf->Output('S'));
        $adaptador = app(JavaValidacionFirmaElectronicaAdapter::class);
        expect($adaptador->verificarDocumentoExterno($ruta, 60)['status'])->toBe('sin_firma')
            ->and($adaptador->verificarFirma($ruta))->toBe(ResultadoValidacionFirma::SinFirma);
    } finally {
        if (is_string($ruta)) {
            @unlink($ruta);
        }
    }
});

test('el diagnóstico rechaza plazos no válidos antes de lanzar un proceso', function (float $plazo): void {
    expect(fn () => app(JavaValidacionFirmaElectronicaAdapter::class)->verificarDocumentoExterno('no-se-abre.pdf', $plazo))
        ->toThrow(InvalidArgumentException::class, 'positivo y finito');
})->with([0.0, -1.0, INF, NAN]);
