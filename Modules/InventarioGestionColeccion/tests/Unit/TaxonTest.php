<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
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
})->with(['especie', 'genero', 'familia', 'orden', 'suborden', 'clase', 'phylum', 'reino', 'subespecie', 'tribu', 'subfamilia', 'morfoespecie']);

test('crear con padre_id lo almacena correctamente', function (): void {
    $padreId = TaxonId::generar();
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850, (string) $padreId);

    expect((string) $taxon->padreId())->toBe((string) $padreId);
});

test('crear sin autor deja autor en null', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Acragas sp.1', 'morfoespecie');

    expect($taxon->autor())->toBeNull()
        ->and($taxon->anioDescripcion())->toBeNull();
});

test('crear con autor vacío o solo espacios lo normaliza a null', function (string $autor): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Acragas sp.1', 'morfoespecie', $autor);

    expect($taxon->autor())->toBeNull();
})->with(['', '   ', "\t"]);

test('crear acepta morfoespecie como rango con alias morphospecies', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Azteca_sp.7', 'morphospecies');

    expect($taxon->rango())->toBe(RangoTaxonomico::Morfoespecie);
});

test('actualizar modifica nombre cientifico autor y anio', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    $taxon->actualizar('Morpho menelaus', 'Linnaeus', 1758);

    expect($taxon->nombreCientifico())->toBe('Morpho menelaus')
        ->and($taxon->autor())->toBe('Linnaeus')
        ->and($taxon->anioDescripcion())->toBe(1758);
});

test('actualizar puede llevar autor y anio a null', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850);

    $taxon->actualizar('Morpho peleides cf.', null, null);

    expect($taxon->autor())->toBeNull()
        ->and($taxon->anioDescripcion())->toBeNull()
        ->and($taxon->nombreCientifico())->toBe('Morpho peleides cf.');
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

test('género no puede ser padre de morfoespecie cuando ambos están al mismo nivel', function (): void {
    expect(RangoTaxonomico::Genero->puedeSerPadreDe(RangoTaxonomico::Morfoespecie))->toBeTrue()
        ->and(RangoTaxonomico::Especie->puedeSerPadreDe(RangoTaxonomico::Morfoespecie))->toBeFalse()
        ->and(RangoTaxonomico::Morfoespecie->puedeSerPadreDe(RangoTaxonomico::Especie))->toBeFalse();
});

test('suborder se intercala entre orden y familia', function (): void {
    expect(RangoTaxonomico::Orden->puedeSerPadreDe(RangoTaxonomico::Suborden))->toBeTrue()
        ->and(RangoTaxonomico::Suborden->puedeSerPadreDe(RangoTaxonomico::Familia))->toBeTrue()
        ->and(RangoTaxonomico::Suborden->puedeSerPadreDe(RangoTaxonomico::Orden))->toBeFalse()
        ->and(RangoTaxonomico::Familia->puedeSerPadreDe(RangoTaxonomico::Suborden))->toBeFalse();
});

test('alias suborder en inglés resuelve al case Suborden', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Apocrita', 'suborder');

    expect($taxon->rango())->toBe(RangoTaxonomico::Suborden);
});

test('crear con epiteto infraespecifico en rango especie lo almacena', function (): void {
    $taxon = Taxon::crear(
        id: TaxonId::generar(),
        nombreCientifico: 'Neoponera apicalis',
        rango: 'especie',
        autor: 'Latreille',
        anioDescripcion: 1802,
        padreId: null,
        epitetoInfraespecifico: 'sensu_stricto',
    );

    expect($taxon->epitetoInfraespecifico())->toBe('sensu_stricto');
});

test('crear con epiteto infraespecifico recorta espacios y normaliza vacío a null', function (?string $valor, ?string $esperado): void {
    $taxon = Taxon::crear(
        id: TaxonId::generar(),
        nombreCientifico: 'Neoponera apicalis',
        rango: 'especie',
        epitetoInfraespecifico: $valor,
    );

    expect($taxon->epitetoInfraespecifico())->toBe($esperado);
})->with([
    ['  sensu_stricto  ', 'sensu_stricto'],
    ['', null],
    ['   ', null],
    [null, null],
]);

test('crear con epiteto infraespecifico en rango distinto a especie lanza excepción', function (string $rango): void {
    Taxon::crear(
        id: TaxonId::generar(),
        nombreCientifico: 'X',
        rango: $rango,
        epitetoInfraespecifico: 'sensu_stricto',
    );
})->throws(InvalidArgumentException::class, 'epíteto')
    ->with(['genero', 'subespecie', 'morfoespecie', 'familia']);

test('actualizar permite agregar epiteto infraespecifico a un taxón especie', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Neoponera apicalis', 'especie', 'Latreille', 1802);

    $taxon->actualizar('Neoponera apicalis', 'Latreille', 1802, 'sensu_stricto');

    expect($taxon->epitetoInfraespecifico())->toBe('sensu_stricto');
});

test('actualizar rechaza epiteto en taxón con rango no-especie', function (): void {
    $taxon = Taxon::crear(TaxonId::generar(), 'Neoponera', 'genero');

    $taxon->actualizar('Neoponera', null, null, 'sensu_stricto');
})->throws(InvalidArgumentException::class, 'epíteto');

test('admiteEpitetoInfraespecifico sólo verdadero para Especie', function (RangoTaxonomico $rango, bool $esperado): void {
    expect($rango->admiteEpitetoInfraespecifico())->toBe($esperado);
})->with([
    [RangoTaxonomico::Especie, true],
    [RangoTaxonomico::Morfoespecie, false],
    [RangoTaxonomico::Subespecie, false],
    [RangoTaxonomico::Genero, false],
    [RangoTaxonomico::Suborden, false],
]);
