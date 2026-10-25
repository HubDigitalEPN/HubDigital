<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\Adapters\PhpSpreadsheetGeneradorXlsxAdapter;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\LocalidadPublica;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Misma selección, dos perfiles; ningún perfil amplía los permisos de divulgación. */
final class ExportacionCatalogoExcel
{
    private const CAMPOS_DB = [
        'eventDate' => 'fecha_colecta', 'datecollectedEnd' => 'fecha_colecta_fin',
        'recordedBy' => 'colector', 'minimumElevationInMeters' => 'elevation_min_m',
        'maximumElevationInMeters' => 'elevation_max_m', 'verbatimLocality' => 'localidad_verbatim',
        'verbatimCoordinates' => 'coord_verbatim', '#actaRecepcion' => 'acta_recepcion',
        'cardexLiquidColCode' => 'cardex_liquid_collection_code', 'DNANotes' => 'dna_notes',
        'IPT_upload' => 'ipt_upload', 'recordCreatedBy,month-year' => 'record_created_by',
        'Responsible researcher or applicant for export' => 'responsible_researcher_export',
        'otherCatalogNumbers' => 'old_code', 'taxonRemarks' => 'taxonomic_notes',
        'verbatimEventDate' => 'fecha_verbatim', 'locationRemarks' => 'locality_notes',
    ];

    // Sólo campos que ya tienen una política explícita de divulgación. Los nuevos
    // campos y las columnas personalizadas permanecen reservados en el portal.
    private const VISIBILIDAD = [
        'occurrenceID' => 'occurrence_id', 'catalogNumber' => 'occurrence_id',
        'scientificName' => 'scientific_name', 'taxonID' => 'scientific_name',
        'kingdom' => 'scientific_name', 'phylum' => 'scientific_name', 'class' => 'scientific_name',
        'order' => 'scientific_name', 'suborder' => 'scientific_name', 'subfamily' => 'scientific_name',
        'tribe' => 'scientific_name', 'taxonRank' => 'scientific_name',
        'specificEpithet' => 'scientific_name', 'infraspecificEpithet' => 'scientific_name',
        'family' => 'family', 'genus' => 'genus', 'country' => 'country', 'countryCode' => 'country',
        'stateProvince' => 'state_province', 'locality' => 'locality_name',
        'verbatimLocality' => 'locality_name', 'municipality' => 'locality_name',
        'decimalLatitude' => 'decimal_latitude', 'decimalLongitude' => 'decimal_longitude',
        'minimumElevationInMeters' => 'elevation', 'maximumElevationInMeters' => 'elevation',
        'eventDate' => 'event_date', 'datecollectedEnd' => 'event_date', 'year' => 'event_date',
        'month' => 'event_date', 'day' => 'event_date', 'individualCount' => 'individual_count',
        'typeStatus' => 'type_status', 'disposition' => 'type_status',
        'typeNotes' => 'type_notes', 'specimenNotes' => 'specimen_notes',
        'samplingProtocol' => 'sampling_protocol', 'recordedBy' => 'recorded_by',
        'occurrenceStatus' => 'occurrence_status', 'caste' => 'caste', 'lifeStage' => 'life_stage',
        'geodeticDatum' => 'decimal_latitude', 'georeferenceRemarks' => 'decimal_latitude',
        'verbatimEventDate' => 'event_date',
    ];

    private const METADATOS = [
        'institutionID', 'collectionID', 'institutionCode', 'collectionCode', 'datasetName',
        'ownerInstitutionCode', 'rightsHolder', 'accessRights', 'license', 'basisOfRecord',
    ];

