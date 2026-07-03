<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryMuestraColectaRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

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
    $taxonRepo = new InMemoryTaxonRepository;
    $localidadRepo = new InMemoryLocalidadRepository;
    $importer = new ImportarCatalogoInvertebrados(
        $especimenRepo,
        $muestraRepo,
        $taxonRepo,
        $localidadRepo,
        new FilaCatalogoMapper,
    );

    return [$importer, $especimenRepo, $muestraRepo, $taxonRepo, $localidadRepo];
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

test('persiste la fila_origen_excel para idempotencia', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => 'A', 'recordedBy' => 'Juan'],
        ['occurrenceID' => 'B', 'recordedBy' => 'Maria'],
    ]);

    $importer->ejecutar($fuente);

    $a = $especimenRepo->buscarPorCodigoCatalogo('A');
    $b = $especimenRepo->buscarPorCodigoCatalogo('B');
    expect($a->filaOrigenExcel())->toBe(1)
        ->and($b->filaOrigenExcel())->toBe(2);
});

test('idempotencia: segunda corrida no duplica especímenes ya importados', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => 'A', 'recordedBy' => 'Juan'],
        ['occurrenceID' => 'B', 'recordedBy' => 'Maria'],
    ]);

    $primera = $importer->ejecutar($fuente);
    expect($primera->especimenesPersistidos)->toBe(2)
        ->and($primera->duplicadosSaltados)->toBe(0);

    $segunda = $importer->ejecutar($fuente);
    expect($segunda->especimenesPersistidos)->toBe(0)
        ->and($segunda->duplicadosSaltados)->toBe(2)
        ->and($especimenRepo->buscarTodos())->toHaveCount(2);
});

test('construye la jerarquía taxonómica encadenada desde kingdom hasta especie', function (): void {
    [$importer, $especimenRepo, , $taxonRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'MEPN:INV:1',
        'recordedBy' => 'Juan',
        'kingdom' => 'Animalia',
        'phylum' => 'Arthropoda',
        'class' => 'Insecta',
        'order' => 'Lepidoptera',
        'family' => 'Nymphalidae',
        'genus' => 'Morpho',
        'specificEpithet' => 'peleides',
        'taxonRank' => 'species',
    ]]);

    $importer->ejecutar($fuente);

    $taxones = $taxonRepo->buscarTodos();
    $nombres = array_map(fn ($t) => $t->nombreCientifico(), $taxones);

    expect($nombres)->toContain('Animalia', 'Arthropoda', 'Insecta', 'Lepidoptera', 'Nymphalidae', 'Morpho', 'Morpho peleides');

    $especimen = $especimenRepo->buscarPorCodigoCatalogo('MEPN:INV:1');
    expect($especimen->taxonId())->not->toBeNull();
});

test('reutiliza taxones entre filas (no duplica género/familia entre especímenes)', function (): void {
    [$importer, , , $taxonRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        [
            'occurrenceID' => '1', 'recordedBy' => 'X',
            'kingdom' => 'Animalia', 'phylum' => 'Arthropoda',
            'class' => 'Insecta', 'order' => 'Lepidoptera',
            'family' => 'Nymphalidae', 'genus' => 'Morpho',
            'specificEpithet' => 'peleides', 'taxonRank' => 'species',
        ],
        [
            'occurrenceID' => '2', 'recordedBy' => 'Y',
            'kingdom' => 'Animalia', 'phylum' => 'Arthropoda',
            'class' => 'Insecta', 'order' => 'Lepidoptera',
            'family' => 'Nymphalidae', 'genus' => 'Morpho',
            'specificEpithet' => 'menelaus', 'taxonRank' => 'species',
        ],
    ]);

    $importer->ejecutar($fuente);

    $taxones = $taxonRepo->buscarTodos();
    // Esperamos: Animalia + Arthropoda + Insecta + Lepidoptera + Nymphalidae + Morpho +
    // Morpho peleides + Morpho menelaus = 8 taxones (no 14).
    expect($taxones)->toHaveCount(8);
});

