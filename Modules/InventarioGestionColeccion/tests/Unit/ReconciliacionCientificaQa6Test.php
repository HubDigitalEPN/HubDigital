<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConstructorTaxonomiaImport;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ReconciliacionCientificaQa6;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

test('QA6-002 nuevas importaciones no reintroducen Coleoptera ni pierden su clasificación fuente', function (): void {
    $original = ['occurrence_id' => 'MEPN-INV-37369', 'kingdom' => 'Animalia', 'phylum' => 'Arthropoda',
        'class' => 'Insecta', 'order' => 'Coleoptera', 'family' => 'Chrysomelidae', 'subfamily' => 'Cassidinae',
        'genus' => 'Anastrepha', 'specific_epithet' => 'freidbergi', 'taxon_rank' => 'species',
        'taxonomic_notes' => 'Texto de etiqueta conservado'];
    $repositorio = new InMemoryTaxonRepository;
    $constructor = new ConstructorTaxonomiaImport($repositorio);
    $id = $constructor->resolverDeFila($original);
    $linaje = [];
    while ($id !== null) {
        $taxon = $repositorio->buscarPorId($id);
        $linaje[] = $taxon->nombreCientifico();
        $id = $taxon->padreId();
    }
    $mapeada = (new FilaCatalogoMapper)->mapear($original);
    expect($linaje)->toBe(['Anastrepha freidbergi', 'Anastrepha', 'Tephritidae', 'Diptera', 'Insecta', 'Arthropoda', 'Animalia'])
        ->and($mapeada->taxonomicNotes)->toContain('Texto de etiqueta conservado', 'Coleoptera', 'Chrysomelidae', 'Cassidinae', ReconciliacionCientificaQa6::FUENTE_TAXONOMIA)
        ->and($mapeada->requiereRevision())->toBeTrue();
});

test('QA6-002 conserva taxonomía válida y otros géneros sin sustituirlos por similitud', function (): void {
    $valida = ['genus' => 'Anastrepha', 'order' => 'Diptera', 'family' => 'Tephritidae', 'subfamily' => 'Trypetinae'];
    $ajena = ['genus' => 'Anastrephus', 'order' => 'Coleoptera', 'family' => 'Chrysomelidae'];
    expect(ReconciliacionCientificaQa6::taxonomia($valida))->toBe($valida)
        ->and(ReconciliacionCientificaQa6::notaTaxonomia($valida))->toBeNull()
        ->and(ReconciliacionCientificaQa6::taxonomia($ajena))->toBe($ajena);
});

function qa6FilaRaguaOriginal(): array
{
    return ['occurrenceID' => 'MEPN-INV-30203', 'oldCode' => '787', 'country' => 'Ecuador',
        'stateProvince' => 'Granada', 'verbatimLocality' => 'Sierra Nevada, Puerto de la Ragua',
        'decimalLatitude' => '-4.0226841', 'decimalLongitude' => '-79.194422',
        'specimenNotes' => 'Notas de etiqueta', 'localityNotes' => 'Procedencia original'];
}

test('QA6-003 importación retiene fuente de Ragua y retira el par incompatible sin reemplazo', function (): void {
    $mapeada = (new FilaCatalogoMapper)->mapear(qa6FilaRaguaOriginal());
    expect($mapeada->decimalLatitude)->toBeNull()->and($mapeada->decimalLongitude)->toBeNull()
        ->and($mapeada->country)->toBe('Ecuador')->and($mapeada->stateProvince)->toBe('Granada')
        ->and($mapeada->localidadVerbatim)->toBe('Sierra Nevada, Puerto de la Ragua')
        ->and($mapeada->coordVerbatim)->toBe('-4.0226841 / -79.194422')
        ->and($mapeada->verbatimLatitude)->toBe('-4.0226841')->and($mapeada->verbatimLongitude)->toBe('-79.194422')
        ->and($mapeada->specimenNotes)->toContain('Notas de etiqueta', 'QA6-003', ReconciliacionCientificaQa6::FUENTE_RAGUA)
        ->and($mapeada->localityNotes)->toContain('Procedencia original', 'No se ha confirmado el lugar de colecta')
        ->and($mapeada->motivoRevision())->toContain('conflicto geográfico pendiente');
});

test('QA6-003 no aplica la cuarentena a un código repetido ni a una coordenada ya corregida', function (array $cambio): void {
    $mapeada = (new FilaCatalogoMapper)->mapear(array_replace(qa6FilaRaguaOriginal(), $cambio));
    expect($mapeada->decimalLatitude)->not->toBeNull()->and($mapeada->decimalLongitude)->not->toBeNull()
        ->and($mapeada->specimenNotes)->toBe('Notas de etiqueta');
})->with([
    'otra muestra' => [['oldCode' => '788']],
    'otro ejemplar' => [['occurrenceID' => 'MEPN-INV-OTRO']],
    'otra localidad' => [['verbatimLocality' => 'Sierra Nevada, otra localidad']],
    'país curado' => [['country' => 'España']],
    'coordenadas curadas' => [['decimalLatitude' => '37.1', 'decimalLongitude' => '-3.0']],
]);

test('QA6-003 no desborda ni trunca un verbatim previo al preservar la fuente', function (): void {
    $previo = str_repeat('V', 255);
    $mapeada = (new FilaCatalogoMapper)->mapear(array_replace(qa6FilaRaguaOriginal(), ['coord_verbatim' => $previo]));
    expect($mapeada->coordVerbatim)->toBe($previo)
        ->and($mapeada->verbatimLatitude)->toBe('-4.0226841')
        ->and($mapeada->verbatimLongitude)->toBe('-79.194422')
        ->and($mapeada->localityNotes)->toContain('-4.0226841, -79.194422');
});
