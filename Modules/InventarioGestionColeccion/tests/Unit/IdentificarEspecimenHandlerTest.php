<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen\IdentificarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IdentificarEspecimen\IdentificarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryIdentificacionRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

function bootstrapIdentificarHandler(): array
{
    $identificacionRepo = new InMemoryIdentificacionRepository;
    $especimenRepo = new InMemoryEspecimenRepository;
    $taxonRepo = new InMemoryTaxonRepository;
    $handler = new IdentificarEspecimenHandler($identificacionRepo, $especimenRepo, $taxonRepo);

    return [$handler, $identificacionRepo, $especimenRepo, $taxonRepo];
}

test('identifica un espécimen y queda como actual', function (): void {
    [$handler, $identificacionRepo, $especimenRepo, $taxonRepo] = bootstrapIdentificarHandler();
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonRepo->guardar($taxon);
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        null,
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    $output = $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) $especimen->id(),
        taxonId: (string) $taxon->id(),
        identificadoPor: 'Dra. M. Cárdenas',
        fechaDeterminacion: '2024-05-12',
        calificador: 'cf.',
    ));

    expect($output->esActual)->toBeTrue()
        ->and($output->taxonId)->toBe((string) $taxon->id());

    // El espécimen quedó enlazado al taxón
    $especimenPersistido = $especimenRepo->buscarPorId($especimen->id());
    expect($especimenPersistido->taxonId())->toBe((string) $taxon->id());

    // Existe una identificación actual para el espécimen
    $actual = $identificacionRepo->buscarActualDe($especimen->id());
    expect($actual)->not->toBeNull()
        ->and((string) $actual->id())->toBe((string) $output->id);
});

test('invariante: una nueva identificación supersede a las anteriores', function (): void {
    [$handler, $identificacionRepo, $especimenRepo, $taxonRepo] = bootstrapIdentificarHandler();
    $taxonA = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonB = Taxon::crear(TaxonId::generar(), 'Morpho menelaus', 'especie', 'Linnaeus', 1758);
    $taxonRepo->guardar($taxonA);
    $taxonRepo->guardar($taxonB);
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        null,
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) $especimen->id(),
        taxonId: (string) $taxonA->id(),
    ));
    $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) $especimen->id(),
        taxonId: (string) $taxonB->id(),
    ));

    $todas = $identificacionRepo->listarPorEspecimen($especimen->id());
    expect($todas)->toHaveCount(2);

    $actuales = array_filter($todas, fn ($i) => $i->esActual());
    expect($actuales)->toHaveCount(1);

    $actual = array_values($actuales)[0];
    expect((string) $actual->taxonId())->toBe((string) $taxonB->id());
});

test('rechaza espécimen inexistente', function (): void {
    [$handler] = bootstrapIdentificarHandler();

    $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) EspecimenId::generar(),
    ));
})->throws(DomainException::class, 'Espécimen');

test('rechaza taxón inexistente', function (): void {
    [$handler, , $especimenRepo] = bootstrapIdentificarHandler();
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        null,
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) $especimen->id(),
        taxonId: (string) TaxonId::generar(),
    ));
})->throws(DomainException::class, 'Taxón');

test('identificación sin taxonId no toca el taxon del espécimen', function (): void {
    [$handler, $identificacionRepo, $especimenRepo, $taxonRepo] = bootstrapIdentificarHandler();
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonRepo->guardar($taxon);
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        (string) $taxon->id(),
        'X',
        '2024-01-01',
        'C',
    );
    $especimenRepo->guardar($especimen);

    $handler->handle(new IdentificarEspecimenInput(
        especimenId: (string) $especimen->id(),
        taxonId: null,
        observaciones: 'no se pudo identificar — falta material clave',
    ));

    expect($especimenRepo->buscarPorId($especimen->id())->taxonId())->toBe((string) $taxon->id());
});