test('rango de fechas en una sola celda: dd-dd/mes/yyyy', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'R1',
        'recordedBy' => 'Juan',
        'eventDate' => '14-26/feb/2001',
    ]]);

    $importer->ejecutar($fuente);

    $e = $especimenRepo->buscarPorCodigoCatalogo('R1');
    expect($e->fechaColecta())->toBe('2001-02-14')
        ->and($e->fechaColectaFin())->toBe('2001-02-26');
});

test('rango de fechas mes-mes: Jun-Jul-1998', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'R2',
        'recordedBy' => 'Juan',
        'eventDate' => 'Jun-Jul-1998',
    ]]);

    $importer->ejecutar($fuente);

    $e = $especimenRepo->buscarPorCodigoCatalogo('R2');
    expect($e->fechaColecta())->toBe('1998-06-01')
        ->and($e->fechaColectaFin())->toBe('1998-07-31');
});

test('rango de fechas dd-mes/dd-mes/yyyy', function (): void {
    [$importer, $especimenRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'R3',
        'recordedBy' => 'Juan',
        'eventDate' => '30-jun/02-jul/2005',
    ]]);

    $importer->ejecutar($fuente);

    $e = $especimenRepo->buscarPorCodigoCatalogo('R3');
    expect($e->fechaColecta())->toBe('2005-06-30')
        ->and($e->fechaColectaFin())->toBe('2005-07-02');
});

test('localidad separada por comas crea la jerarquía y enlaza el espécimen a la hoja', function (): void {
    [$importer, $especimenRepo, , , $localidadRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'L1',
        'recordedBy' => 'Juan',
        'verbatimLocality' => 'ECUADOR, ORELLANA, YASUNÍ',
    ]]);

    $importer->ejecutar($fuente);

    // Se crearon tres niveles: País, Provincia, Sitio.
    $localidades = $localidadRepo->buscarTodos();
    $porNombre = [];
    foreach ($localidades as $l) {
        $porNombre[$l->nombreCanonico()] = $l;
    }
    expect($porNombre)->toHaveKeys(['Ecuador', 'Orellana', 'Yasuní']);

    // La jerarquía encadena por padre: Yasuní → Orellana → Ecuador.
    expect((string) $porNombre['Yasuní']->padreId())->toBe((string) $porNombre['Orellana']->id())
        ->and((string) $porNombre['Orellana']->padreId())->toBe((string) $porNombre['Ecuador']->id())
        ->and($porNombre['Ecuador']->padreId())->toBeNull();

    // El espécimen queda enlazado a la hoja (la más específica: Yasuní).
    $e = $especimenRepo->buscarPorCodigoCatalogo('L1');
    expect($e->localidadId())->toBe((string) $porNombre['Yasuní']->id());
});

test('localidad de un solo nombre sin comas NO crea jerarquía ni enlaza (queda por confirmar)', function (): void {
    [$importer, $especimenRepo, , , $localidadRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([[
        'occurrenceID' => 'L2',
        'recordedBy' => 'Juan',
        'verbatimLocality' => 'Yasuní',
    ]]);

    $importer->ejecutar($fuente);

    expect($localidadRepo->buscarTodos())->toBeEmpty();

    $e = $especimenRepo->buscarPorCodigoCatalogo('L2');
    expect($e->localidadId())->toBeNull();
});

test('la misma localidad separada por comas se reutiliza entre filas (sin duplicar)', function (): void {
    [$importer, , , , $localidadRepo] = bootstrapImporter();

    $fuente = new ArrayFuenteCatalogo([
        ['occurrenceID' => 'L3', 'recordedBy' => 'Juan', 'verbatimLocality' => 'ECUADOR, ORELLANA, YASUNÍ'],
        ['occurrenceID' => 'L4', 'recordedBy' => 'Maria', 'verbatimLocality' => 'ecuador, orellana, yasuní'],
    ]);

    $importer->ejecutar($fuente);

    // Pese a la diferencia de caja, se normaliza y reutiliza: 3 localidades, no 6.
    expect($localidadRepo->buscarTodos())->toHaveCount(3);
});
