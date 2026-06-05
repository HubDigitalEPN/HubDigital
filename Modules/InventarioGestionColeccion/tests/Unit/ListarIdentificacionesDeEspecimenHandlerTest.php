<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen\ListarIdentificacionesDeEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen\ListarIdentificacionesDeEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryIdentificacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

test('listar identificaciones devuelve la actual primero y resuelve nombres de taxones', function (): void {
    $identificacionRepo = new InMemoryIdentificacionRepository;
    $taxonRepo = new InMemoryTaxonRepository;
    $especimenId = EspecimenId::generar();

    $taxonViejo = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonNuevo = Taxon::crear(TaxonId::generar(), 'Morpho menelaus', 'especie', 'Linnaeus', 1758);
    $taxonRepo->guardar($taxonViejo);
    $taxonRepo->guardar($taxonNuevo);

    $idHistorica = Identificacion::crear(
        id: IdentificacionId::generar(),
        especimenId: $especimenId,
        taxonId: (string) $taxonViejo->id(),
        fechaDeterminacion: new DateTimeImmutable('2020-01-01'),
        esActual: false,
    );
    $idActual = Identificacion::crear(
        id: IdentificacionId::generar(),
        especimenId: $especimenId,
        taxonId: (string) $taxonNuevo->id(),
        fechaDeterminacion: new DateTimeImmutable('2024-05-12'),
        esActual: true,
    );
    $identificacionRepo->guardar($idHistorica);
    $identificacionRepo->guardar($idActual);

    $output = (new ListarIdentificacionesDeEspecimenHandler($identificacionRepo, $taxonRepo))->handle(
        new ListarIdentificacionesDeEspecimenInput(especimenId: (string) $especimenId)
    );

    expect($output->total)->toBe(2)
        ->and($output->items[0]->esActual)->toBeTrue()
        ->and($output->items[0]->taxonNombre)->toBe('Morpho menelaus')
        ->and($output->items[1]->esActual)->toBeFalse()
        ->and($output->items[1]->taxonNombre)->toBe('Morpho peleides');
});

test('listar de un espécimen sin identificaciones devuelve total 0', function (): void {
    $identificacionRepo = new InMemoryIdentificacionRepository;
    $taxonRepo = new InMemoryTaxonRepository;

    $output = (new ListarIdentificacionesDeEspecimenHandler($identificacionRepo, $taxonRepo))->handle(
        new ListarIdentificacionesDeEspecimenInput(especimenId: (string) EspecimenId::generar())
    );

    expect($output->total)->toBe(0)
        ->and($output->items)->toBe([]);
});
