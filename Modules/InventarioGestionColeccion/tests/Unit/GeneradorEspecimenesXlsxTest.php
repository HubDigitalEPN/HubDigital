<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ExcelFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorEspecimenesXlsx;

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/esp_export_*') ?: [] as $archivo) {
        @unlink($archivo);
    }
});

test('genera un .xlsx válido y round-trip: encabezados y datos leíbles', function (): void {
    $columnas = ['occurrenceID', 'scientificName', 'country'];
    $filas = [
        ['occurrenceID' => 'MEPN:INV:1', 'scientificName' => 'Morpho peleides', 'country' => 'Ecuador'],
        ['occurrenceID' => 'MEPN:INV:2', 'scientificName' => 'Morpho menelaus', 'country' => 'Ecuador'],
    ];

    $bytes = GeneradorEspecimenesXlsx::generar($columnas, $filas);
    expect(substr($bytes, 0, 2))->toBe('PK'); // firma OOXML/ZIP

    $ruta = tempnam(sys_get_temp_dir(), 'esp_export_').'.xlsx';
    file_put_contents($ruta, $bytes);

    $fuente = new ExcelFuenteCatalogo($ruta);
    expect($fuente->headers())->toBe($columnas);

    $leidas = iterator_to_array($fuente->iterar());
    expect($leidas)->toHaveCount(2)
        ->and($leidas[1]['scientificName'])->toBe('Morpho peleides')
        ->and($leidas[2]['country'])->toBe('Ecuador');
});

test('genera un .xlsx solo con encabezados cuando no hay filas', function (): void {
    $bytes = GeneradorEspecimenesXlsx::generar(['occurrenceID', 'scientificName'], []);

    expect(substr($bytes, 0, 2))->toBe('PK');
});
