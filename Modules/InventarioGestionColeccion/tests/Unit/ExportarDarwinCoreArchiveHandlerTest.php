<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarDarwinCoreArchive\ExportarDarwinCoreArchiveInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\CriteriosCalidadGbif;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryDatasetConfigRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

function bootstrapExporter(): array
{
    $especimenRepo = new InMemoryEspecimenRepository;
    $taxonRepo = new InMemoryTaxonRepository;
    $datasetConfigRepo = new InMemoryDatasetConfigRepository;
    $handler = new ExportarDarwinCoreArchiveHandler(
        $especimenRepo,
        $taxonRepo,
        $datasetConfigRepo,
        new CriteriosCalidadGbif,
    );

    return [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo];
}

function sembrarDatasetConfig(InMemoryDatasetConfigRepository $repo): DatasetConfig
{
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'MEPN Invertebrados',
        rightsHolder: 'Escuela Politécnica Nacional',
        license: 'CC0 1.0',
        emlTitulo: 'Catálogo Invertebrados MEPN',
        emlContacto: 'curador@epn.edu.ec',
    );
    $repo->guardar($config);

    return $config;
}

function sembrarJerarquiaTaxonomica(InMemoryTaxonRepository $repo): Taxon
{
    $reino = Taxon::crear(TaxonId::generar(), 'Animalia', 'reino');
    $phylum = Taxon::crear(TaxonId::generar(), 'Arthropoda', 'phylum', padreId: (string) $reino->id());
    $clase = Taxon::crear(TaxonId::generar(), 'Insecta', 'clase', padreId: (string) $phylum->id());
    $orden = Taxon::crear(TaxonId::generar(), 'Lepidoptera', 'orden', padreId: (string) $clase->id());
    $familia = Taxon::crear(TaxonId::generar(), 'Nymphalidae', 'familia', padreId: (string) $orden->id());
    $genero = Taxon::crear(TaxonId::generar(), 'Morpho', 'genero', padreId: (string) $familia->id());
    $especie = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850, (string) $genero->id());

    foreach ([$reino, $phylum, $clase, $orden, $familia, $genero, $especie] as $t) {
        $repo->guardar($t);
    }

    return $especie;
}

function crearEspecimenParaExport(
    string $codigo,
    ?string $taxonId,
    ?float $lat,
    ?float $lon,
): Especimen {
    return Especimen::crear(
        EspecimenId::generar(),
        $codigo,
        $taxonId,
        'Yasuní',
        '2024-01-15',
        'Juan Pérez',
        decimalLatitude: $lat,
        decimalLongitude: $lon,
        geodeticDatum: 'WGS84',
        country: 'Ecuador',
        stateProvince: 'Orellana',
        localityName: 'Yasuní Onkonegare',
        individualCount: 3,
    );
}

test('rechaza exportar si no hay DatasetConfig', function (): void {
    [$handler] = bootstrapExporter();

    $handler->handle(new ExportarDarwinCoreArchiveInput);
})->throws(DomainException::class, 'DatasetConfig');

test('de 20 especímenes en distintos estados, sólo los completos se publican', function (): void {
    [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    $taxonEspecie = sembrarJerarquiaTaxonomica($taxonRepo);
    $taxonId = (string) $taxonEspecie->id();

    // 5 publicables: tienen taxon + coords
    for ($i = 1; $i <= 5; $i++) {
        $especimenRepo->guardar(crearEspecimenParaExport("OK-{$i}", $taxonId, -0.5 - $i * 0.01, -76.0));
    }
    // 5 sin coords
    for ($i = 1; $i <= 5; $i++) {
        $especimenRepo->guardar(crearEspecimenParaExport("NO-COORD-{$i}", $taxonId, null, null));
    }
    // 5 sin taxon
    for ($i = 1; $i <= 5; $i++) {
        $especimenRepo->guardar(crearEspecimenParaExport("NO-TAXON-{$i}", null, -0.5, -76.0));
    }
    // 5 sin coords ni taxon
    for ($i = 1; $i <= 5; $i++) {
        $especimenRepo->guardar(crearEspecimenParaExport("NO-NADA-{$i}", null, null, null));
    }

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->totalEspecimenes)->toBe(20)
        ->and($output->publicados)->toBe(5)
        ->and($output->excluidos)->toBe(15)
        ->and($output->motivosExclusion['sin taxón identificado'])->toBe(10)
        ->and($output->motivosExclusion['sin coordenadas geográficas'])->toBe(10);
});

