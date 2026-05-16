<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon\DesactivarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon\DesactivarTaxonInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

test('desactivar un taxon existente cambia el estado a inactivo', function (): void {
    $repo = new InMemoryTaxonRepository;
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $repo->guardar($taxon);

    $output = (new DesactivarTaxonHandler($repo))->handle(
        new DesactivarTaxonInput(taxonId: (string) $taxon->id())
    );

    expect($output->estado)->toBe('inactivo')
        ->and($output->taxonId)->toBe((string) $taxon->id());

    $persistido = $repo->buscarPorId($taxon->id());
    expect($persistido->estado())->toBe(EstadoTaxon::Inactivo);
});

test('desactivar persiste el cambio en el repositorio', function (): void {
    $repo = new InMemoryTaxonRepository;
    $taxon = Taxon::crear(TaxonId::generar(), 'Heliconius melpomene', 'especie', 'Linnaeus', 1758);
    $repo->guardar($taxon);

    (new DesactivarTaxonHandler($repo))->handle(
        new DesactivarTaxonInput(taxonId: (string) $taxon->id())
    );

    expect($repo->buscarPorId($taxon->id())->estado())->toBe(EstadoTaxon::Inactivo);
});

test('desactivar un taxon inexistente lanza DomainException', function (): void {
    $handler = new DesactivarTaxonHandler(new InMemoryTaxonRepository);

    $handler->handle(new DesactivarTaxonInput(taxonId: '00000000-0000-4000-8000-000000000000'));
})->throws(DomainException::class);

test('el output de desactivar contiene el taxonId correcto', function (): void {
    $repo = new InMemoryTaxonRepository;
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $repo->guardar($taxon);

    $output = (new DesactivarTaxonHandler($repo))->handle(
        new DesactivarTaxonInput(taxonId: (string) $taxon->id())
    );

    expect($output->taxonId)->toBe((string) $taxon->id());
});
