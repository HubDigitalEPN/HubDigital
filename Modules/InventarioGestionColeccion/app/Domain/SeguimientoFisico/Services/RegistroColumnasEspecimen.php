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

    public const TIPO_TEXTO = 'texto';

    public const TIPO_TEXTO_LARGO = 'texto_largo';

    public const TIPO_BOOLEANO = 'booleano';

    /**
     * Campos que pueden fijarse en bloque sobre una selección de especímenes.
     *
     * Es una lista blanca CERRADA, y la exclusión importa tanto como la
     * inclusión. Quedan fuera a propósito:
     *
     *  - **Identificadores** (`codigoCatalogo`, `occurrenceId`, `catalogNumber`,
     *    `oldCode`…): identifican una pieza concreta. Fijar el mismo valor en
     *    varias filas no es una corrección, es perder de qué ejemplar habla cada
     *    registro. Además alimentan `taxonomia.especimen_identificadores`.
     *  - **Claves foráneas** (`taxonId`, `localidadId`, `muestraId`…): se enlazan
     *    desde las bandejas de revisión, que validan que el destino existe.
     *  - **Campos verbatim** (`taxonVerbatim`, `localidadVerbatim`,
     *    `fechaVerbatim`, `coordVerbatim`…): son el dato crudo del Excel, la
     *    única prueba de qué decía la etiqueta original. Sobrescribirlos borra
     *    la trazabilidad del import y no se puede reconstruir.
     *  - **Coordenadas, fechas de colecta e individualCount**: son hechos
     *    medidos de cada ejemplar, no metadatos repetibles. Se corrigen uno a
     *    uno en el modal.
     *  - **`estadoRevision` / `motivoRevision`**: tienen su propio flujo, que
     *    mantiene coherente la pareja estado + motivo. Pisar `motivoRevision` en
     *    bloque además apagaría los avisos que {@see ClasificadorMotivoRevision}
     *    deriva de ese texto.
     *
     * Lo que queda es justo lo que un curador rellena repetido: procedencia
     * administrativa, atributos del ejemplar y notas.
     *
     * `maximo` refleja el `varchar` real de la columna para poder avisar ANTES
     * de escribir, en vez de dejar que Postgres aborte el lote entero.
     *
     * @return array<string, array{tipo: string, maximo: int|null, admiteVacio: bool}>
     */
    public static function camposEditablesEnMasa(): array
    {
        return [
            // Procedencia geográfica normalizada (el crudo vive en los verbatim)
            'localidad' => self::campoEdit(self::TIPO_TEXTO, 255, admiteVacio: false),
            'localityName' => self::campoEdit(self::TIPO_TEXTO, 500),
            'localityCode' => self::campoEdit(self::TIPO_TEXTO, 255),
            'localityNotes' => self::campoEdit(self::TIPO_TEXTO_LARGO, null),
            'country' => self::campoEdit(self::TIPO_TEXTO, 120),
            'countryCode' => self::campoEdit(self::TIPO_TEXTO, 255),
            'continent' => self::campoEdit(self::TIPO_TEXTO, 255),
            'stateProvince' => self::campoEdit(self::TIPO_TEXTO, 120),
            'municipality' => self::campoEdit(self::TIPO_TEXTO, 120),
            'geodeticDatum' => self::campoEdit(self::TIPO_TEXTO, 60),
            'latLonMaxError' => self::campoEdit(self::TIPO_TEXTO, 255),
            'elevationMaxError' => self::campoEdit(self::TIPO_TEXTO, 255),

            // Quién y cómo se recolectó / determinó
            'colector' => self::campoEdit(self::TIPO_TEXTO, 255, admiteVacio: false),
            'identifiedBy' => self::campoEdit(self::TIPO_TEXTO, 255),
            'dateDetermined' => self::campoEdit(self::TIPO_TEXTO, 255),
            'scientificNameAuthorship' => self::campoEdit(self::TIPO_TEXTO, 255),
            'identificationQualifier' => self::campoEdit(self::TIPO_TEXTO, 255),
            'identificationRemarks' => self::campoEdit(self::TIPO_TEXTO_LARGO, null),
            'clade' => self::campoEdit(self::TIPO_TEXTO, 255),
            'vernacularName' => self::campoEdit(self::TIPO_TEXTO, 255),

            // Atributos del ejemplar
            'sex' => self::campoEdit(self::TIPO_TEXTO, 40),
            'lifeStage' => self::campoEdit(self::TIPO_TEXTO, 40),
            'caste' => self::campoEdit(self::TIPO_TEXTO, 60),
            'typeStatus' => self::campoEdit(self::TIPO_TEXTO, 120),
            'typeNotes' => self::campoEdit(self::TIPO_TEXTO_LARGO, null),
            'endemic' => self::campoEdit(self::TIPO_BOOLEANO, null),
            'biome' => self::campoEdit(self::TIPO_TEXTO, 120),
            'habitat' => self::campoEdit(self::TIPO_TEXTO, 255),
            'microhabitat' => self::campoEdit(self::TIPO_TEXTO, 255),
            'biogeographicRegion' => self::campoEdit(self::TIPO_TEXTO, 120),

            // Gestión curatorial y administrativa
            'preparations' => self::campoEdit(self::TIPO_TEXTO, 120),
            'disposition' => self::campoEdit(self::TIPO_TEXTO, 120),
            'occurrenceStatus' => self::campoEdit(self::TIPO_TEXTO, 120),
            'actaRecepcion' => self::campoEdit(self::TIPO_TEXTO, 120),
            'origin' => self::campoEdit(self::TIPO_TEXTO, 255),
            'researchPermit' => self::campoEdit(self::TIPO_TEXTO, 255),
            'transportPermit' => self::campoEdit(self::TIPO_TEXTO, 255),
            'exportImportAuthorization' => self::campoEdit(self::TIPO_TEXTO, 255),
            'informationWithheld' => self::campoEdit(self::TIPO_TEXTO_LARGO, null),
            'priorOwner' => self::campoEdit(self::TIPO_TEXTO, 255),
            'locatedAt' => self::campoEdit(self::TIPO_TEXTO, 255),
            'iptUpload' => self::campoEdit(self::TIPO_TEXTO, 255),
            'recordCreatedBy' => self::campoEdit(self::TIPO_TEXTO, 255),
            'responsibleResearcherExport' => self::campoEdit(self::TIPO_TEXTO, 255),
        ];
    }

    /** @return string[] */
    public static function clavesEditablesEnMasa(): array
    {
        return array_keys(self::camposEditablesEnMasa());
    }

    /**
     * Solo los campos de texto: buscar y reemplazar no tiene sentido sobre un
     * booleano, y aplicarlo ahí solo produciría valores inválidos.
     *
     * @return string[]
     */
    public static function clavesEditablesDeTexto(): array
    {
        return array_keys(array_filter(
            self::camposEditablesEnMasa(),
            fn (array $c) => in_array($c['tipo'], [self::TIPO_TEXTO, self::TIPO_TEXTO_LARGO], true),
        ));
    }

    public static function esEditableEnMasa(string $clave): bool
    {
        return array_key_exists($clave, self::camposEditablesEnMasa());
    }

    /** @return array{tipo: string, maximo: int|null, admiteVacio: bool}|null */
    public static function campoEditable(string $clave): ?array
    {
        return self::camposEditablesEnMasa()[$clave] ?? null;
    }

    /** @return array{tipo: string, maximo: int|null, admiteVacio: bool} */
    private static function campoEdit(string $tipo, ?int $maximo, bool $admiteVacio = true): array
    {
        return compact('tipo', 'maximo', 'admiteVacio');
    }

    /**
     * Claves que la tabla del catálogo puede ordenar.
     *
     * Solo columnas que existen tal cual en `taxonomia.especimenes`: la capa de
     * infraestructura convierte la clave camelCase a snake_case y la usa como
     * `ORDER BY`. Al ser una lista blanca cerrada, ningún texto del usuario llega
     * a la consulta. `taxonNombre` queda fuera a propósito (se resuelve contra el
     * repositorio de taxones, no es una columna del espécimen): para ordenar por
     * taxonomía está `taxonVerbatim`.
     *
     * @return string[]
     */
    public static function clavesOrdenables(): array
    {
        return [
            // Identificación
            'codigoCatalogo', 'occurrenceId', 'catalogNumber', 'oldCode',
            'cardexLiquidCollectionCode', 'recordNumber', 'filaOrigenExcel',
            // Taxonomía
            'taxonVerbatim', 'clade', 'vernacularName', 'scientificNameAuthorship',
            'identifiedBy', 'dateDetermined',
            // Localidad
            'localidad', 'localidadVerbatim', 'localityName', 'country', 'countryCode',
            'continent', 'stateProvince', 'municipality', 'decimalLatitude',
            'decimalLongitude', 'elevationMinM', 'elevationMaxM',
            // Fecha
            'fechaColecta', 'fechaColectaFin', 'fechaVerbatim',
            // Registro
            'colector', 'individualCount', 'preparations', 'disposition',
            'occurrenceStatus', 'actaRecepcion', 'estado', 'origin',
            // Atributos
            'sex', 'lifeStage', 'caste', 'typeStatus', 'biome', 'habitat',
            'microhabitat', 'biogeographicRegion', 'endemic',
            // Revisión
            'estadoRevision', 'motivoRevision',
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
