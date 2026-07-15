<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados\ExportarEspecimenesSeleccionadosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados\ExportarEspecimenesSeleccionadosInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

test('arma filas Darwin Core con jerarquía resuelta solo para los seleccionados', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $taxonRepo = new InMemoryTaxonRepository;

    $familia = Taxon::crear(TaxonId::generar(), 'Nymphalidae', 'familia');
    $genero = Taxon::crear(TaxonId::generar(), 'Morpho', 'genero', padreId: (string) $familia->id());
    $especie = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', padreId: (string) $genero->id());
    $taxonRepo->guardar($familia);
    $taxonRepo->guardar($genero);
    $taxonRepo->guardar($especie);

    $e1 = Especimen::crear(
        EspecimenId::generar(), 'MEPN-1', (string) $especie->id(), 'Yasuní', '2001-02-14', 'Juan',
        catalogNumber: 'CAT-1', country: 'Ecuador', decimalLatitude: -0.6, decimalLongitude: -76.4,
    );
    $e2 = Especimen::crear(
        EspecimenId::generar(), 'MEPN-2', (string) $especie->id(), 'Napo', '2002-03-10', 'Maria',
    );
    $especimenRepo->guardar($e1);
    $especimenRepo->guardar($e2);

    $handler = new ExportarEspecimenesSeleccionadosHandler($especimenRepo, $taxonRepo);
    $output = $handler->handle(new ExportarEspecimenesSeleccionadosInput([(string) $e1->id()]));

    expect($output->filas)->toHaveCount(1);

    $fila = $output->filas[0];
    expect($fila['scientificName'])->toBe('Morpho peleides')
        ->and($fila['family'])->toBe('Nymphalidae')
        ->and($fila['genus'])->toBe('Morpho')
        ->and($fila['catalogNumber'])->toBe('CAT-1')
        ->and($fila['country'])->toBe('Ecuador')
        ->and($fila['occurrenceID'])->toStartWith('MEPN:INV:')
        ->and($output->columnas)->toContain('scientificName', 'family', 'decimalLatitude');
});

test('ids inexistentes devuelven cero filas', function (): void {
    $handler = new ExportarEspecimenesSeleccionadosHandler(
        new InMemoryEspecimenRepository,
        new InMemoryTaxonRepository,
    );

    $output = $handler->handle(new ExportarEspecimenesSeleccionadosInput(['no-existe']));

    expect($output->filas)->toBe([]);
});
