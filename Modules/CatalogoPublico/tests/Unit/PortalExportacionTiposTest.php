<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\ValueObjects\NumeroExportacion;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('QA5 XLSX usa números y fechas nativos preservando códigos texto nulos y fórmulas literales', function (): void {
    $encabezados = ['occurrenceID', 'codigoINEC', 'decimalLatitude', 'decimalLongitude', 'individualCount',
        'minimumElevationInMeters', 'maximumElevationInMeters', 'eventDate', 'recordedBy'];
    $filas = [
        ['000123', '010101', '-0.1234567', '78.9876543', '0', '-25.5', '3200.75', '2025-01-10', '=1+1'],
        ['000124', '010102', null, null, null, null, null, '1700-01-01', '@SUM(A1)'],
        ['000125', '010103', '-91', '=1+1', '-1', 'INF', '1e309', '2025-02-30', '+1+1'],
    ];
    $contenido = (new PhpSpreadsheetGeneradorXlsxAdapter)->generar($encabezados, $filas);
    $archivo = tempnam(sys_get_temp_dir(), 'pest-qa5-tipos-');
    $libro = null; $zip = new ZipArchive();
    try {
        file_put_contents($archivo, $contenido);
        expect($zip->open($archivo))->toBeTrue();
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $celda = static fn (string $referencia) => $xml->xpath('//s:c[@r="'.$referencia.'"]')[0];
        expect((string) $celda('C2')['t'])->toBe('n')->and((string) $celda('D2')['t'])->toBe('n')
            ->and((string) $celda('C2')->v)->toBe('-0.1234567')->and((string) $celda('D2')->v)->toBe('78.9876543')
            ->and($xml->xpath('//s:f'))->toBe([]);
        $libro = IOFactory::load($archivo);
        $hoja = $libro->getActiveSheet();
        // Al leer estilos, PhpSpreadsheet representa inlineStr como RichText.
        // Comparar su texto conserva códigos y literales, junto al tipo original.
        expect((string) $hoja->getCell('A2')->getValue())->toBe('000123')->and($hoja->getCell('A2')->getDataType())->toBe('inlineStr')
            ->and((string) $hoja->getCell('B2')->getValue())->toBe('010101')->and($hoja->getCell('B2')->getDataType())->toBe('inlineStr')
            ->and($hoja->getCell('E2')->getValue())->toBe(0)->and($hoja->getCell('E2')->getDataType())->toBe('n')
            ->and($hoja->getCell('F2')->getValue())->toBe(-25.5)->and($hoja->getCell('G2')->getValue())->toBe(3200.75)
            ->and($hoja->getCell('H2')->getDataType())->toBe('n')->and($hoja->getCell('H2')->getFormattedValue())->toBe('2025-01-10')
            ->and((string) $hoja->getCell('H3')->getValue())->toBe('1700-01-01')->and($hoja->getCell('H3')->getDataType())->toBe('inlineStr')
            ->and((string) $hoja->getCell('I2')->getValue())->toBe('=1+1')->and($hoja->getCell('I2')->getDataType())->toBe('inlineStr')
            ->and((string) $hoja->getCell('I3')->getValue())->toBe('@SUM(A1)')->and((string) $hoja->getCell('C3')->getValue())->toBe('')
            ->and($hoja->getCell('C3')->getDataType())->toBe('inlineStr');
        foreach (['C4', 'D4', 'E4', 'F4', 'G4', 'H4', 'I4'] as $referencia) expect($hoja->getCell($referencia)->getDataType())->toBe('inlineStr');
    } finally {
        if ($libro !== null) $libro->disconnectWorksheets();
        unlink($archivo);
    }
});

test('QA5 el contrato numérico conserva precisión y rechaza expresiones no finitas o fuera de rango', function (): void {
    expect(NumeroExportacion::decimal('-0.123456700', -90, 90))->toBe('-0.123456700')
        ->and(NumeroExportacion::decimal('+1.25', -90, 90))->toBe('1.25')
        ->and(NumeroExportacion::decimal(0, -90, 90))->toBe('0')
        ->and(NumeroExportacion::decimal('0', 0, null, true))->toBe('0');
    foreach ([null, '', '=1+1', '-1+2', "\t=1", 'NaN', 'INF', '1e309', '-90.1', '90.1'] as $valor)
        expect(NumeroExportacion::decimal($valor, -90, 90))->toBeNull();
    expect(NumeroExportacion::decimal('-1', 0, null, true))->toBeNull()
        ->and(NumeroExportacion::decimal('1.5', 0, null, true))->toBeNull();
});