    public function descargar(array $ids, string $perfil, bool $publico = false): StreamedResponse
    {
        DescargasCatalogo::autorizar();
        abort_unless(in_array($perfil, ['catalogo', 'ipt'], true), 422);
        if (!$publico) abort_unless(auth()->user()?->esCurador() || auth()->user()?->esAdministrador(), 403);
        $def = app(ConfiguracionPlantillaCatalogo::class)->leer();
        $ipt = app(ConfiguracionIpt::class)->leer();
        $campos = $perfil === 'ipt' ? array_column($ipt['columnas'], 'origen') : array_column($def['columnas'], 'campo');
        $cabeceras = $perfil === 'ipt' ? array_column($ipt['columnas'], 'campo') : array_column($def['columnas'], 'cabecera');
        if ($perfil === 'catalogo') foreach (['catalogNumber', 'verbatimEventDate', 'georeferenceRemarks'] as $campo) if (!in_array($campo, $campos, true)) { $campos[] = $campo; $cabeceras[] = in_array($campo, $cabeceras, true) ? $campo.' (referencia)' : $campo; }
        $revision = [];
        $filas = $this->filas(array_values(array_unique($ids)), $campos, $publico, $perfil === 'ipt', $cabeceras, $revision);
        $escritor = app(PhpSpreadsheetGeneradorXlsxAdapter::class);
        // Se completa en disco antes de entregar la respuesta; un fallo no deja
        // al usuario con un ZIP parcialmente descargado.
        $bytes = $perfil === 'ipt' ? $escritor->generarIpt($cabeceras, $filas, $ipt['columnas'], $revision) : $escritor->generarCatalogo($cabeceras, $filas, $campos);
        return response()->streamDownload(fn () => print($bytes), $perfil === 'ipt' ? 'catalogo-ipt-gbif.xlsx' : 'catalogo-completo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store', 'X-Accel-Buffering' => 'no',
            'X-HubDigital-Export-Profile' => $perfil === 'ipt' ? 'ipt-configurable-v1' : 'catalogo-completo-v1',
            'X-HubDigital-Review-Required' => $revision !== [] ? 'true' : 'false',
        ]);
    }

    public function filas(array $ids, array $campos, bool $publico, bool $ipt, array $cabeceras = [], array &$revision = []): \Generator
    {
        if ($ids === []) return;
        $config = (array) DB::table('taxonomia.dataset_config')->first();
        $taxones = DB::table('taxonomia.taxones')->get()->keyBy('id')->all();
        $validos = array_fill_keys(CalidadDatoPublico::taxonesConLinajeValido($taxones), true);
        $mapper = new FilaCatalogoMapper;
        $duplicados = $ipt ? DB::table('taxonomia.especimenes')->whereNotNull('occurrence_id')
            ->groupBy('occurrence_id')->havingRaw('COUNT(*) > 1')->pluck('occurrence_id')->flip()->all() : [];
        foreach (array_chunk($ids, 500) as $lote) {
            $query = DB::table('taxonomia.especimenes as e')
                ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
                ->whereIn('e.id', $lote)->select('e.*', 'mc.sampling_protocol as protocolo_muestra');
            if ($publico) $query->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'e.id')
                ->where('ed.publicado', true)->addSelect(array_map(
                    static fn (string $permiso): string => 'ed.'.$permiso.'_visible', array_values(array_unique(self::VISIBILIDAD)),
                ));
            $filas = $query->get()->keyBy('id');
            $extras = $publico ? collect() : DB::table('taxonomia.valores_columnas_especimen')->whereIn('especimen_id', $lote)->get()->groupBy('especimen_id');
            $originales = $publico ? collect() : DB::table('taxonomia.importacion_filas_catalogo as f')
                ->join('taxonomia.importaciones_catalogo as i', 'i.id', '=', 'f.importacion_id')
                ->whereIn('f.especimen_id', $lote)->orderByDesc('i.created_at')->orderByDesc('f.id')
                ->get(['f.especimen_id', 'f.original'])->unique('especimen_id')->keyBy('especimen_id');
            foreach ($lote as $id) {
                if (!isset($filas[$id])) continue;
                $e = $filas[$id]; $db = (array) $e; $datos = [];
                $original = [];
                if (isset($originales[$id])) foreach (json_decode($originales[$id]->original, true, 512, JSON_THROW_ON_ERROR) as $c) $original[$c['cabecera']] = $c['valor'];
                $original = $mapper->normalizarClaves($original);
                foreach (array_unique([...$campos, ...PerfilIptGbif::COLUMNAS, 'locality', 'datecollectedEnd']) as $campo) {
                    if ($campo === '') continue;
                    $clave = self::CAMPOS_DB[$campo] ?? array_key_first($mapper->normalizarClaves([$campo => 'x']));
                    // Un valor vacío actual no se rellena con el dato histórico que
                    // el curador pudo haber borrado. Sólo se recuperan campos sin almacenamiento propio.
                    $datos[$campo] = array_key_exists($clave, $db) ? ($db[$clave] ?? '') : ($original[$clave] ?? '');
                }
                foreach (self::METADATOS as $campo) {
                    $clave = array_key_first($mapper->normalizarClaves([$campo => 'x']));
                    if (trim((string)($config[$clave] ?? '')) !== '') $datos[$campo] = $config[$clave];
                }
                $datos['occurrenceID'] = $e->occurrence_id ?? '';
                $datos['locality'] = LocalidadPublica::desdeFila($e) ?? '';
                $datos['samplingProtocol'] = trim($e->sampling_protocol ?? '') ?: ($e->protocolo_muestra ?? '');
                $datos = array_replace($datos, $this->taxonomia($e, $taxones, $validos));
                if (!$ipt && !$publico && $datos['scientificName'] === '') $datos['scientificName'] = $e->taxon_verbatim ?? '';
                $datos['verbatimEventDate'] = $e->fecha_verbatim ?? '';
                $datos['georeferenceRemarks'] = $e->lat_lon_max_error ?? '';
                $inicio = (string)($e->fecha_colecta ?? ''); $fin = (string)($e->fecha_colecta_fin ?? '');
                $datos['eventDate'] = PerfilIptGbif::fecha($inicio, $fin, $e->fecha_verbatim ?? null);
                preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/D', $datos['eventDate'], $partesFecha);
                foreach (['year' => 1, 'month' => 2, 'day' => 3] as $campo => $indice) $datos[$campo] = isset($partesFecha[$indice]) ? (int)$partesFecha[$indice] : '';
                foreach ($extras[$id] ?? [] as $extra) $datos[$extra->campo] = $extra->valor ?? '';
                if ($publico) {
                    foreach ($datos as $campo => $valor) {
                        $permiso = self::VISIBILIDAD[$campo] ?? null;
                        if (!in_array($campo, self::METADATOS, true) && ($permiso === null || !($e->{$permiso.'_visible'} ?? false))) $datos[$campo] = '';
                    }
                    // El par y su datum no pueden reconstruirse mediante campos verbatim.
                    if (!($e->decimal_latitude_visible && $e->decimal_longitude_visible)) $datos['decimalLatitude'] = $datos['decimalLongitude'] = $datos['geodeticDatum'] = $datos['georeferenceRemarks'] = '';
                }
                if ($ipt) {
                    if ($datos['occurrenceID'] !== '' && isset($duplicados[$datos['occurrenceID']])) $datos['occurrenceID'] .= ':'.$id;
                    if (!$publico && $datos['occurrenceID'] === '') $datos['occurrenceID'] = 'urn:uuid:'.$id;
                    $datos = array_replace($datos, PerfilIptGbif::fila($datos));
                }
                $valores = array_map(static fn (string $campo) => $datos[$campo] ?? '', $campos);
                if ($ipt) {
                    $exportada = array_combine($cabeceras ?: $campos, $valores);
                    $normalizada = PerfilIptGbif::fila($exportada);
                    foreach (['id', 'countryCode', 'language', 'occurrenceStatus'] as $campo) if (array_key_exists($campo, $exportada)) $exportada[$campo] = $normalizada[$campo];
                    $valores = array_values($exportada);
                    foreach (['occurrenceID', 'basisOfRecord', 'scientificName', 'eventDate'] as $campo) {
                        if (trim((string)($exportada[$campo] ?? '')) === '') $revision[$campo] = ($revision[$campo] ?? 0) + 1;
                    }
                }
                yield $valores;
            }
        }
    }

    private function taxonomia(object $e, array $taxones, array $validos): array
    {
        $rangos = ['reino' => 'kingdom', 'phylum' => 'phylum', 'clase' => 'class', 'orden' => 'order', 'suborden' => 'suborder', 'familia' => 'family', 'subfamilia' => 'subfamily', 'tribu' => 'tribe', 'genero' => 'genus', 'especie' => 'species', 'subespecie' => 'subspecies'];
        $t = $taxones[$e->taxon_id] ?? null; $ruta = []; $id = $e->taxon_id;
        while ($id && isset($taxones[$id]) && !isset($ruta[$id]) && count($ruta) < 30) { $ruta[$id] = $taxones[$id]; $id = $taxones[$id]->padre_id; }
        $datos = array_fill_keys(['taxonID', 'scientificName', 'taxonRank', 'specificEpithet', 'infraspecificEpithet', ...array_values($rangos)], '');
        if ($t === null || !isset($validos[$t->id])) return $datos;
        foreach ($ruta as $nodo) if (isset($rangos[$nodo->rango])) $datos[$rangos[$nodo->rango]] = $nodo->nombre_cientifico;
        $datos['taxonID'] = $t->id; $datos['scientificName'] = $t->nombre_cientifico;
        $datos['taxonRank'] = $rangos[$t->rango] ?? '';
        $datos['infraspecificEpithet'] = $t->epiteto_infraespecifico ?? '';
        $especie = $datos['species'];
        if ($especie !== '' && $datos['genus'] !== '' && str_starts_with($especie, $datos['genus'].' ')) {
            $datos['specificEpithet'] = explode(' ', substr($especie, strlen($datos['genus']) + 1))[0];
        }
        return $datos;
    }
}
