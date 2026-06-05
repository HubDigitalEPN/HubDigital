<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/**
 * Catálogo de columnas del espécimen con su clasificación de prioridad.
 *
 * Fuente de verdad para la UI: cada columna declara su clave, etiqueta legible,
 * grupo lógico y nivel de prioridad (crítica/recomendada/opcional). Esto
 * permite que las pantallas pinten badges de color y que el curador decida
 * qué columnas ver/ocultar/reordenar, sin que se le escapen las requeridas.
 *
 * Niveles:
 *  - **critica**: sin este campo el espécimen no es publicable a GBIF.
 *    Coincide con CriteriosCalidadGbif: taxón identificado y coordenadas.
 *  - **recomendada**: GBIF recomienda altamente para calidad de datos
 *    (locality, country, eventDate, recordedBy).
 *  - **opcional**: descriptiva o complementaria.
 */
final class RegistroColumnasEspecimen
{
    public const PRIORIDAD_CRITICA = 'critica';

    public const PRIORIDAD_RECOMENDADA = 'recomendada';

    public const PRIORIDAD_OPCIONAL = 'opcional';

    public const GRUPO_IDENTIFICACION = 'identificacion';

    public const GRUPO_TAXONOMIA = 'taxonomia';

    public const GRUPO_LOCALIDAD = 'localidad';

    public const GRUPO_FECHA = 'fecha';

    public const GRUPO_REGISTRO = 'registro';

    public const GRUPO_ATRIBUTOS = 'atributos';

    public const GRUPO_REVISION = 'revision';

    /**
     * @return list<array{
     *   clave: string,
     *   etiqueta: string,
     *   grupo: string,
     *   prioridad: string,
     *   visiblePorDefecto: bool
     * }>
     */
    public static function todas(): array
    {
        return [
            // Identificación
            self::col('codigoCatalogo', 'Código catálogo', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_RECOMENDADA, true),
            self::col('occurrenceId', 'occurrenceID', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_RECOMENDADA, false),
            self::col('catalogNumber', 'catalogNumber', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false),
            self::col('oldCode', 'oldCode (muestra)', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false),
            self::col('cardexLiquidCollectionCode', 'Cardex líquido', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false),
            self::col('filaOrigenExcel', 'Fila origen Excel', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false),

            // Taxonomía
            self::col('taxonNombre', 'Taxón (científico)', self::GRUPO_TAXONOMIA, self::PRIORIDAD_CRITICA, true),
            self::col('taxonVerbatim', 'Taxón verbatim', self::GRUPO_TAXONOMIA, self::PRIORIDAD_RECOMENDADA, false),

            // Localidad
            self::col('localidad', 'Localidad', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, true),
            self::col('localidadVerbatim', 'Localidad verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, false),
            self::col('localityName', 'Locality name', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('country', 'País', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, false),
            self::col('stateProvince', 'Provincia', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('municipality', 'Cantón/Municipio', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('decimalLatitude', 'Latitud', self::GRUPO_LOCALIDAD, self::PRIORIDAD_CRITICA, true),
            self::col('decimalLongitude', 'Longitud', self::GRUPO_LOCALIDAD, self::PRIORIDAD_CRITICA, true),
            self::col('coordVerbatim', 'Coord verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('geodeticDatum', 'Datum', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('elevationMinM', 'Elevación min (m)', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),
            self::col('elevationMaxM', 'Elevación max (m)', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false),

            // Fecha
            self::col('fechaColecta', 'Fecha colecta', self::GRUPO_FECHA, self::PRIORIDAD_RECOMENDADA, true),
            self::col('fechaColectaFin', 'Fecha colecta fin', self::GRUPO_FECHA, self::PRIORIDAD_OPCIONAL, false),
            self::col('fechaVerbatim', 'Fecha verbatim', self::GRUPO_FECHA, self::PRIORIDAD_RECOMENDADA, false),

            // Registro
            self::col('colector', 'Colector (recordedBy)', self::GRUPO_REGISTRO, self::PRIORIDAD_RECOMENDADA, true),
            self::col('individualCount', 'Individual count', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('individualCountVerbatim', 'Count verbatim', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('preparations', 'Preparación', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('disposition', 'Disposición', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('occurrenceStatus', 'Occurrence status', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('actaRecepcion', '#Acta recepción', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),
            self::col('estado', 'Estado físico', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false),

            // Atributos
            self::col('sex', 'Sexo', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('lifeStage', 'Estadio', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('caste', 'Casta', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('typeStatus', 'Type status', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_RECOMENDADA, false),
            self::col('biome', 'Bioma', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('habitat', 'Hábitat', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('microhabitat', 'Microhábitat', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('biogeographicRegion', 'Región biogeográfica', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),
            self::col('endemic', 'Endémico', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false),

            // Revisión (siempre crítica para flujo curador post-import)
            self::col('estadoRevision', 'Estado revisión', self::GRUPO_REVISION, self::PRIORIDAD_CRITICA, true),
            self::col('motivoRevision', 'Motivo revisión', self::GRUPO_REVISION, self::PRIORIDAD_CRITICA, true),
        ];
    }

    /** @return string[] Claves visibles por defecto, en el orden del registro. */
    public static function clavesVisiblesPorDefecto(): array
    {
        return array_values(array_map(
            fn ($c) => $c['clave'],
            array_filter(self::todas(), fn ($c) => $c['visiblePorDefecto'])
        ));
    }

    /** @return array<string, string> Mapa clave → prioridad (critica/recomendada/opcional). */
    public static function prioridadesPorClave(): array
    {
        $out = [];
        foreach (self::todas() as $col) {
            $out[$col['clave']] = $col['prioridad'];
        }

        return $out;
    }

    /**
     * @return array{
     *   clave: string,
     *   etiqueta: string,
     *   grupo: string,
     *   prioridad: string,
     *   visiblePorDefecto: bool
     * }
     */
    private static function col(
        string $clave,
        string $etiqueta,
        string $grupo,
        string $prioridad,
        bool $visiblePorDefecto,
    ): array {
        return compact('clave', 'etiqueta', 'grupo', 'prioridad', 'visiblePorDefecto');
    }
}
