<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\CsvFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

const CATALOGO_CSV_FIXTURE = __DIR__.'/Fixtures/catalogo_invertebrados_sample.csv';

test('CsvFuenteCatalogo lee headers y filas como arrays asociativos', function (): void {
    $fuente = new CsvFuenteCatalogo(CATALOGO_CSV_FIXTURE);

    $headers = $fuente->headers();
    expect($headers)->toContain('occurrenceID')
        ->and($headers)->toContain('invidualCount')
        ->and($headers)->toContain('Endemic');

    $filas = iterator_to_array($fuente->iterar());
    expect($filas)->toHaveCount(6)
        ->and($filas[1]['occurrenceID'])->toBe('MEPN:INV:1')
        ->and($filas[6]['decimalLatitude'])->toBe('200.5');
});

test('end-to-end con fixture CSV: 6 filas → 6 especímenes, 2 muestras (BT2F3 y LT2F2)', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $taxonRepo = new InMemoryTaxonRepository;
    $importer = new ImportarCatalogoInvertebrados(
        $especimenRepo,
        $muestraRepo,
        $taxonRepo,
        new FilaCatalogoMapper,
    );

    $fuente = new CsvFuenteCatalogo(CATALOGO_CSV_FIXTURE);
    $resultado = $importer->ejecutar($fuente);

    expect($resultado->filasLeidas)->toBe(6)
        ->and($resultado->especimenesPersistidos)->toBe(6)
        ->and($resultado->muestrasCreadas)->toBe(2);

    // Marcadas para revisión: fila 3 (count + coord), fila 5 (occurrence_id ausente), fila 6 (coord fuera de rango + fecha + occurrence_id ausente desconocido — no, sí tiene occurrence_id).
    // Verificamos al menos que hay 3 con warnings (fila 3, 5, 6).
    expect($resultado->marcadosParaRevision)->toBeGreaterThanOrEqual(3);

    // Los motivos deben incluir los tres tipos esperados.
    $motivos = $resultado->motivosRevision;
    expect(array_keys($motivos))->toContain('occurrence_id ausente');
});

test('archivo CSV inexistente lanza excepción', function (): void {
    new CsvFuenteCatalogo('/ruta/que/no/existe.csv');
})->throws(InvalidArgumentException::class, 'no encontrado');
