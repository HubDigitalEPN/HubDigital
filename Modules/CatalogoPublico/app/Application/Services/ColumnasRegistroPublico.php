<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;

/** Configura presentación de DTO públicos; nunca concede visibilidad de datos. */
final class ColumnasRegistroPublico
{
    private const MAPA = [
        'occurrence_id' => ['codigoCatalogo', 'catalogNumber'],
        'scientific_name' => ['taxonNombre', 'scientificName'],
        'event_date' => ['fechaColecta', 'eventDate'],
        'recorded_by' => ['colector', 'recordedBy'],
        'country' => ['country', 'country'],
        'state_province' => ['stateProvince', 'stateProvince'],
        'locality_name' => ['localidad', 'locality'],
        'locality_excel' => ['localidadVerbatim', 'verbatimLocality'],
        'locality_inec' => ['localityName', 'Locality (INEC)'],
        'locality_inec_reference' => ['localityNotes', 'INEC reference'],
        'decimal_latitude' => ['decimalLatitude', 'decimalLatitude'],
        'decimal_longitude' => ['decimalLongitude', 'decimalLongitude'],
        'coordinate_reference' => ['latLonMaxError', 'Coordinate reference'],
        'elevation_min_m' => ['elevationMinM', 'minimumElevationInMeters'],
        'elevation_max_m' => ['elevationMaxM', 'maximumElevationInMeters'],
        'sampling_protocol' => ['samplingProtocol', 'samplingProtocol'],
        'individual_count' => ['individualCount', 'individualCount'],
        'type_status' => ['typeStatus', 'typeStatus'],
        'disposition' => ['disposition', 'disposition'],
        'type_notes' => ['typeNotes', 'Type remarks'],
        'specimen_notes' => ['specimenNotes', 'occurrenceRemarks'],
        'occurrence_status' => ['occurrenceStatus', 'occurrenceStatus'],
        'caste' => ['caste', 'caste'],
        'life_stage' => ['lifeStage', 'lifeStage'],
        'fotografias' => ['fotografias', 'Photographs'],
        'ficha' => ['ficha', 'Record'],
    ];

    public function todas(): array
    {
        $registro = array_column(app(ResolverPrioridadColumnas::class)->aplicar('especimenes', RegistroColumnasEspecimen::todas()), null, 'clave');
        $config = Schema::hasTable('taxonomia.columnas_portal_publico')
            ? DB::table('taxonomia.columnas_portal_publico')->pluck('visible', 'clave')->all() : [];
        $columnas = [];
        foreach (self::MAPA as $campo => [$claveRegistro, $etiqueta]) {
            $meta = $registro[$claveRegistro] ?? [];
            $columnas[] = ['clave' => $campo, 'campo' => $campo, 'claveRegistro' => $claveRegistro, 'etiqueta' => $etiqueta,
                'grupo' => $meta['grupo'] ?? 'registro', 'prioridad' => $meta['prioridad'] ?? 'opcional',
                'visible' => array_key_exists($campo, $config) ? (bool) $config[$campo] : true];
        }
        return $columnas;
    }

    public function visibles(): array
    {
        return array_values(array_filter($this->todas(), static fn (array $columna): bool => $columna['visible']));
    }

    /** El panel interno autoriza al actor antes de llamar a esta operación. */
    public function actualizar(array $clavesVisibles): void
    {
        foreach ($clavesVisibles as $clave) {
            if (! is_string($clave) || ! array_key_exists($clave, self::MAPA)) {
                throw ValidationException::withMessages(['columnasPublicas' => 'Selecciona únicamente columnas públicas disponibles.']);
            }
        }
        $filas = [];
        foreach (self::MAPA as $clave => $_) $filas[] = ['clave' => $clave, 'visible' => in_array($clave, $clavesVisibles, true),
            'actualizado_por' => auth()->id(), 'created_at' => now(), 'updated_at' => now()];
        DB::transaction(fn () => DB::table('taxonomia.columnas_portal_publico')->upsert($filas, ['clave'], ['visible', 'actualizado_por', 'updated_at']));
    }
}
