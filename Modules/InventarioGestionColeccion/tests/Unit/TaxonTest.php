<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

test('crear un taxon con datos validos lo deja en estado activo', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    expect($taxon->estado())->toBe(EstadoTaxon::Activo)
        ->and($taxon->nombreCientifico())->toBe('Morpho peleides')
        ->and($taxon->rango()->value)->toBe('especie')
        ->and($taxon->autor())->toBe('Kollar')
        ->and($taxon->anioDescripcion())->toBe(1850)
        ->and($taxon->padreId())->toBeNull();
});

test('crear recorta espacios del nombre cientifico y del autor', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), '  Morpho peleides  ', 'especie', '  Kollar  ', 1850);

    expect($taxon->nombreCientifico())->toBe('Morpho peleides')
        ->and($taxon->autor())->toBe('Kollar');
});

test('crear con rango invalido lanza InvalidArgumentException', function (): void {
    Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'rango_inexistente', 'Kollar', 1850);
})->throws(InvalidArgumentException::class, 'rango');

test('crear acepta todos los rangos validos', function (string $rango): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Nombre valido', $rango, 'Autor', 2000);

    expect($taxon->rango()->value)->toBe($rango);
})->with(['especie', 'genero', 'familia', 'orden', 'clase', 'phylum', 'reino', 'subespecie', 'tribu', 'subfamilia']);

test('crear con padre_id lo almacena correctamente', function (): void {
    $padreId = TaxonId::generar();
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850, (string) $padreId);

    expect((string) $taxon->padreId())->toBe((string) $padreId);
});

test('actualizar modifica nombre cientifico autor y anio', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    $taxon->actualizar('Morpho menelaus', 'Linnaeus', 1758);

    expect($taxon->nombreCientifico())->toBe('Morpho menelaus')
        ->and($taxon->autor())->toBe('Linnaeus')
        ->and($taxon->anioDescripcion())->toBe(1758);
});

test('actualizar no cambia el rango ni el estado', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    $taxon->actualizar('Morpho menelaus', 'Linnaeus', 1758);

    expect($taxon->rango()->value)->toBe('especie')
        ->and($taxon->estado())->toBe(EstadoTaxon::Activo);
});

test('desactivar cambia el estado a inactivo', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    $taxon->desactivar();

    expect($taxon->estado())->toBe(EstadoTaxon::Inactivo);
});

test('el id asignado en crear se conserva', function (): void {
    $id = TaxonId::generar();
    $taxon = Taxon::crear($id, 'Morpho peleides', 'especie', 'Kollar', 1850);

    expect((string) $taxon->id())->toBe((string) $id);
});
