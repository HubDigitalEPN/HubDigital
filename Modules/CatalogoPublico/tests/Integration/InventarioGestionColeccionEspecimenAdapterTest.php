<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Infrastructure\Adapters\InventarioGestionColeccionEspecimenAdapter;
use Tests\PostgresIntegrationTestCase;

uses(PostgresIntegrationTestCase::class);

/**
 * Siembra una jerarquía taxonómica mínima en taxonomia.taxones y retorna el id del taxón hoja.
 * Retorna [familyId, genusId, speciesId, familyName, genusName, speciesName].
 */
function crearJerarquiaTaxonomica(string $family, string $genus, string $species): array
{
    $familyId = (string) Str::uuid();
    $genusId = (string) Str::uuid();
    $speciesId = (string) Str::uuid();
    $sufijo = ' QA-'.Str::uuid();
    $familyName = $family.$sufijo;
    $genusName = $genus.$sufijo;
    $speciesName = $species.$sufijo;

    DB::table('taxonomia.taxones')->insert([
        ['id' => $familyId, 'nombre_cientifico' => $familyName, 'rango' => 'familia', 'padre_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $genusId, 'nombre_cientifico' => $genusName, 'rango' => 'genero', 'padre_id' => $familyId, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $speciesId, 'nombre_cientifico' => $speciesName, 'rango' => 'especie', 'padre_id' => $genusId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return [$familyId, $genusId, $speciesId, $familyName, $genusName, $speciesName];
}

function crearEspecimenEnTaxonomia(string $taxonId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('taxonomia.especimenes')->insert(array_merge([
        'id' => $id,
        'codigo_catalogo' => 'TEST-'.$id,
        'taxon_id' => $taxonId,
        'occurrence_id' => 'OCC-'.$id,
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
    [, , $speciesId, , , $speciesName] = crearJerarquiaTaxonomica('Formicidae', 'Atta', 'Atta cephalotes');
    $occurrenceId = 'QA-'.Str::uuid();

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => $occurrenceId,
        'colector' => 'Ana Torres',
        'individual_count' => 3,
        'type_status' => 'Holotype',
        'type_notes' => 'Designación nomenclatural documentada',
        'disposition' => 'in_collection',
        'occurrence_status' => 'present',
        'specimen_notes' => 'Obrera recolectada',
        'country' => 'Ecuador',
        'locality_name' => 'Reserva Yasuní',
        'decimal_latitude' => -0.6753,
        'decimal_longitude' => -76.3981,
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);

    expect($datos)->not->toBeNull()
        ->and($datos->especimenId)->not->toBeEmpty()
        ->and($datos->occurrenceId)->toBe($occurrenceId)
        ->and($datos->scientificName)->toBe($speciesName)
        ->and($datos->individualCount)->toBe(3)
        ->and($datos->typeStatus)->toBe('Holotype')
        ->and($datos->typeNotes)->toBe('Designación nomenclatural documentada')
        ->and($datos->disposition)->toBe('in_collection')
        ->and($datos->recordedBy)->toBe('Ana Torres')      // ACL: colector → recordedBy
        ->and($datos->occurrenceStatus)->toBe('present')
        ->and($datos->specimenNotes)->toBe('Obrera recolectada')
        ->and($datos->country)->toBe('Ecuador')
        ->and($datos->localityName)->toBe('Reserva Yasuní')
        ->and($datos->decimalLatitude)->toBe(-0.6753)
        ->and($datos->decimalLongitude)->toBe(-76.3981);
});

test('resuelve familia y género recorriendo la jerarquía taxonómica', function (): void {
    [, , $speciesId, $familyName, $genusName] = crearJerarquiaTaxonomica('Apidae', 'Bombus', 'Bombus atratus');
    $occurrenceId = 'QA-'.Str::uuid();

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => $occurrenceId,
        'colector' => 'Carlos Mena',
        'occurrence_status' => 'loaned',
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);

    expect($datos)->not->toBeNull()
        ->and($datos->family)->toBe($familyName)
        ->and($datos->genus)->toBe($genusName);
});

test('especimenId coincide con el id de taxonomia.especimenes', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Vespidae', 'Polistes', 'Polistes versicolor');

    $occurrenceId = 'QA-'.Str::uuid();
    $id = crearEspecimenEnTaxonomia($speciesId, ['occurrence_id' => $occurrenceId]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);

    expect($datos)->not->toBeNull()
        ->and($datos->especimenId)->toBe($id);
});

test('retorna null si el occurrenceId no existe en taxonomia', function (): void {
    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);

    expect($adapter->buscarPorOccurrenceId('QA-NO-EXISTE-'.Str::uuid()))->toBeNull();
});

test('obtenerTodos omite especímenes sin occurrence_id', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Nymphalidae', 'Morpho', 'Morpho menelaus');

    // Sin occurrence_id (debe ser ignorado)
    $sinOccurrenceId = crearEspecimenEnTaxonomia($speciesId, ['occurrence_id' => null]);

    // Con occurrence_id (debe aparecer)
    $conOccurrenceId = crearEspecimenEnTaxonomia($speciesId);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $todos = $adapter->obtenerTodos();

    $ids = array_map(fn ($dato) => $dato->especimenId, $todos);

    expect($ids)->toContain($conOccurrenceId)->not->toContain($sinOccurrenceId);
});

