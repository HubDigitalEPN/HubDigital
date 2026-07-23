<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ExcelFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Escribe un .xlsx temporal con las filas dadas (la primera es la cabecera) y
 * devuelve su ruta. Se limpia en afterEach.
 *
 * @param  array<int, array<int, string>>  $filas
 */
function escribirXlsxTemporal(array $filas): string
{
    $spreadsheet = new Spreadsheet;
    $hoja = $spreadsheet->getActiveSheet();
    $hoja->fromArray($filas, null, 'A1');

    $ruta = tempnam(sys_get_temp_dir(), 'excel_fuente_').'.xlsx';
    (new Xlsx($spreadsheet))->save($ruta);
    $spreadsheet->disconnectWorksheets();

    return $ruta;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/excel_fuente_*') ?: [] as $archivo) {
        @unlink($archivo);
    }
});

test('lee los headers de la primera fila del Excel', function (): void {
    $ruta = escribirXlsxTemporal([
        ['occurrenceID', 'scientificName', 'recordedBy'],
        ['MEPN:INV:1', 'Morpho peleides', 'Juan'],
    ]);

    $fuente = new ExcelFuenteCatalogo($ruta);

    expect($fuente->headers())->toBe(['occurrenceID', 'scientificName', 'recordedBy']);
});

test('itera cada fila como array asociativo header => valor, indexado desde 1', function (): void {
    $ruta = escribirXlsxTemporal([
        ['occurrenceID', 'scientificName', 'recordedBy'],
        ['MEPN:INV:1', 'Morpho peleides', 'Juan'],
        ['MEPN:INV:2', 'Morpho menelaus', 'Maria'],
    ]);

    $fuente = new ExcelFuenteCatalogo($ruta);

    $filas = iterator_to_array($fuente->iterar());

    expect($filas)->toHaveCount(2)
        ->and($filas[1])->toBe([
            'occurrenceID' => 'MEPN:INV:1',
            'scientificName' => 'Morpho peleides',
            'recordedBy' => 'Juan',
        ])
        ->and($filas[2]['occurrenceID'])->toBe('MEPN:INV:2');
});

test('ignora filas totalmente vacías', function (): void {
    $ruta = escribirXlsxTemporal([
        ['occurrenceID', 'recordedBy'],
        ['A', 'Juan'],
        ['', ''],
        ['B', 'Maria'],
    ]);

    $fuente = new ExcelFuenteCatalogo($ruta);

    $filas = iterator_to_array($fuente->iterar());

    expect($filas)->toHaveCount(2)
        ->and(array_column($filas, 'occurrenceID'))->toBe(['A', 'B']);
});

test('lanza si el archivo no existe', function (): void {
    new ExcelFuenteCatalogo('/ruta/inexistente/catalogo.xlsx');
})->throws(InvalidArgumentException::class);

test('el importador persiste especímenes leyendo directo desde el Excel (end-to-end)', function (): void {
    $ruta = escribirXlsxTemporal([
        ['occurrenceID', 'recordedBy', 'scientificName', 'oldCode'],
        ['MEPN:INV:1', 'Juan', 'Morpho peleides', 'BT2F3'],
        ['MEPN:INV:2', 'Maria', 'Morpho menelaus', 'BT2F3'],
    ]);

    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $importer = new ImportarCatalogoInvertebrados(
        $especimenRepo,
        $muestraRepo,
        new InMemoryTaxonRepository,
        new FilaCatalogoMapper,
    );

    $resultado = $importer->ejecutar(new ExcelFuenteCatalogo($ruta));

    expect($resultado->filasLeidas)->toBe(2)
        ->and($resultado->especimenesPersistidos)->toBe(2)
        ->and($resultado->muestrasCreadas)->toBe(1) // mismo oldCode → una muestra
        ->and($especimenRepo->buscarPorCodigoCatalogo('MEPN:INV:1'))->not->toBeNull();
});
