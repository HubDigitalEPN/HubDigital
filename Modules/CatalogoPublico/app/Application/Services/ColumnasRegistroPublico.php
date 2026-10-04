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
        'occurrence_id' => ['codigoCatalogo', 'Código de catálogo'],
        'scientific_name' => ['taxonNombre', 'Identificación científica'],
        'event_date' => ['fechaColecta', 'Fecha original'],
        'recorded_by' => ['colector', 'Colector'],
        'country' => ['country', 'País'],
        'state_province' => ['stateProvince', 'Provincia'],
        'locality_name' => ['localityName', 'Localidad registrada'],
        'locality_excel' => ['localidadVerbatim', 'Localidad original'],
        'locality_inec' => ['localityName', 'Localidad INEC'],
        'locality_inec_reference' => ['localityNotes', 'Referencia INEC'],
        'decimal_latitude' => ['decimalLatitude', 'Latitud'],
        'decimal_longitude' => ['decimalLongitude', 'Longitud'],
        'coordinate_reference' => ['latLonMaxError', 'Referencia de coordenadas'],
        'elevation_min_m' => ['elevationMinM', 'Elevación mín. (m)'],
        'elevation_max_m' => ['elevationMaxM', 'Elevación máx. (m)'],
        'sampling_protocol' => ['samplingProtocol', 'Método de colecta'],
        'individual_count' => ['individualCount', 'Individuos'],
        'type_status' => ['typeStatus', 'Condición de tipo'],
        'disposition' => ['disposition', 'Disposición'],
        'type_notes' => ['typeNotes', 'Notas de tipo'],
        'specimen_notes' => ['specimenNotes', 'Notas del espécimen'],
        'occurrence_status' => ['occurrenceStatus', 'Estado'],
        'caste' => ['caste', 'Casta'],
        'life_stage' => ['lifeStage', 'Estadio'],
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
