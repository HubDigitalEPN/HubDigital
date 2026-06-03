<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;

/**
 * Fuente en-memoria que recibe un array de filas (sin necesidad de archivo).
 */
class ArrayFuenteCatalogo implements FuenteCatalogoIterator
{
    public function __construct(private array $filas, private array $headers = []) {}

    public function iterar(): Generator
    {
        $n = 0;
        foreach ($this->filas as $fila) {
            $n++;
            yield $n => $fila;
        }
    }

    public function headers(): array
    {
        return $this->headers;
    }
}

function bootstrapImporter(): array
{
    $especimenRepo = new InMemoryEspecimenRepository;
    $muestraRepo = new InMemoryMuestraColectaRepository;
    $importer = new ImportarCatalogoInvertebrados($especimenRepo, $muestraRepo, new FilaCatalogoMapper);

    return [$importer, $especimenRepo, $muestraRepo];
}

test('persiste un espécimen mínimo (camino feliz)', function (): void {
    [$importer, $especimenRepo, $muestraRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => 'MEPN:INV:1', 'recordedBy' => 'Juan', 'scientificName' => 'Morpho peleides'],
    ]);

    $resultado = $importer->ejecutar($fuente);

    expect($resultado->filasLeidas)->toBe(1)
        ->and($resultado->especimenesPersistidos)->toBe(1)
        ->and($resultado->marcadosParaRevision)->toBe(0)
        ->and($especimenRepo->buscarPorCodigoCatalogo('MEPN:INV:1'))->not->toBeNull();
});

test('agrupa filas con mismo oldCode en una sola muestra', function (): void {
    [$importer, , $muestraRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => '1', 'oldCode' => 'BT2F3', 'recordedBy' => 'Juan'],
        ['occurrenceID' => '2', 'oldCode' => 'BT2F3', 'recordedBy' => 'Juan'],
        ['occurrenceID' => '3', 'oldCode' => 'BT2F3', 'recordedBy' => 'Juan'],
        ['occurrenceID' => '4', 'oldCode' => 'LT2F2', 'recordedBy' => 'Maria'],
    ]);

    $resultado = $importer->ejecutar($fuente);

    expect($resultado->especimenesPersistidos)->toBe(4)
        ->and($resultado->muestrasCreadas)->toBe(2)
        ->and($muestraRepo->contarTodas())->toBe(2);
});

test('marca para revisión las filas con warnings y agrega motivos', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['oldCode' => 'X'], // occurrence_id ausente
        ['occurrenceID' => 'Y', 'invidualCount' => 'NO-NUM'],
        ['occurrenceID' => 'Z', 'decimalLatitude' => 'dañada', 'decimalLongitude' => 'dañada'],
        ['occurrenceID' => 'OK', 'recordedBy' => 'Limpio'],
    ]);

    $resultado = $importer->ejecutar($fuente);

    expect($resultado->especimenesPersistidos)->toBe(4)
        ->and($resultado->marcadosParaRevision)->toBe(3)
        ->and(array_keys($resultado->motivosRevision))->toContain('occurrence_id ausente');

    // El espécimen sin issues sigue en estado pendiente por defecto pero sin motivo.
    $limpio = $especimenRepo->buscarPorCodigoCatalogo('OK');
    expect($limpio->motivoRevision())->toBeNull();
});

test('dry-run no persiste y reporta los conteos correctos', function (): void {
    [$importer, $especimenRepo, $muestraRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => '1', 'oldCode' => 'BT2F3', 'recordedBy' => 'Juan'],
        ['occurrenceID' => '2', 'oldCode' => 'BT2F3', 'recordedBy' => 'Juan'],
    ]);

    $resultado = $importer->ejecutar($fuente, dryRun: true);

    expect($resultado->dryRun)->toBeTrue()
        ->and($resultado->especimenesPersistidos)->toBe(2)
        ->and($resultado->muestrasCreadas)->toBe(1)
        ->and($especimenRepo->buscarTodos())->toHaveCount(0)
        ->and($muestraRepo->contarTodas())->toBe(0);
});

test('respeta el rango --from / --to', function (): void {
    [$importer] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => 'A'],
        ['occurrenceID' => 'B'],
        ['occurrenceID' => 'C'],
        ['occurrenceID' => 'D'],
        ['occurrenceID' => 'E'],
    ]);

    $resultado = $importer->ejecutar($fuente, desde: 2, hasta: 4);

    expect($resultado->filasLeidas)->toBe(3)
        ->and($resultado->especimenesPersistidos)->toBe(3);
});

test('persistencia: el espécimen lleva el muestra_id correcto', function (): void {
    [$importer, $especimenRepo, $muestraRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => '1', 'oldCode' => 'BT2F3'],
        ['occurrenceID' => '2', 'oldCode' => 'BT2F3'],
    ]);

    $importer->ejecutar($fuente);

    $muestras = $muestraRepo->buscarTodas();
    expect($muestras)->toHaveCount(1);
    $muestraId = (string) $muestras[0]->id();

    foreach ($especimenRepo->buscarTodos() as $e) {
        expect($e->muestraId())->toBe($muestraId);
    }
});

test('reporte agrega motivos ordenados por frecuencia descendente', function (): void {
    [$importer] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['oldCode' => 'X1'],
        ['oldCode' => 'X2'],
        ['oldCode' => 'X3'],
        ['occurrenceID' => 'Y', 'invidualCount' => 'BAD'],
    ]);

    $resultado = $importer->ejecutar($fuente);
    $motivos = array_keys($resultado->motivosRevision);

    // Top motivo debe ser 'occurrence_id ausente' con 3 ocurrencias
    expect($motivos[0])->toBe('occurrence_id ausente')
        ->and($resultado->motivosRevision['occurrence_id ausente'])->toBe(3);
});