test('occurrence.txt incluye header y formato MEPN:INV:{uuid}', function (): void {
    [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    $taxon = sembrarJerarquiaTaxonomica($taxonRepo);
    $especimen = crearEspecimenParaExport('MEPN-1', (string) $taxon->id(), -0.5, -76.0);
    $especimenRepo->guardar($especimen);

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->occurrenceTxt)->toContain('occurrenceID')
        ->and($output->occurrenceTxt)->toContain('basisOfRecord')
        ->and($output->occurrenceTxt)->toContain('MEPN:INV:'.((string) $especimen->id()));
});

test('occurrence.txt resuelve la jerarquía taxonómica completa', function (): void {
    [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    $taxon = sembrarJerarquiaTaxonomica($taxonRepo);
    $especimenRepo->guardar(crearEspecimenParaExport('MEPN-1', (string) $taxon->id(), -0.5, -76.0));

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->occurrenceTxt)->toContain('Animalia')
        ->and($output->occurrenceTxt)->toContain('Arthropoda')
        ->and($output->occurrenceTxt)->toContain('Insecta')
        ->and($output->occurrenceTxt)->toContain('Lepidoptera')
        ->and($output->occurrenceTxt)->toContain('Nymphalidae')
        ->and($output->occurrenceTxt)->toContain('Morpho')
        ->and($output->occurrenceTxt)->toContain('Morpho peleides');
});

test('taxon.txt incluye sólo los taxones usados con su jerarquía completa', function (): void {
    [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    $taxon = sembrarJerarquiaTaxonomica($taxonRepo);
    $especimenRepo->guardar(crearEspecimenParaExport('MEPN-1', (string) $taxon->id(), -0.5, -76.0));

    // Agregamos un taxón NO usado para verificar que NO sale en taxon.txt.
    $taxonRepo->guardar(Taxon::crear(TaxonId::generar(), 'Coleoptera', 'orden'));

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->taxonTxt)->toContain('Morpho peleides')
        ->and($output->taxonTxt)->not->toContain('Coleoptera');
});

test('eml.xml contiene los datos del DatasetConfig', function (): void {
    [$handler, , $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    sembrarJerarquiaTaxonomica($taxonRepo);

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->emlXml)->toContain('Catálogo Invertebrados MEPN')
        ->and($output->emlXml)->toContain('curador@epn.edu.ec')
        ->and($output->emlXml)->toContain('Escuela Politécnica Nacional')
        ->and($output->emlXml)->toContain('CC0 1.0')
        ->and($output->emlXml)->toContain('MEPN');
});

test('meta.xml declara core Occurrence y extension Taxon con sus columnas', function (): void {
    [$handler, , $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    sembrarJerarquiaTaxonomica($taxonRepo);

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->metaXml)->toContain('http://rs.tdwg.org/dwc/terms/Occurrence')
        ->and($output->metaXml)->toContain('http://rs.tdwg.org/dwc/terms/Taxon')
        ->and($output->metaXml)->toContain('occurrence.txt')
        ->and($output->metaXml)->toContain('taxon.txt')
        ->and($output->metaXml)->toContain('http://rs.tdwg.org/dwc/terms/scientificName')
        ->and($output->metaXml)->toContain('http://rs.tdwg.org/dwc/terms/decimalLatitude');
});

test('repositorio vacío genera DwC-A vacío pero válido', function (): void {
    [$handler, , , $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->totalEspecimenes)->toBe(0)
        ->and($output->publicados)->toBe(0)
        ->and($output->occurrenceTxt)->toContain('occurrenceID') // header
        ->and($output->taxonTxt)->toContain('taxonID');
});

test('campos con tabs o saltos de línea en TSV se escapan a espacios', function (): void {
    [$handler, $especimenRepo, $taxonRepo, $datasetConfigRepo] = bootstrapExporter();
    sembrarDatasetConfig($datasetConfigRepo);
    $taxon = sembrarJerarquiaTaxonomica($taxonRepo);

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-CON-TAB',
        (string) $taxon->id(),
        "linea1\tlinea2",
        '2024-01-15',
        "colector\ncon\nsalto",
        decimalLatitude: -0.5,
        decimalLongitude: -76.0,
    );
    $especimenRepo->guardar($especimen);

    $output = $handler->handle(new ExportarDarwinCoreArchiveInput);

    expect($output->occurrenceTxt)->not->toContain("linea1\tlinea2")
        ->and($output->occurrenceTxt)->toContain('linea1 linea2');
});
