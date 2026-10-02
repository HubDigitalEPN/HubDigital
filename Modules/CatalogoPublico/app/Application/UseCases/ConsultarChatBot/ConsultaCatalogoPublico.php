<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Lista blanca de consultas determinísticas sobre ejemplares divulgables. */
final class ConsultaCatalogoPublico
{
    public function __construct(private readonly DetectorEntidadesChat $detector, private readonly TextoChat $texto) {}

    public function responder(string $pregunta, array $contexto = []): ?array
    {
        if (! config('chatbot.specimen_search', true)) {
            return null;
        }
        $normal = $this->texto->normalizar($pregunta);
        $retirarFiltro = $this->filtroPorRetirar($normal);
        if ($retirarFiltro === 'no_soportado') {
            return $this->aclaracion('No puedo retirar ese filtro del chat de forma confirmada. Indica uno de estos criterios: taxón, código, provincia, país, localidad, mes, fechas, elevación, coordenadas o identificación. Para otros filtros, usa el formulario del catálogo.', $contexto);
        }
        $camposPorFiltro = ['taxon' => ['taxon'], 'codigo' => ['codigo'], 'provincia' => ['provincia'], 'pais' => ['pais'],
            'localidad' => ['localidad'], 'mes' => ['mes'], 'fechas' => ['desde', 'hasta', 'fecha_precision'],
            'desde' => ['desde', 'fecha_precision'], 'hasta' => ['hasta', 'fecha_precision'],
            'elevacion' => ['elev_desde', 'elev_hasta'], 'ubicacion' => ['ubicacion'], 'identificacion' => ['identificacion']];
        if ($retirarFiltro !== null && array_intersect(array_keys($contexto), $camposPorFiltro[$retirarFiltro]) === []) {
            return $this->aclaracion('La consulta anterior no tiene ese filtro aplicado. Indica qué criterio quieres retirar o escribe una nueva consulta.', $contexto);
        }
        $coleccionExplicita = (bool) preg_match('/\b(?:cuant[oa]s?|numero|total)\b/', $normal)
            && preg_match('/\b(?:registros|especimenes|ejemplares|especies)\b/', $normal)
            && preg_match('/\b(?:en|de)\s+(?:toda\s+)?(?:(?:la\s+)?coleccion|(?:el\s+)?catalogo)\b/', $normal)
            && ! preg_match('/\b(?:esa especie|ese taxon|esos registros|esos ejemplares|estos registros)\b/', $normal);
        $correction = (bool) preg_match('/^(perdon|corrijo|quise decir|queria decir|no )\b/', $normal);
        $followup = (bool) preg_match('/^(?:y\s+de\s+|y\s+)?cuantos?\b|^(?:y|solo|dame solo|quita|quitar|elimina)\s+|^donde\s+los\s+encontraron\b|\b(?:esa especie|ese taxon|esos registros|de esos dos registros)\b/', $normal);
        $queryText = $pregunta;
        $geografiasNegadas = [];
        if ($correction && str_starts_with($normal, 'no ')) {
            // Conserva mayúsculas del nombre o localidad: el detector las usa para acotar entidades.
            $clauses = preg_split('/\s*(?:[,;.]|\bpero\b|\bsino\b)\s*/iu', rtrim($pregunta, '.')) ?: [];
            if (count($clauses) > 1) {
                $queryText = (string) array_pop($clauses);
                foreach ($clauses as $clausula) {
                    $geografiasNegadas = array_merge($geografiasNegadas, $this->geografiasNegadas($clausula, $contexto));
                }
            }
        }
        $entities = $retirarFiltro === null ? $this->detector->extraer($queryText) : [];
        if (($correction || $followup || $retirarFiltro !== null) && ! $coleccionExplicita && $contexto !== []) {
            $previous = array_intersect_key($contexto, array_flip(['taxon', 'provincia', 'localidad', 'pais', 'codigo', 'mes', 'desde', 'hasta', 'fecha_precision', 'ubicacion', 'identificacion', 'elev_desde', 'elev_hasta']));
            if (isset($entities['taxon']) || isset($entities['codigo'])) unset($previous['taxon'], $previous['codigo']);
            foreach ($geografiasNegadas as $geografia) unset($previous[$geografia]);
            foreach (['provincia', 'localidad', 'pais'] as $geografia) {
                if (isset($entities[$geografia])) unset($previous[$geografia]);
            }
            $quitarMes = $retirarFiltro === 'mes';
            if ((isset($entities['mes']) || $quitarMes) && ! isset($entities['desde']) && ! isset($entities['hasta'])
                && ($previous['fecha_precision'] ?? 'mes') === 'mes'
                && isset($previous['mes'], $previous['desde'], $previous['hasta'])) {
                $inicio = \DateTimeImmutable::createFromFormat('!Y-m-d', $previous['desde']);
                // Un mes con año genera límites de calendario; cambiarlo conserva el año pedido.
                if ($inicio !== false && $inicio->format('Y-m-d') === $previous['desde']
                    && $inicio->format('d') === '01' && $inicio->format('Y-m-t') === $previous['hasta']
                    && (int) $inicio->format('n') === (int) $previous['mes']) {
                    $nuevoInicio = $inicio->setDate((int) $inicio->format('Y'), $quitarMes ? 1 : (int) $entities['mes'], 1);
                    $previous['desde'] = $nuevoInicio->format('Y-m-d');
                    $previous['hasta'] = $quitarMes ? $inicio->format('Y').'-12-31' : $nuevoInicio->format('Y-m-t');
                    $previous['fecha_precision'] = $quitarMes ? 'explicita' : 'mes';
                }
            }
            if (isset($entities['mes'])) unset($previous['mes']);
            if (isset($entities['desde']) || isset($entities['hasta'])) unset($previous['desde'], $previous['hasta'], $previous['fecha_precision']);
            if (isset($entities['elev_desde']) || isset($entities['elev_hasta'])) unset($previous['elev_desde'], $previous['elev_hasta']);
            if ($quitarMes) unset($previous['mes']);
            if ($retirarFiltro !== null && ! $quitarMes) {
                foreach ($camposPorFiltro[$retirarFiltro] as $campo) unset($previous[$campo]);
            }
            $entities = array_replace($previous, $entities);
        }
        if (preg_match('/\b(primero|primer|segundo|tercero|ultimo)\b.*\b(?:registros?|codigos?|de esos|de los)\b/', $normal, $referencia)) {
            $codigos = array_values(array_filter(array_map('trim', explode(',', (string) ($contexto['codigo'] ?? '')))));
            $posicion = ['primero' => 0, 'primer' => 0, 'segundo' => 1, 'tercero' => 2, 'ultimo' => count($codigos) - 1][$referencia[1]];
            if (! isset($codigos[$posicion])) {
                return $this->aclaracion('No tengo una lista de códigos suficiente para resolver esa posición. Indica el código del registro que quieres consultar.', $contexto);
            }
            $entities['codigo'] = $codigos[$posicion];
            unset($entities['taxon']);
        }
        if (isset($entities['error_consulta'])) return $this->aclaracion($entities['error_consulta'], $contexto);
        if (preg_match('/\bo\b/', $normal)) {
            return $this->aclaracion('La unión de alternativas con «o» no está disponible en esta consulta. No he calculado un conteo parcial. Elige una alternativa o consulta cada una por separado.', $contexto);
        }
        if (preg_match('/\b(?:hembras?|machos?|femenin[oa]s?|masculin[oa]s?|sexo)\b/', $normal)) {
            return $this->aclaracion('El filtro por sexo no está disponible en esta consulta. No he calculado un conteo parcial; indica otros criterios o consulta al laboratorio sobre ese dato.', $contexto);
        }
        if (preg_match('/\b(?:preservad[oa]s?|conservad[oa]s?|alcohol|etanol|formol)\b/', $normal)) {
            return $this->aclaracion('No puedo traducir esa condición de preservación a un filtro confirmado. No he calculado un conteo parcial. Revisa Preparación en el catálogo y elige el valor disponible.', $contexto);
        }
        $options = [['label' => 'Abrir catálogo para ver más', 'url' => route('portal.catalogo', $this->parametros($entities))]];
        if (preg_match('/\b(?:sin|no tienen|no tengan)\s+coordenadas\b|\b(?:excepto|excluye|excluir)\b/', $normal)) {
            return $this->aclaracion('La exclusión solicitada no está disponible en los filtros de esta consulta. No he calculado un conteo parcial. Puedes reformular con los criterios que deseas incluir.', $contexto);
        }
        if (isset($entities['codigo'])) {
            return $this->porCodigo($entities, $options);
        }
        if ($entities === [] && preg_match('/(?:que|cuales)\s+familias\s+(?:tienen|hay|existen)/', $normal)) {
            return $this->familias($options);
        }
        if (isset($entities['taxon']) && preg_match('/\bgeneros\b/', $normal)) {
            return $this->generos($entities, $options);
        }
        if ($entities === [] && $retirarFiltro === null && ! $coleccionExplicita && ! preg_match('/\bcuant[oa]s?\s+(?:registros|especimenes|ejemplares|especies)\s+(?:public[oa]s?\s+)?(?:tienen|hay|estan)/', $normal)) {
            return null;
        }
        $query = $this->seleccion($entities);
        if (preg_match('/^donde\s+los\s+encontraron\b/', $normal) && $entities !== []) {
            return $this->localidades($query, $entities, $options);
        }
        $total = (clone $query)->count('e.id');
        $label = implode(' en ', array_values(array_filter([$entities['taxon'] ?? null, $entities['provincia'] ?? $entities['localidad'] ?? $entities['pais'] ?? null])));
        $de = $label === '' ? '' : ' de '.$label;
        if ($total === 0) {
            $suggestions = isset($entities['taxon']) ? $this->sugerirTaxonPublico($entities['taxon']) : [];
            foreach ($suggestions as $name) $options[] = ['label' => 'Buscar '.$name, 'pregunta' => '¿Tienen '.$name.'?'];
            $hint = $suggestions === [] ? '' : ' ¿Te refieres a '.implode(' o ', $suggestions).'? Confirma el nombre antes de buscar.';
            return $this->resultado('No encontré registros publicados'.$de.' en el catálogo. Esto no confirma si existen ejemplares no divulgados.'.$hint, 'catalogo.count', $entities, 0, [], $options);
        }
        if (preg_match('/\bespecies\b/', $normal)) {
            $query->where('t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('t.nombre_cientifico'))->where('d.scientific_name_visible', true);
            $rows = (clone $query)->select('t.nombre_cientifico')->distinct()->orderBy('t.nombre_cientifico')->limit(8)->pluck('nombre_cientifico')->all();
            $count = (clone $query)->distinct()->count('t.nombre_cientifico');
            $text = 'Hay '.$count.' '.($count === 1 ? 'especie publicada' : 'especies publicadas').$de.'. '.implode('; ', $rows).($count > 8 ? '; se muestran las primeras 8.' : '.');
            return $this->resultado($text, 'catalogo.species', $entities, $count, $rows, $options);
        }
        if (preg_match('/\b(cuantos?|numero|total|tienen|existe|hay registros)\b/', $normal)) {
            return $this->resultado('Hay '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').$de.' en el catálogo.', 'catalogo.count', $entities, $total, [], $options);
        }
        $rows = (clone $query)->where('d.occurrence_id_visible', true)->where('d.scientific_name_visible', true)->select('e.occurrence_id', 't.nombre_cientifico')
            ->orderBy('e.occurrence_id')->limit(10)->get()
            ->map(static fn ($row) => $row->occurrence_id.' — '.$row->nombre_cientifico)->all();
        $text = 'Encontré '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').$de.'. Primeros resultados: '.implode('; ', $rows).($total > 10 ? '; se muestran los primeros 10.' : '.');
        return $this->resultado($text, 'catalogo.search', $entities, $total, $rows, $options);
    }

    /** Retira solo los valores previos mencionados en una cláusula geográfica negada. */
    private function geografiasNegadas(string $clausula, array $contexto): array
    {
        $negado = $this->texto->normalizar($clausula);
        if (! preg_match('/^no\b/', $negado)) return [];
        $dimensiones = ['provincia', 'localidad', 'pais'];
        $explicitas = array_filter($dimensiones, static fn (string $campo): bool => (bool) preg_match('/\b'.$campo.'\b/', $negado));
        $campos = [];
        foreach ($dimensiones as $campo) {
            if ($explicitas !== [] && ! in_array($campo, $explicitas, true)) continue;
            $valor = $this->texto->normalizar((string) ($contexto[$campo] ?? ''));
            if ($valor !== '' && preg_match('/\b'.preg_quote($valor, '/').'\b/', $negado)) $campos[] = $campo;
        }

        return $campos;
    }

    private function publicos(): Builder
    {
        return DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false);
    }

    private function filtroPorRetirar(string $normal): ?string
    {
        if (! preg_match('/^(?:y\s+)?(?:quita(?:r)?|elimina(?:r)?|sin)\s+(?:el\s+)?filtro(?:\s+de|\s+por)?\s+(.+)$/', $normal, $m)) return null;

        return ['taxon' => 'taxon', 'nombre cientifico' => 'taxon', 'codigo' => 'codigo', 'codigo de catalogo' => 'codigo',
            'provincia' => 'provincia', 'pais' => 'pais', 'localidad' => 'localidad', 'mes' => 'mes', 'mes de colecta' => 'mes',
            'fecha' => 'fechas', 'fechas' => 'fechas', 'rango de fechas' => 'fechas', 'fecha de colecta' => 'fechas',
            'fecha inicial' => 'desde', 'fecha desde' => 'desde', 'fecha final' => 'hasta', 'fecha hasta' => 'hasta',
            'elevacion' => 'elevacion', 'altitud' => 'elevacion', 'coordenadas' => 'ubicacion', 'coordenadas publicas' => 'ubicacion',
            'ubicacion' => 'ubicacion', 'identificacion' => 'identificacion'][$m[1]] ?? 'no_soportado';
    }

    /** Solo propone nombres que aparecen en registros públicos; nunca cambia el filtro en silencio. */
    private function sugerirTaxonPublico(string $name): array
    {
        if (DB::getDriverName() !== 'pgsql' || mb_strlen($name) < 4 || mb_strlen($name) > 80) return [];
        $sql = <<<'SQL'
            WITH nombres AS (
                SELECT t.nombre_cientifico AS nombre FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND e.coordenadas_otras_regiones = false AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
                UNION
                SELECT p.nombre_cientifico AS nombre FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN taxonomia.taxones p ON p.id = t.padre_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND e.coordenadas_otras_regiones = false AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
            )
            SELECT nombre FROM nombres WHERE similarity(lower(nombre), lower(?)) >= 0.45
              AND lower(nombre) <> lower(?) ORDER BY similarity(lower(nombre), lower(?)) DESC, nombre LIMIT 10
            SQL;
        $matches = array_filter(DB::select($sql, [$name, $name, $name]), static fn ($row) =>
            levenshtein(mb_strtolower($name), mb_strtolower($row->nombre)) <= 2);
        return array_slice(array_map(static fn ($row) => $row->nombre, $matches), 0, 3);
    }

    private function porCodigo(array $entities, array $options): array
    {
        $query = $this->seleccion($entities)->where('d.occurrence_id_visible', true);
        $total = (clone $query)->count();
        $rows = $query
            ->select('e.occurrence_id')->selectRaw('CASE WHEN d.scientific_name_visible THEN t.nombre_cientifico END AS nombre_cientifico')->orderBy('e.occurrence_id')->limit(10)->get();
        $items = $rows->map(static fn ($row) => $row->occurrence_id.' — '.$row->nombre_cientifico)->all();
        $text = $rows->isEmpty() ? 'No encontré registros públicos con ese código en el catálogo.'
            : 'Encontré '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').': '.implode('; ', $items).($total > 10 ? '; se muestran los primeros 10.' : '.');
        return $this->resultado($text, 'catalogo.code', $entities, $total, $items, $options);
    }

    /** La URL y el conteo comparten el mismo contrato de selección del portal. */
    public function parametros(array $entities): array
    {
        return array_filter([
            'vista' => 'registros', 'fpais' => $entities['pais'] ?? null, 'fc' => $entities['codigo'] ?? null,
            'ft' => $entities['taxon'] ?? null, 'fprov' => $entities['provincia'] ?? null,
            'fg' => isset($entities['localidad']) ? [$entities['localidad']] : null,
            'fmes' => $entities['mes'] ?? null, 'ffd' => $entities['desde'] ?? null,
            'ffh' => $entities['hasta'] ?? null, 'fgeo' => $entities['ubicacion'] ?? null,
            'fid' => $entities['identificacion'] ?? null,
            'fed' => $entities['elev_desde'] ?? null, 'feh' => $entities['elev_hasta'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    }

    private function seleccion(array $entities): Builder
    {
        $filtros = FiltrosBusqueda::desde([
            'filtroPais' => $entities['pais'] ?? '',
            'filtroCatalogo' => $entities['codigo'] ?? '', 'filtroTaxon' => $entities['taxon'] ?? '',
            'filtroProvincia' => $entities['provincia'] ?? '', 'filtroGeografias' => isset($entities['localidad']) ? [$entities['localidad']] : [],
            'filtroMes' => $entities['mes'] ?? '', 'filtroFechaDesde' => $entities['desde'] ?? '',
            'filtroFechaHasta' => $entities['hasta'] ?? '', 'filtroSoloUbicacion' => $entities['ubicacion'] ?? '',
            'filtroIdentificacion' => $entities['identificacion'] ?? '',
            'filtroElevDesde' => $entities['elev_desde'] ?? '', 'filtroElevHasta' => $entities['elev_hasta'] ?? '',
        ]);
        $seleccion = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($filtros)->select('te.id');
        return $this->publicos()->whereIn('e.id', $seleccion);
    }

    private function localidades(Builder $query, array $entities, array $options): array
    {
        $rows = (clone $query)->where(function (Builder $q): void {
            $q->where(fn (Builder $inner) => $inner->where('d.locality_name_visible', true)->whereNotNull('e.locality_name'))
                ->orWhere(fn (Builder $inner) => $inner->where('d.state_province_visible', true)->whereNotNull('e.state_province'));
        })->selectRaw('CASE WHEN d.locality_name_visible THEN e.locality_name END AS localidad, CASE WHEN d.state_province_visible THEN e.state_province END AS provincia')
            ->distinct()->limit(8)->get();
        $items = $rows->map(static fn ($row) => implode(', ', array_filter([$row->localidad, $row->provincia])))->filter()->all();
        $text = $items === [] ? 'No encontré localidades publicadas para esa búsqueda.'
            : 'Localidades publicadas: '.implode('; ', $items).'.';
        return $this->resultado($text, 'catalogo.localities', $entities, count($items), $items, $options);
    }

    private function familias(array $options): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE linaje AS (
                SELECT e.id AS especimen_id, t.id AS taxon_id, t.padre_id, t.rango, t.nombre_cientifico, 0 AS profundidad
                FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND e.coordenadas_otras_regiones = false AND d.occurrence_id_visible = true AND d.scientific_name_visible = true
                  AND d.family_visible = true AND e.occurrence_id IS NOT NULL
                UNION ALL
                SELECT l.especimen_id, p.id, p.padre_id, p.rango, p.nombre_cientifico, l.profundidad + 1
                FROM linaje l JOIN taxonomia.taxones p ON p.id = l.padre_id WHERE l.profundidad < 15
            )
            SELECT nombre_cientifico, COUNT(DISTINCT especimen_id) AS registros FROM linaje WHERE rango = 'familia'
            GROUP BY nombre_cientifico ORDER BY registros DESC, nombre_cientifico LIMIT 10
            SQL;
        $rows = array_map(static fn ($row) => $row->nombre_cientifico.' ('.$row->registros.' registros)', DB::select($sql));
        $text = $rows === [] ? 'No encontré familias con registros publicados.'
            : 'Estas familias tienen registros publicados: '.implode('; ', $rows).'. Puedes abrir el catálogo para ver más.';
        return $this->resultado($text, 'catalogo.families', [], count($rows), $rows, $options);
    }

    private function generos(array $entities, array $options): array
    {
        // Parte de la misma selección pública que el enlace; cada identificación
        // se recorre una vez aunque tenga miles de ejemplares en la colección.
        $seleccion = $this->seleccion($entities)->where('d.scientific_name_visible', true)->where('d.genus_visible', true)
            ->select('e.taxon_id')->distinct();
        $sqlSeleccion = $seleccion->toSql();
        $sql = <<<SQL
            WITH RECURSIVE linaje AS (
                SELECT t.id, t.rango, t.nombre_cientifico, t.padre_id, 0 AS profundidad
                FROM ({$sqlSeleccion}) seleccion JOIN taxonomia.taxones t ON t.id = seleccion.taxon_id
                UNION ALL
                SELECT t.id, t.rango, t.nombre_cientifico, t.padre_id, l.profundidad + 1
                FROM linaje l JOIN taxonomia.taxones t ON t.id = l.padre_id WHERE l.profundidad < 15
            )
            SELECT DISTINCT nombre_cientifico FROM linaje WHERE rango = 'genero'
            SQL;
        $generos = DB::query()->fromRaw('('.$sql.') AS generos_publicos', $seleccion->getBindings());
        $total = (clone $generos)->count();
        $rows = (clone $generos)->orderBy('nombre_cientifico')->limit(10)->pluck('nombre_cientifico')->all();
        $taxon = $entities['taxon'];
        $text = $rows === [] ? 'No encontré géneros publicados dentro de '.$taxon.'.'
            : 'Géneros publicados dentro de '.$taxon.' ('.$total.'): '.implode('; ', $rows).($total > 10 ? '; se muestran los primeros 10.' : '.');
        return $this->resultado($text, 'catalogo.genera', $entities, $total, $rows, $options);
    }

    private function resultado(string $text, string $intent, array $entities, int $total, array $rows, array $options): array
    {
        $criterios = [];
        foreach (['codigo' => 'código', 'taxon' => 'taxón', 'provincia' => 'provincia', 'localidad' => 'localidad', 'pais' => 'país', 'mes' => 'mes', 'desde' => 'desde', 'hasta' => 'hasta', 'ubicacion' => 'coordenadas públicas', 'identificacion' => 'identificación', 'elev_desde' => 'elevación desde (m)', 'elev_hasta' => 'elevación hasta (m)'] as $clave => $etiqueta) {
            if (isset($entities[$clave])) $criterios[] = $etiqueta.': '.($clave === 'ubicacion' ? 'sí' : $entities[$clave]);
        }
        if ($criterios !== []) $text .= ' Filtros aplicados: '.implode('; ', $criterios).'.';
        if (isset($entities['localidad_preferida'])) $text .= ' '.$entities['localidad_preferida'].' es una preferencia; no la apliqué como restricción obligatoria.';
        return ['texto' => $text, 'opciones' => $options, 'intent' => $intent, 'fuente' => 'catalogo',
            'confianza' => 'HIGH', 'confianza_valor' => 1.0, 'entidades' => $entities,
            'datos' => ['total' => $total, 'filas' => $rows]];
    }

    private function aclaracion(string $texto, array $contexto): array
    {
        return ['texto' => $texto, 'intent' => 'catalogo.aclaracion', 'fuente' => 'aclaracion',
            'entidades' => $contexto, 'datos' => ['total' => null, 'filas' => []],
            'opciones' => [['label' => 'Abrir catálogo', 'url' => route('portal.catalogo', $this->parametros($contexto))]]];
    }
}
