<?php

declare(strict_types=1);

use App\Support\ConfiguracionIpt;
use App\Support\PerfilIptGbif;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(Tests\InfrastructureTestCase::class);

test('el perfil inicial conserva exactamente las 69 cabeceras y el orden del ejemplo EPN', function (): void {
    $referencia = json_decode(file_get_contents(__DIR__.'/../Fixtures/ipt-epn-cabeceras.json'), true, 512, JSON_THROW_ON_ERROR);
    expect(PerfilIptGbif::COLUMNAS)->toHaveCount(69)->toBe($referencia)
        ->and(array_column(ConfiguracionIpt::predeterminada()['columnas'], 'campo'))->toBe($referencia);
});

test('IPT normaliza códigos sin reinterpretar disposición ni inventar metadatos institucionales', function (): void {
    $fila = PerfilIptGbif::fila(['occurrenceID' => '00012', 'countryCode' => 'Ec', 'occurrenceStatus' => 'Dañado', 'disposition' => 'in_collection']);
    expect($fila['id'])->toBe('00012')->and($fila['countryCode'])->toBe('EC')
        ->and($fila['occurrenceStatus'])->toBe('')->and($fila['disposition'])->toBe('in_collection')
        ->and($fila['license'])->toBe('')->and($fila['institutionID'])->toBe('');
});

test('las fechas parciales y los intervalos no adquieren precisión inexistente', function (): void {
    expect(PerfilIptGbif::fecha('2008-01-01', null, '2008'))->toBe('2008')
        ->and(PerfilIptGbif::fecha('2008-03-01', null, '2008-03'))->toBe('2008-03')
        ->and(PerfilIptGbif::fecha('2008-03-15', '2008-03-18', null))->toBe('2008-03-15/2008-03-18')
        ->and(PerfilIptGbif::fecha(null, null, 'ilegible'))->toBe('');
    expect(PerfilIptGbif::fechaIsoValida('2008-02-29'))->toBeTrue()
        ->and(PerfilIptGbif::fechaIsoValida('2007-02-29'))->toBeFalse()
        ->and(PerfilIptGbif::fechaIsoValida('2008-03/2008-04'))->toBeTrue()
        ->and(PerfilIptGbif::fechaIsoValida('2008-04/2008-03'))->toBeFalse();
});

test('el escritor IPT aplica orden ancho y formato configurados y mantiene las fórmulas como texto', function (): void {
    $columnas = [
        ['campo' => 'occurrenceID', 'formato' => 'texto', 'ancho' => 24],
        ['campo' => 'eventDate', 'formato' => 'fecha', 'ancho' => 18],
        ['campo' => 'individualCount', 'formato' => 'entero', 'ancho' => 10],
        ['campo' => 'recordedBy', 'formato' => 'texto', 'ancho' => 30],
    ];
    $revision = ['scientificName' => 1];
    $bytes = (new PhpSpreadsheetGeneradorXlsxAdapter)->generarIpt(array_column($columnas, 'campo'), [
        ['00012', '2008-03', 2, '=HYPERLINK("https://example.com")'],
        ['00013', '2007-02-29', 'dos', 'Colector'],
    ], $columnas, $revision);
    $ruta = tempnam(sys_get_temp_dir(), 'ipt-'); file_put_contents($ruta, $bytes);
    $libro = null;
    try {
        $libro = IOFactory::load($ruta); $hoja = $libro->getSheet(0);
        expect($hoja->getTitle())->toBe('occurrence')
            ->and((string)$hoja->getCell('A2')->getValue())->toBe('00012')
            ->and($hoja->getCell('A2')->getDataType())->toBe(DataType::TYPE_INLINE)
            ->and((string)$hoja->getCell('B2')->getValue())->toBe('2008-03')
            ->and($hoja->getCell('C2')->getValue())->toBe(2)
            ->and($hoja->getCell('D2')->getDataType())->toBe(DataType::TYPE_INLINE)
            ->and((string)$hoja->getCell('D2')->getValue())->toBe('=HYPERLINK("https://example.com")')
            ->and((string)$hoja->getCell('B3')->getValue())->toBe('')
            ->and((string)$hoja->getCell('C3')->getValue())->toBe('')
            ->and($hoja->getColumnDimension('A')->getWidth())->toBe(24.0)
            ->and($hoja->getStyle('A1')->getFill()->getFillType())->toBe('none')
            ->and($libro->getSheetByName('Revisión'))->not->toBeNull();
        expect($revision['formato:eventDate'])->toBe(1)->and($revision['formato:individualCount'])->toBe(1);
    } finally { $libro?->disconnectWorksheets(); unlink($ruta); }
});
