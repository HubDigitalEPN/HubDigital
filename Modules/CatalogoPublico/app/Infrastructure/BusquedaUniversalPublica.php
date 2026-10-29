<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Database\Query\Builder;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Busca valores del catálogo publicado; nunca inspecciona tablas de cuentas o expedientes. */
final class BusquedaUniversalPublica
{
    /** Cada columna está ligada a su permiso público, incluso al buscar y contar. */
    private const CAMPOS = [
        'occurrence_id_visible' => ['codigo_catalogo', 'occurrence_id'],
        'event_date_visible' => ['fecha_colecta', 'fecha_colecta_fin', 'fecha_verbatim'],
        'recorded_by_visible' => ['colector'],
        'country_visible' => ['country'], 'state_province_visible' => ['state_province'],
        'locality_name_visible' => ['locality_name', 'localidad', 'localidad_area', 'localidad_desglosada', 'localidad2', 'localidad3', 'localidad_verbatim', 'locality_notes', 'locality_code', 'municipality'],
        'decimal_latitude_visible' => ['decimal_latitude'], 'decimal_longitude_visible' => ['decimal_longitude'],
        'elevation_visible' => ['elevation_min_m', 'elevation_max_m', 'verbatim_elevation'],
        'sampling_protocol_visible' => ['sampling_protocol'], 'individual_count_visible' => ['individual_count'],
        'type_status_visible' => ['type_status', 'disposition'], 'type_notes_visible' => ['type_notes'],
        'specimen_notes_visible' => ['specimen_notes'], 'occurrence_status_visible' => ['occurrence_status'],
        'caste_visible' => ['caste'], 'life_stage_visible' => ['life_stage'],
    ];

    public static function terminos(string $texto): array
    {
        $texto = mb_substr(trim(preg_replace('/\s+/u', ' ', $texto) ?? ''), 0, 120);
        // Admite la fecha de pantalla y la fecha ISO, sin reinterpretar números sueltos.
        $texto = preg_replace_callback('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', static function (array $m): string {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? "$m[3]-$m[2]-$m[1]" : $m[0];
        }, $texto) ?? $texto;
        return array_slice(array_values(array_unique(preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [])), 0, 10);
    }

    public static function aplicar(Builder $query, ?string $texto, ?string $registroId = null): void
    {
        if ($registroId !== null) $query->where('te.id', $registroId);
        $terminos = self::terminos($texto ?? '');
        if ($terminos === []) return;
        $valores = [];
        foreach (self::CAMPOS as $permiso => $campos) {
            foreach ($campos as $campo) {
                $valores[] = "CASE WHEN ed.{$permiso} THEN te.{$campo}::text END";
            }
        }
        // El linaje se prepara una vez para todos los taxones, nunca una
        // consulta recursiva por cada ejemplar y cada palabra buscada.
        $linajes = "(WITH RECURSIVE l AS (
            SELECT id AS origen, id, padre_id, rango, nombre_cientifico FROM taxonomia.taxones
            UNION SELECT l.origen, t.id, t.padre_id, t.rango, t.nombre_cientifico FROM taxonomia.taxones t JOIN l ON t.id = l.padre_id
            ) SELECT origen AS id,
            string_agg(nombre_cientifico, ' ') FILTER (WHERE rango NOT IN ('familia','family','genero','genus')) AS nombres,
            string_agg(nombre_cientifico, ' ') FILTER (WHERE rango IN ('familia','family')) AS familias,
            string_agg(nombre_cientifico, ' ') FILTER (WHERE rango IN ('genero','genus')) AS generos
            FROM l GROUP BY origen) AS bu_taxones";
        $query->leftJoin(\Illuminate\Support\Facades\DB::raw($linajes), 'bu_taxones.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.muestras_colecta as bu_muestras', 'bu_muestras.id', '=', 'te.muestra_id');
        $valores[] = 'CASE WHEN ed.scientific_name_visible THEN bu_taxones.nombres END';
        $valores[] = 'CASE WHEN ed.family_visible THEN bu_taxones.familias END';
        $valores[] = 'CASE WHEN ed.genus_visible THEN bu_taxones.generos END';
        $valores[] = 'CASE WHEN ed.decimal_latitude_visible AND ed.decimal_longitude_visible THEN te.lat_lon_max_error::text END';
        $valores[] = 'CASE WHEN ed.sampling_protocol_visible THEN bu_muestras.sampling_protocol END';
        // Preparación, bioma y hábitat no tienen banderas por fila en el contrato vigente.
        foreach (['preparations', 'biome', 'habitat', 'microhabitat'] as $campo) $valores[] = "te.{$campo}::text";
        $documento = "concat_ws(' ', ".implode(', ', $valores).')';
        foreach ($terminos as $termino) {
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino).'%';
            $query->whereRaw("{$documento} ILIKE ?", [$patron]);
        }
    }

    public function opciones(FiltrosBusqueda $filtros, string $texto, int $pagina = 1, string $nivel = '', string $taxon = ''): array
    {
        if (trim($texto) === '') return ['filas' => [], 'hay_mas' => false, 'pagina' => 1];
        $query = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($filtros, $nivel, $taxon);
        self::aplicar($query, $texto);
        $filas = $query->leftJoin('taxonomia.taxones as tu', 'tu.id', '=', 'te.taxon_id')
            ->select('te.id')->selectRaw("CASE WHEN ed.occurrence_id_visible THEN COALESCE(te.occurrence_id, te.codigo_catalogo) END AS codigo,
                CASE WHEN ed.scientific_name_visible THEN tu.nombre_cientifico END AS taxon,
                CASE WHEN ed.event_date_visible THEN te.fecha_colecta::text END AS fecha,
                CASE WHEN ed.locality_name_visible THEN COALESCE(te.locality_name, te.localidad) END AS localidad")
            ->orderBy('te.fila_origen_excel')->orderBy('te.id')->offset(($pagina - 1) * 30)->limit(31)->get()->all();
        return ['filas' => array_map(static fn ($f): array => (array) $f, array_slice($filas, 0, 30)),
            'hay_mas' => count($filas) > 30, 'pagina' => $pagina];
    }
}