test('traduce los nuevos campos de divulgación y resuelve sampling_protocol vía muestra', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Formicidae', 'Camponotus', 'Camponotus femoratus');
    $occurrenceId = 'QA-'.Str::uuid();

    $muestraId = (string) Str::uuid();
    DB::table('taxonomia.muestras_colecta')->insert([
        'id' => $muestraId,
        'codigo_muestra' => 'M-TEST-'.Str::uuid(),
        'sampling_protocol' => 'Trampa Winkler',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => $occurrenceId,
        'muestra_id' => $muestraId,
        'state_province' => 'Napo',
        'fecha_colecta' => '2024-03-20',
        'caste' => 'obrera',
        'life_stage' => 'adulto',
        'elevation_min_m' => 1200,
        'elevation_max_m' => 1500,
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);

    expect($datos)->not->toBeNull()
        ->and($datos->samplingProtocol)->toBe('Trampa Winkler')
        ->and($datos->stateProvince)->toBe('Napo')
        ->and($datos->eventDate)->toContain('2024-03-20')
        ->and($datos->caste)->toBe('obrera')
        ->and($datos->lifeStage)->toBe('adulto')
        ->and($datos->elevationMinM)->toBe(1200.0)
        ->and($datos->elevationMaxM)->toBe(1500.0);
});

test('usa present como occurrenceStatus por defecto cuando el Supplier no tiene valor', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Formicidae', 'Solenopsis', 'Solenopsis invicta');
    $occurrenceId = 'QA-'.Str::uuid();

    crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => $occurrenceId,
        'occurrence_status' => null,
    ]);

    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);

    expect($datos->occurrenceStatus)->toBe('present');
});

test('QA6 conserva el tipo desconocido y la disposición sin convertirlos en tipo nomenclatural', function (): void {
    [, , $speciesId] = crearJerarquiaTaxonomica('Formicidae', 'Camponotus', 'Camponotus contrato QA6');
    $occurrenceId = 'QA6-'.Str::uuid();
    $id = crearEspecimenEnTaxonomia($speciesId, [
        'occurrence_id' => $occurrenceId, 'type_status' => null, 'disposition' => 'in_collection',
        'lat_lon_max_error' => 'Coordenadas recuperadas del Excel; precisión pendiente de revisión.',
    ]);
    $adapter = app(InventarioGestionColeccionEspecimenAdapter::class);
    foreach ([$adapter->buscarPorOccurrenceId($occurrenceId), $adapter->buscarPorEspecimenIds([$id])[0],
        $adapter->buscarPorOccurrenceIds([$occurrenceId])[0]] as $datos) {
        expect($datos->typeStatus)->toBeNull()->and($datos->disposition)->toBe('in_collection')
            ->and($datos->coordinateReference)->toBe('Coordenadas recuperadas del Excel; precisión pendiente de revisión.');
    }
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['type_status' => 'Estado curatorial no catalogado', 'disposition' => null]);
    $datos = $adapter->buscarPorOccurrenceId($occurrenceId);
    expect($datos->typeStatus)->toBe('Estado curatorial no catalogado')->and($datos->disposition)->toBeNull();
});
