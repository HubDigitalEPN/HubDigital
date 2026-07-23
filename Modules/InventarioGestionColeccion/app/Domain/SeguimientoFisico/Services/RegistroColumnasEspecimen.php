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
     *   visiblePorDefecto: bool,
     *   nombreDwC: string|null
     * }>
     */
    public static function todas(): array
    {
        return [
            // Identificación
            self::col('codigoCatalogo', 'Código catálogo', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_RECOMENDADA, true, null),
            self::col('occurrenceId', 'occurrenceID', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_RECOMENDADA, false, 'occurrenceID'),
            self::col('catalogNumber', 'catalogNumber', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false, 'catalogNumber'),
            self::col('oldCode', 'oldCode (muestra)', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('cardexLiquidCollectionCode', 'Cardex líquido', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('filaOrigenExcel', 'Fila origen Excel', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_OPCIONAL, false, null),

            // Taxonomía
            self::col('taxonNombre', 'Taxón (científico)', self::GRUPO_TAXONOMIA, self::PRIORIDAD_CRITICA, true, 'scientificName'),
            self::col('taxonVerbatim', 'Taxón verbatim', self::GRUPO_TAXONOMIA, self::PRIORIDAD_RECOMENDADA, false, 'verbatimIdentification'),

            // Localidad
            self::col('localidad', 'Localidad', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, true, 'verbatimLocality'),
            self::col('localidadVerbatim', 'Localidad verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, false, null),
            self::col('localityName', 'Locality name', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'locality'),
            self::col('country', 'País', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, false, 'country'),
            self::col('stateProvince', 'Provincia', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'stateProvince'),
            self::col('municipality', 'Cantón/Municipio', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'municipality'),
            self::col('decimalLatitude', 'Latitud', self::GRUPO_LOCALIDAD, self::PRIORIDAD_CRITICA, true, 'decimalLatitude'),
            self::col('decimalLongitude', 'Longitud', self::GRUPO_LOCALIDAD, self::PRIORIDAD_CRITICA, true, 'decimalLongitude'),
            self::col('coordVerbatim', 'Coord verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimCoordinates'),
            self::col('geodeticDatum', 'Datum', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'geodeticDatum'),
            self::col('elevationMinM', 'Elevación min (m)', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'minimumElevationInMeters'),
            self::col('elevationMaxM', 'Elevación max (m)', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'maximumElevationInMeters'),

            // Fecha
            self::col('fechaColecta', 'Fecha colecta', self::GRUPO_FECHA, self::PRIORIDAD_RECOMENDADA, true, 'eventDate'),
            self::col('fechaColectaFin', 'Fecha colecta fin', self::GRUPO_FECHA, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('fechaVerbatim', 'Fecha verbatim', self::GRUPO_FECHA, self::PRIORIDAD_RECOMENDADA, false, 'verbatimEventDate'),

            // Registro
            self::col('colector', 'Colector (recordedBy)', self::GRUPO_REGISTRO, self::PRIORIDAD_RECOMENDADA, true, 'recordedBy'),
            self::col('individualCount', 'Individual count', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, 'individualCount'),
            self::col('individualCountVerbatim', 'Count verbatim', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('preparations', 'Preparación', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, 'preparations'),
            self::col('disposition', 'Disposición', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, 'disposition'),
            self::col('occurrenceStatus', 'Occurrence status', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, 'occurrenceStatus'),
            self::col('actaRecepcion', '#Acta recepción', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('estado', 'Estado físico', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),

            // Atributos
            self::col('sex', 'Sexo', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, 'sex'),
            self::col('lifeStage', 'Estadio', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, 'lifeStage'),
            self::col('caste', 'Casta', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('typeStatus', 'Type status', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_RECOMENDADA, false, 'typeStatus'),
            self::col('biome', 'Bioma', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('habitat', 'Hábitat', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, 'habitat'),
            self::col('microhabitat', 'Microhábitat', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('biogeographicRegion', 'Región biogeográfica', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('endemic', 'Endémico', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),

            // Revisión (siempre crítica para flujo curador post-import)
            self::col('estadoRevision', 'Estado revisión', self::GRUPO_REVISION, self::PRIORIDAD_CRITICA, true, null),
            self::col('motivoRevision', 'Motivo revisión', self::GRUPO_REVISION, self::PRIORIDAD_CRITICA, true, null),

            // ── Plantilla v2 ──────────────────────────────────────────────────
            // Clasificación del documento: obligatorio → crítica (rojo),
            // opcional-si-aplica → recomendada (ámbar), opcional/uso-interno → opcional.

            // Obligatorias (plantilla)
            self::col('recordNumber', 'Record number (código campo)', self::GRUPO_IDENTIFICACION, self::PRIORIDAD_CRITICA, false, 'recordNumber'),
            self::col('origin', 'Origen (research/consulting)', self::GRUPO_REGISTRO, self::PRIORIDAD_CRITICA, false, null),
            self::col('identifiedBy', 'Identificado por', self::GRUPO_TAXONOMIA, self::PRIORIDAD_CRITICA, false, 'identifiedBy'),
            self::col('dateDetermined', 'Fecha de determinación', self::GRUPO_TAXONOMIA, self::PRIORIDAD_CRITICA, false, 'dateIdentified'),
            self::col('researchPermit', 'Permiso de investigación', self::GRUPO_REGISTRO, self::PRIORIDAD_CRITICA, false, null),
            self::col('transportPermit', 'Permiso de transporte', self::GRUPO_REGISTRO, self::PRIORIDAD_CRITICA, false, null),

            // Opcional-si-aplica (plantilla)
            self::col('exportImportAuthorization', 'Autorización export/import', self::GRUPO_REGISTRO, self::PRIORIDAD_RECOMENDADA, false, null),
            self::col('scientificNameAuthorship', 'Autor del nombre', self::GRUPO_TAXONOMIA, self::PRIORIDAD_RECOMENDADA, false, 'scientificNameAuthorship'),
            self::col('latLonMaxError', 'Error coord (m)', self::GRUPO_LOCALIDAD, self::PRIORIDAD_RECOMENDADA, false, 'coordinateUncertaintyInMeters'),

            // Opcionales (plantilla)
            self::col('clade', 'Clado', self::GRUPO_TAXONOMIA, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('identificationQualifier', 'Calificador identificación', self::GRUPO_TAXONOMIA, self::PRIORIDAD_OPCIONAL, false, 'identificationQualifier'),
            self::col('identificationRemarks', 'Notas de identificación', self::GRUPO_TAXONOMIA, self::PRIORIDAD_OPCIONAL, false, 'identificationRemarks'),
            self::col('vernacularName', 'Nombre común', self::GRUPO_TAXONOMIA, self::PRIORIDAD_OPCIONAL, false, 'vernacularName'),
            self::col('typeNotes', 'Notas de tipo', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('continent', 'Continente', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'continent'),
            self::col('countryCode', 'Código país', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'countryCode'),
            self::col('localityNotes', 'Notas de localidad', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('localityCode', 'Código de localidad', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('elevationMaxError', 'Error elevación', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('verbatimElevation', 'Elevación verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimElevation'),
            self::col('verbatimDepth', 'Profundidad verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimDepth'),
            self::col('verbatimLatitude', 'Latitud verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimLatitude'),
            self::col('verbatimLongitude', 'Longitud verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimLongitude'),
            self::col('verbatimCoordinateSystem', 'Sistema coord verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimCoordinateSystem'),
            self::col('verbatimSrs', 'SRS verbatim', self::GRUPO_LOCALIDAD, self::PRIORIDAD_OPCIONAL, false, 'verbatimSRS'),
            self::col('informationWithheld', 'Información reservada', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, 'informationWithheld'),
            self::col('priorOwner', 'Propietario previo', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('locatedAt', 'Ubicado en', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),

            // Uso interno (plantilla)
            self::col('iptUpload', 'Subido a IPT', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('recordCreatedBy', 'Registrado por', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('responsibleResearcherExport', 'Responsable de exportación', self::GRUPO_REGISTRO, self::PRIORIDAD_OPCIONAL, false, null),
            self::col('endemicVerbatim', 'Endémico (texto)', self::GRUPO_ATRIBUTOS, self::PRIORIDAD_OPCIONAL, false, null),
        ];
    }

    /** @return array<string, string> Mapa clave interna → nombre DwC (solo columnas con DwC no nulo). */
    public static function mapaClaveADwC(): array
    {
        $mapa = [];
        foreach (self::todas() as $col) {
            if ($col['nombreDwC'] !== null) {
                $mapa[$col['clave']] = $col['nombreDwC'];
            }
        }

        return $mapa;
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
     *   visiblePorDefecto: bool,
     *   nombreDwC: string|null
     * }
     */
    private static function col(
        string $clave,
        string $etiqueta,
        string $grupo,
        string $prioridad,
        bool $visiblePorDefecto,
        ?string $nombreDwC,
    ): array {
        return compact('clave', 'etiqueta', 'grupo', 'prioridad', 'visiblePorDefecto', 'nombreDwC');
    }
}
