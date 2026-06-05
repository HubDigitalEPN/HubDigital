<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function sembrarEspecimenParaIdentificacion(): Especimen
{
    $especimenRepo = app(EspecimenRepositoryInterface::class);
    $taxonRepo = app(TaxonRepositoryInterface::class);

    $taxon = Taxon::crear($taxonRepo->nextIdentity(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonRepo->guardar($taxon);

    $especimen = Especimen::crear(
        $especimenRepo->nextIdentity(),
        'MEPN-INV-test-'.bin2hex(random_bytes(4)),
        (string) $taxon->id(),
        'Pichincha',
        '2024-01-01',
        'Colector test',
    );
    $especimenRepo->guardar($especimen);

    return $especimen;
}

test('guarda y recupera una identificación con todos los campos', function (): void {
    $repo = app(IdentificacionRepositoryInterface::class);
    $taxonRepo = app(TaxonRepositoryInterface::class);
    $especimen = sembrarEspecimenParaIdentificacion();

    $taxon = Taxon::crear($taxonRepo->nextIdentity(), 'Morpho menelaus', 'especie', 'Linnaeus', 1758);
    $taxonRepo->guardar($taxon);

    $identificacion = Identificacion::crear(
        id: $repo->nextIdentity(),
        especimenId: $especimen->id(),
        taxonId: (string) $taxon->id(),
        identificadoPor: 'Dra. M. Cárdenas',
        fechaDeterminacion: new DateTimeImmutable('2024-05-12'),
        calificador: 'cf.',
        observaciones: 'Determinación tentativa',
    );

    $repo->guardar($identificacion);

    $encontrado = $repo->buscarPorId($identificacion->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->identificadoPor())->toBe('Dra. M. Cárdenas')
        ->and($encontrado->fechaDeterminacion()?->format('Y-m-d'))->toBe('2024-05-12')
        ->and($encontrado->calificador())->toBe('cf.')
        ->and($encontrado->esActual())->toBeTrue();
});

test('desactualizarTodasDe baja flag en todas las identificaciones actuales del espécimen', function (): void {
    $repo = app(IdentificacionRepositoryInterface::class);
    $taxonRepo = app(TaxonRepositoryInterface::class);
    $especimen = sembrarEspecimenParaIdentificacion();

    $taxon = Taxon::crear($taxonRepo->nextIdentity(), 'Morpho menelaus', 'especie', 'Linnaeus', 1758);
    $taxonRepo->guardar($taxon);

    $i = Identificacion::crear(
        id: $repo->nextIdentity(),
        especimenId: $especimen->id(),
        taxonId: (string) $taxon->id(),
        esActual: true,
    );
    $repo->guardar($i);

    $repo->desactualizarTodasDe($especimen->id());

    $actual = $repo->buscarActualDe($especimen->id());
    expect($actual)->toBeNull();
});

test('listarPorEspecimen ordena la actual primero', function (): void {
    $repo = app(IdentificacionRepositoryInterface::class);
    $taxonRepo = app(TaxonRepositoryInterface::class);
    $especimen = sembrarEspecimenParaIdentificacion();

    $taxonViejo = Taxon::crear($taxonRepo->nextIdentity(), 'A historica', 'especie', 'Auct.', 1900);
    $taxonNuevo = Taxon::crear($taxonRepo->nextIdentity(), 'B actual', 'especie', 'Auct.', 2024);
    $taxonRepo->guardar($taxonViejo);
    $taxonRepo->guardar($taxonNuevo);

    $repo->guardar(Identificacion::crear(
        id: $repo->nextIdentity(),
        especimenId: $especimen->id(),
        taxonId: (string) $taxonViejo->id(),
        fechaDeterminacion: new DateTimeImmutable('2010-01-01'),
        esActual: false,
    ));
    $repo->guardar(Identificacion::crear(
        id: $repo->nextIdentity(),
        especimenId: $especimen->id(),
        taxonId: (string) $taxonNuevo->id(),
        fechaDeterminacion: new DateTimeImmutable('2024-01-01'),
        esActual: true,
    ));

    $lista = $repo->listarPorEspecimen($especimen->id());

    expect($lista)->toHaveCount(2)
        ->and($lista[0]->esActual())->toBeTrue()
        ->and((string) $lista[0]->taxonId())->toBe((string) $taxonNuevo->id());
});
