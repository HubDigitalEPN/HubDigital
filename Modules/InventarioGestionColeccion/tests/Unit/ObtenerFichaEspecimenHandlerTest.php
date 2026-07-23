<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

/** @return array{0: ObtenerFichaEspecimenHandler, 1: string} [handler, especimenId] */
function sembrarFicha(): array
{
    $taxonRepo = new InMemoryTaxonRepository;
    $especimenRepo = new InMemoryEspecimenRepository;

    $especie = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie');
    $taxonRepo->guardar($especie);

    $id = EspecimenId::generar();
    $especimenRepo->guardar(Especimen::crear(
        $id, 'MEPN-1', (string) $especie->id(), 'Yasuní', '2001-02-14', 'Juan',
    ));

    return [new ObtenerFichaEspecimenHandler($especimenRepo, $taxonRepo), (string) $id];
}

test('la ficha devuelve TODAS las columnas del catálogo con el taxón resuelto', function (): void {
    [$handler, $id] = sembrarFicha();

    $out = $handler->handle(new ObtenerFichaEspecimenInput($id));

    expect($out->encontrado)->toBeTrue()
        ->and($out->ficha['codigoCatalogo'])->toBe('MEPN-1')
        ->and($out->ficha['taxonNombre'])->toBe('Morpho peleides')
        ->and($out->ficha['localidad'])->toBe('Yasuní');

    // La ficha expone cada columna declarada en el catálogo (sin recortes).
    foreach (RegistroColumnasEspecimen::todas() as $col) {
        expect($out->ficha)->toHaveKey($col['clave']);
    }
});

test('un id inexistente devuelve encontrado=false sin lanzar', function (): void {
    [$handler] = sembrarFicha();

    $out = $handler->handle(new ObtenerFichaEspecimenInput((string) EspecimenId::generar()));

    expect($out->encontrado)->toBeFalse()
        ->and($out->ficha)->toBe([]);
});

test('un id con formato inválido devuelve encontrado=false sin lanzar', function (): void {
    [$handler] = sembrarFicha();

    $out = $handler->handle(new ObtenerFichaEspecimenInput('no-es-uuid'));

    expect($out->encontrado)->toBeFalse();
});
