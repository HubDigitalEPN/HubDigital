<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Modules\CatalogoPublico\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

/**
 * Siembra una jerarquía taxonómica mínima en taxonomia.taxones y retorna el id del taxón hoja.
 * Retorna [familyId, genusId, speciesId].
 */
function crearJerarquiaTaxonomica(string $family, string $genus, string $species): array
{
    $familyId = (string) Str::uuid();
    $genusId = (string) Str::uuid();
    $speciesId = (string) Str::uuid();

    DB::table('taxonomia.taxones')->insert([
        ['id' => $familyId, 'nombre_cientifico' => $family, 'rango' => 'familia', 'padre_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $genusId, 'nombre_cientifico' => $genus, 'rango' => 'genero', 'padre_id' => $familyId, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $speciesId, 'nombre_cientifico' => $species, 'rango' => 'especie', 'padre_id' => $genusId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return [$familyId, $genusId, $speciesId];
}

function crearEspecimenEnTaxonomia(string $taxonId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('taxonomia.especimenes')->insert(array_merge([
        'id' => $id,
        'codigo_catalogo' => 'TEST-'.substr($id, 0, 8),
        'taxon_id' => $taxonId,
        'occurrence_id' => 'OCC-'.substr($id, 0, 8),
        'localidad' => 'Pichincha',
        'fecha_colecta' => '2024-01-15',
        'colector' => 'Dr. Entomólogo',
        'estado' => 'disponible',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

test('traduce correctamente los campos básicos del Supplier al lenguaje del Customer', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Formicidae', 'Atta', 'Atta cephalotes');

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => 'EPN-0012',
        'colector' => 'Ana Torres',
        'individual_count' => 3,
        'disposition' => 'Holotype',
        'occurrence_status' => 'present',
        'specimen_notes' => 'Obrera recolectada',
        'country' => 'Ecuador',
        'locality_name' => 'Reserva Yasuní',
        'decimal_latitude' => -0.6753,
        'decimal_longitude' => -76.3981,
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId('EPN-0012');

    expect($datos)->not->toBeNull()
        ->and($datos->especimenId)->not->toBeEmpty()
        ->and($datos->occurrenceId)->toBe('EPN-0012')
        ->and($datos->scientificName)->toBe('Atta cephalotes')
        ->and($datos->individualCount)->toBe(3)
        ->and($datos->typeStatus)->toBe('Holotype')        // ACL: disposition → typeStatus
        ->and($datos->recordedBy)->toBe('Ana Torres')      // ACL: colector → recordedBy
        ->and($datos->occurrenceStatus)->toBe('present')
        ->and($datos->specimenNotes)->toBe('Obrera recolectada')
        ->and($datos->country)->toBe('Ecuador')
        ->and($datos->localityName)->toBe('Reserva Yasuní')
        ->and($datos->decimalLatitude)->toBeCloseTo(-0.6753, 4)
        ->and($datos->decimalLongitude)->toBeCloseTo(-76.3981, 4);
});

test('resuelve familia y género recorriendo la jerarquía taxonómica', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Apidae', 'Bombus', 'Bombus atratus');

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => 'EPN-002',
        'colector' => 'Carlos Mena',
        'occurrence_status' => 'loaned',
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId('EPN-002');

    expect($datos)->not->toBeNull()
        ->and($datos->family)->toBe('Apidae')
        ->and($datos->genus)->toBe('Bombus');
});

test('especimenId coincide con el id de taxonomia.especimenes', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Vespidae', 'Polistes', 'Polistes versicolor');

    $id = crearEspecimenEnTaxonomia($speciesId, ['occurrence_id' => 'EPN-UUID-CHECK']);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId('EPN-UUID-CHECK');

    expect($datos)->not->toBeNull()
        ->and($datos->especimenId)->toBe($id);
});

test('retorna null si el occurrenceId no existe en taxonomia', function (): void {
    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);

    expect($adapter->buscarPorOccurrenceId('NO-EXISTE'))->toBeNull();
});

test('obtenerTodos omite especímenes sin occurrence_id', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Nymphalidae', 'Morpho', 'Morpho menelaus');

    // Sin occurrence_id (debe ser ignorado)
    crearEspecimenEnTaxonomia($speciesId, ['occurrence_id' => null]);

    // Con occurrence_id (debe aparecer)
    crearEspecimenEnTaxonomia($speciesId, ['occurrence_id' => 'EPN-005']);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $todos = $adapter->obtenerTodos();

    $occurrenceIds = array_column($todos, null);
    $encontrado = array_filter($todos, fn ($d) => $d->occurrenceId === 'EPN-005');

    expect($encontrado)->toHaveCount(1);

    foreach ($todos as $dato) {
        expect($dato->occurrenceId)->not->toBeNull()->and($dato->occurrenceId)->not->toBe('');
    }
});

test('usa present como occurrenceStatus por defecto cuando el Supplier no tiene valor', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Formicidae', 'Solenopsis', 'Solenopsis invicta');

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => 'EPN-DEFAULT',
        'occurrence_status' => null,
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId('EPN-DEFAULT');

    expect($datos->occurrenceStatus)->toBe('present');
});
