<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ExcelFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorPlantillaImportXlsx;

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/plantilla_*') ?: [] as $archivo) {
        @unlink($archivo);
    }
});

test('las columnas incluyen los campos clave que reconoce el importador', function (): void {
    $columnas = array_keys(GeneradorPlantillaImportXlsx::columnas());

    expect($columnas)->toContain('occurrenceID', 'scientificName', 'recordedBy', 'eventDate')
        ->toContain('country', 'stateProvince', 'municipality', 'localityName')
        ->toContain('decimalLatitude', 'decimalLongitude');
});

test('genera un .xlsx válido (firma ZIP)', function (): void {
    $bytes = GeneradorPlantillaImportXlsx::generar();

    expect($bytes)->not->toBe('')
        ->and(substr($bytes, 0, 2))->toBe('PK'); // firma de archivo ZIP/OOXML
});

test('la plantilla es leíble por el importador y sus headers coinciden', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'plantilla_').'.xlsx';
    file_put_contents($ruta, GeneradorPlantillaImportXlsx::generar());

    $fuente = new ExcelFuenteCatalogo($ruta);

    expect($fuente->headers())->toBe(array_keys(GeneradorPlantillaImportXlsx::columnas()));
});
