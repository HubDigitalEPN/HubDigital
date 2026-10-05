<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Lista blanca de consultas determinísticas sobre ejemplares divulgables. */
final class ConsultaCatalogoPublico
{
    public function __construct(private readonly DetectorEntidadesChat $detector, private readonly TextoChat $texto) {}

    /** La descripción usa únicamente el linaje y los permisos de registros públicos. */
    public function clasificacionPublica(string $pregunta): ?array
    {
        if (! config('chatbot.specimen_search', true)) return null;
        $entities = $this->detector->extraer($pregunta);
        if (! isset($entities['taxon'])) return null;
        $taxon = DB::table('taxonomia.taxones')->whereRaw('lower(nombre_cientifico) = lower(?)', [$entities['taxon']])
            ->first(['id', 'nombre_cientifico', 'rango']);
        if ($taxon === null || ! CalidadDatoPublico::esTextoValido($taxon->nombre_cientifico)) return null;
        $publicos = $this->publicos()->where('d.scientific_name_visible', true)->whereRaw(<<<'SQL'
            e.taxon_id IN (WITH RECURSIVE seleccion AS (
                SELECT id FROM taxonomia.taxones WHERE id = ?
                UNION SELECT t.id FROM taxonomia.taxones t JOIN seleccion s ON t.padre_id = s.id
            ) SELECT id FROM seleccion)
            SQL, [$taxon->id]);
        if ($taxon->rango === 'familia') $publicos->where('d.family_visible', true);
        if ($taxon->rango === 'genero') $publicos->where('d.genus_visible', true);
        $fila = (clone $publicos)->orderByDesc('d.family_visible')->orderByDesc('d.genus_visible')
            ->first(['d.family_visible', 'd.genus_visible']);
        if ($fila === null) return null;
        $linaje = DB::select(<<<'SQL'
            WITH RECURSIVE ruta AS (
                SELECT id, padre_id, rango, nombre_cientifico, 0 AS profundidad, ARRAY[id] AS camino
                FROM taxonomia.taxones WHERE id = ?
                UNION ALL
                SELECT p.id, p.padre_id, p.rango, p.nombre_cientifico, r.profundidad + 1, r.camino || p.id
                FROM ruta r JOIN taxonomia.taxones p ON p.id = r.padre_id
                WHERE r.profundidad < 29 AND NOT p.id = ANY(r.camino)
            ) SELECT * FROM ruta ORDER BY profundidad DESC
            SQL, [$taxon->id]);
        if (array_any($linaje, static fn (object $n): bool => $n->profundidad >= 29 || in_array($n->padre_id, explode(',', trim((string) $n->camino, '{}')), true))) return null;
        $ruta = CalidadDatoPublico::rutaConfirmada(array_map(static fn (object $n): array => ['rango' => $n->rango, 'nombre' => $n->nombre_cientifico], $linaje));
        $ruta = array_filter($ruta, static fn (array $n): bool => ($n['rango'] !== 'familia' || $fila->family_visible) && ($n['rango'] !== 'genero' || $fila->genus_visible));
        if ($ruta === [] || end($ruta)['nombre'] !== $taxon->nombre_cientifico) return null;
        $rango = ['genero' => 'género', 'especie' => 'especie', 'familia' => 'familia', 'phylum' => 'filo', 'clase' => 'clase', 'orden' => 'orden'][$taxon->rango] ?? $taxon->rango;
        $texto = $taxon->nombre_cientifico.' figura como '.$rango.' en la clasificación interna publicada del catálogo. Linaje público: '.implode(' → ', array_column($ruta, 'nombre')).'. Hay '.(clone $publicos)->count('e.id').' registros públicos de este taxón y sus descendientes. No hay una descripción de historia natural publicada para esta consulta; la clasificación no certifica la identificación física ni un nombre aceptado por una autoridad externa.';
        $referencia = app(\Modules\CatalogoPublico\Application\Services\ReferenciaTaxonomicaPublica::class)->para($taxon->nombre_cientifico, (bool) $fila->family_visible);
        if ($referencia !== null) {
            if ($fila->family_visible) {
                $familia = array_find($ruta, static fn (array $n): bool => $n['rango'] === 'familia');
                if ($familia !== null) $texto .= ' Familia publicada en el catálogo: '.$familia['nombre'].'.';
                if ($referencia['familia_referencia'] !== null) $texto .= ' Familia de referencia externa: '.$referencia['familia_referencia'].'.';
            }
            $texto .= ' Contraste externo: '.$referencia['fuente'].' consultado el '.$referencia['fecha'].'. '.$referencia['decision'].'. '.$referencia['limite'];
        }
        return ['texto' => $texto, 'fuente' => 'catalogo', 'intent' => 'catalogo.clasificacion', 'entidades' => $entities,
            'opciones' => [['label' => 'Ver registros del taxón', 'url' => route('portal.catalogo', $this->parametros($entities))]]];
    }

    /** Cuenta la misma población pública del mapa sin revelar coordenadas reservadas. */
    public function diagnosticoMapa(array $entrada): ?array
    {
        $seleccion = SeleccionPaginaChat::desde($entrada);
        if ($seleccion === null) return null;
        $query = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($seleccion->filtros,
            $seleccion->parametros['nivel'] ?? '', $seleccion->parametros['taxon'] ?? '');
        $total = (clone $query)->count('te.id');
        $coordenadas = $query->where('ed.decimal_latitude_visible', true)->where('ed.decimal_longitude_visible', true)
            ->whereBetween('te.decimal_latitude', [-90, 90])->whereBetween('te.decimal_longitude', [-180, 180])->count('te.id');

        return ['total' => $total, 'con_coordenadas' => $coordenadas];
    }

    public function responder(string $pregunta, array $contexto = [], ?array $seleccionPortal = null, ?string $alcanceConfirmado = null): ?array
    {
        if (! config('chatbot.specimen_search', true)) {
            return null;
        }
        $normal = $this->texto->normalizar($pregunta);
        $operadorProvincia = $this->detector->operadorProvincia($pregunta);
        if (isset($operadorProvincia['error_consulta'])) {
            $aclaracion = $this->aclaracion($operadorProvincia['error_consulta'], $contexto, sinDatos: true);
            $aclaracion['opciones'] = [];
            $aclaracion['solicitud_pendiente'] = null;
            return $aclaracion;
        }
        $referenciaSeleccion = $alcanceConfirmado === 'pagina' || ($alcanceConfirmado !== 'consulta' && ($this->esReferenciaSeleccion($pregunta)
            || ($contexto === [] && $seleccionPortal !== null && (bool) preg_match('/^y\s+cuant|^y?\s*donde\s+se\s+(?:recolecto|colecto)|^y\s+donde/', $normal))));
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
            && preg_match('/\b(?:registros|especimenes|ejemplares|especies|generos|familias)\b/', $normal)
            && preg_match('/\b(?:en|de)\s+(?:toda\s+)?(?:(?:la\s+)?coleccion|(?:el\s+)?catalogo)\b/', $normal)
            && ! preg_match('/\b(?:esa especie|ese taxon|esos registros|esos ejemplares|estos registros)\b/', $normal);
        $correction = (bool) preg_match('/^(perdon|corrijo|quise decir|queria decir|no )\b/', $normal);
        $followup = (bool) preg_match('/^(?:y\s+de\s+|y\s+)?cuantos?\b|^(?:y|solo|dame solo|quita|quitar|elimina)\s+|^donde\s+los\s+encontraron\b|\b(?:esa especie|ese taxon|esos registros|de esos dos registros)\b/', $normal);
        $queryText = $pregunta;
        $geografiasNegadas = [];
        if ($operadorProvincia === [] && $correction && str_starts_with($normal, 'no ')) {
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
        if (($correction || $followup || $retirarFiltro !== null) && ! $coleccionExplicita && ! $referenciaSeleccion && $contexto !== []) {
            $previous = array_intersect_key($contexto, array_flip(['taxon', 'provincia', 'localidad', 'pais', 'codigo', 'mes', 'desde', 'hasta', 'fecha_precision', 'ubicacion', 'identificacion', 'elev_desde', 'elev_hasta']));
            if (isset($entities['taxon']) || isset($entities['codigo'])) unset($previous['taxon'], $previous['codigo']);
            foreach ($geografiasNegadas as $geografia) {
                unset($previous[$geografia]);
            }
            foreach (['provincia', 'localidad', 'pais'] as $geografia) {
                if (isset($entities[$geografia])) {
                    unset($previous[$geografia]);
                }
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
        if (preg_match('/\bo\b/', $normal) && ! preg_match('/\bindividuos?\s+o\s+(?:dos\s+)?especies\b/', $normal)) {
            return $this->aclaracion('La unión de alternativas con «o» no está disponible en esta consulta. No he calculado un conteo parcial. Elige una alternativa o consulta cada una por separado.', $contexto);
        }
        if (preg_match('/\b(?:hembras?|machos?|femenin[oa]s?|masculin[oa]s?|sexo)\b/', $normal)) {
            return $this->aclaracion('El filtro por sexo no está disponible en esta consulta. No he calculado un conteo parcial; indica otros criterios o consulta al laboratorio sobre ese dato.', $contexto);
        }
        if (preg_match('/\b(?:preservad[oa]s?|conservad[oa]s?|alcohol|etanol|formol)\b/', $normal)) {
            return $this->aclaracion('No puedo traducir esa condición de preservación a un filtro confirmado. No he calculado un conteo parcial. Revisa Preparación en el catálogo y elige el valor disponible.', $contexto);
        }
        if (preg_match('/\b(?:sin|no tienen|no tengan)\s+coordenadas\b/', $normal)) {
            return $this->aclaracion('La exclusión solicitada no está disponible en los filtros de esta consulta. No he calculado un conteo parcial. Puedes reformular con los criterios que deseas incluir.', $contexto);
        }
        if ($referenciaSeleccion) {
            $entrada = $seleccionPortal ?? ($contexto !== [] ? $this->parametros($contexto) : null);
            if ($entrada === null) return $this->aclaracion('No tengo una selección aplicada ni una consulta pública anterior. Abre el catálogo y aplica los filtros o escribe qué quieres contar.', $contexto);
            $seleccion = SeleccionPaginaChat::desde($entrada);
            if ($seleccion === null) return $this->aclaracion('La selección recibida contiene criterios inválidos. Revisa los filtros del catálogo; no he calculado un conteo parcial.', $contexto);
            if ($alcanceConfirmado === 'pagina' || preg_match('/\bestos\s+(?:dos\s+)?registros\b.*\bindividuos?\b/', $normal)) {
                $entities = [];
                $coleccionExplicita = false;
            }
            if ($coleccionExplicita || ! $this->criteriosCoinciden($entities, $seleccion)) {
                $respuesta = $this->aclaracion('Los criterios nombrados difieren de la selección aplicada. Elige cuál usar; no he calculado un conteo ni preparado enlaces parciales.', $contexto);
                $respuesta['requiere_seleccion'] = true;
                $respuesta['opciones'] = [
                    ['label' => 'Usar selección de la página', 'pregunta' => 'La selección aplicada de esta página'],
                    ['label' => 'Preparar consulta nueva', 'pregunta' => 'Una consulta nueva con los criterios que indiqué'],
                ];
                return $respuesta;
            }

            return $this->contarSeleccion($normal, $seleccion, $seleccionPortal === null ? $contexto : [], $seleccionPortal !== null);
        }
        $options = [['label' => 'Abrir catálogo para ver más', 'url' => route('portal.catalogo', $this->parametros($entities))]];
        $unidades = $this->unidadesSolicitadas($normal);
        if (count($unidades) > 1) return $this->contarUnidades($this->seleccion($entities), $entities, $options, $unidades);
        $unidadPedida = $unidades[0] ?? null;
        $especiesPedidas = $unidadPedida === 'especies' || ($unidadPedida === null && preg_match('/\bespecies\b/', $normal));
        if ($unidadPedida === 'familias' || ($unidadPedida === null && preg_match('/\bfamilias\b/', $normal))) {
            if (preg_match('/\bmas\s+registros\b/', $normal)) return $this->familias($entities, $options);
            return $this->taxonesPorRango($this->seleccion($entities), $entities, $options, 'familia');
        }
        if ($unidadPedida === 'generos' || ($unidadPedida === null && preg_match('/\bgeneros\b/', $normal))) {
            return $this->taxonesPorRango($this->seleccion($entities), $entities, $options, 'genero');
        }
        if (isset($entities['codigo'])) {
            return $this->porCodigo($entities, $options);
        }
        if ($entities === [] && $retirarFiltro === null && ! $coleccionExplicita && ! preg_match('/\bcuant[oa]s?\s+(?:registros|especimenes|ejemplares|especies)\s+(?:public[oa]s?\s+)?(?:tienen|hay|estan)/', $normal)) {
            return null;
        }
        $query = $this->seleccion($entities);
        if (preg_match('/\bdonde\b.*\b(?:recolect|colect|encontr)/', $normal) && $entities !== []) {
            return $this->localidades($query, $entities, $options);
        }
        $total = (clone $query)->count('e.id');
        $label = implode(' en ', array_values(array_filter([$entities['taxon'] ?? null, $entities['provincia'] ?? $entities['localidad'] ?? $entities['pais'] ?? null])));
        $de = $label === '' ? '' : ' de '.$label;
        if ($total === 0) {
            $suggestions = isset($entities['taxon']) ? $this->sugerirTaxonPublico($entities['taxon']) : [];
            foreach ($suggestions as $name) $options[] = ['label' => 'Buscar '.$name, 'pregunta' => '¿Tienen '.$name.'?'];
            $hint = $suggestions === [] ? '' : ' ¿Te refieres a '.implode(' o ', $suggestions).'? Confirma el nombre antes de buscar.';
            $texto = 'No encontré registros publicados'.$de.' en el catálogo. Esto no confirma si existen ejemplares no divulgados.'.$hint;
            // Una selección sin registros también tiene cero especies públicas;
            // conserva la unidad pedida y no afirma ausencia biológica.
            if ($especiesPedidas) $texto = 'Hay 0 especies publicadas'.$de.' en el catálogo. '.$texto;
            return $this->resultado($texto, $especiesPedidas ? 'catalogo.species' : 'catalogo.count', $entities, 0, [], $options);
        }
        if ($especiesPedidas) {
            return $this->taxonesPorRango($query->where('t.rango', 'especie'), $entities, $options, 'especie');
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

    public function esReferenciaSeleccion(string $pregunta): bool
    {
        return (bool) preg_match('/\b(?:(?:esta|esa|mi) seleccion|seleccion (?:actual|aplicada)|(?:esta|esa|mi) pagina|(?:de|en) aqui|registros visibles|(?:estos|estas) (?:(?:dos|tres|cuatro|cinco|seis|\d+) )?(?:registros|caracoles|ejemplares|especimenes|invertebrados))\b/', $this->texto->normalizar($pregunta));
    }

    private function criteriosCoinciden(array $entidades, SeleccionPaginaChat $seleccion): bool
    {
        $aplicados = $seleccion->parametros;
        foreach ($this->parametros($entidades) as $clave => $valor) {
            if ($clave === 'vista') continue;
            if ($clave === 'ft') {
                if (! in_array($this->texto->normalizar((string) $valor), array_map(fn (string $nombre): string => $this->texto->normalizar($nombre), [$aplicados['ft'] ?? '', $aplicados['taxon'] ?? '']), true)) return false;
            } elseif (is_array($valor)) {
                $actuales = array_map(fn (string $nombre): string => $this->texto->normalizar($nombre), $aplicados[$clave] ?? []);
                foreach ($valor as $nombre) if (! in_array($this->texto->normalizar($nombre), $actuales, true)) return false;
            } elseif (! isset($aplicados[$clave])) {
                return false;
            } elseif ($clave === 'fc') {
                // Los ceros y separadores pertenecen al identificador, no a un número.
                if (mb_strtolower(trim((string) $valor)) !== mb_strtolower(trim((string) $aplicados[$clave]))) return false;
            } elseif (in_array($clave, ['flat', 'flax', 'flon', 'flox', 'fed', 'feh', 'fmes'], true)
                && is_numeric($valor) && is_numeric($aplicados[$clave])) {
                if ((float) $valor !== (float) $aplicados[$clave]) return false;
            } elseif ($this->texto->normalizar((string) $valor) !== $this->texto->normalizar((string) $aplicados[$clave])) return false;
        }
        return true;
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
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'));
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
                WHERE d.publicado = true AND __ELEGIBILIDAD_GEOGRAFICA__ AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
                UNION
                SELECT p.nombre_cientifico AS nombre FROM taxonomia.especimenes e
                JOIN taxonomia.taxones t ON t.id = e.taxon_id
                JOIN taxonomia.taxones p ON p.id = t.padre_id
                JOIN divulgacion.especimenes_divulgables d ON d.especimen_id = e.id
                WHERE d.publicado = true AND __ELEGIBILIDAD_GEOGRAFICA__ AND d.scientific_name_visible = true AND d.occurrence_id_visible = true
            )
            SELECT nombre FROM nombres WHERE similarity(lower(nombre), lower(?)) >= 0.45
              AND lower(nombre) <> lower(?) ORDER BY similarity(lower(nombre), lower(?)) DESC, nombre LIMIT 10
            SQL;
        $sql = str_replace('__ELEGIBILIDAD_GEOGRAFICA__', ElegibilidadGeograficaPortal::sql('e', 'd'), $sql);
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
            $q->where(fn (Builder $inner) => $inner->where('d.locality_name_visible', true)->whereNotNull(DB::raw(\Modules\CatalogoPublico\Infrastructure\LocalidadPublica::sql())))
                ->orWhere(fn (Builder $inner) => $inner->where('d.state_province_visible', true)->whereNotNull('e.state_province'));
        })->selectRaw('CASE WHEN d.locality_name_visible THEN '.\Modules\CatalogoPublico\Infrastructure\LocalidadPublica::sql().' END AS localidad, CASE WHEN d.state_province_visible THEN e.state_province END AS provincia')
            ->distinct()->limit(8)->get();
        $items = $rows->map(static fn ($row) => implode(', ', array_filter([$row->localidad, $row->provincia])))->filter()->all();
        $text = $items === [] ? 'No encontré localidades publicadas para esa búsqueda.'
            : 'Localidades publicadas: '.implode('; ', $items).'.';
        return $this->resultado($text, 'catalogo.localities', $entities, count($items), $items, $options);
    }

    private function contarSeleccion(string $normal, SeleccionPaginaChat $seleccion, array $entities, bool $pagina): array
    {
        if (preg_match('/\b(holotipos?|paratipos?|alotipos?|sintipos?|lectotipos?|neotipos?)\b/', $normal, $tipo)) {
            $tipoCanonico = \Modules\CatalogoPublico\Domain\ValueObjects\CondicionMaterialPublica::tipoFiltro(rtrim($tipo[1], 's'));
            if ($seleccion->filtros->tipo !== null && $seleccion->filtros->tipo !== $tipoCanonico) return $this->aclaracion('La selección tiene otra condición de tipo aplicada. Retira ese criterio para consultar '.$tipo[1].'; no he sustituido el filtro.', $entities);
            $seleccion = SeleccionPaginaChat::desde($seleccion->parametros + ['fsti' => $tipoCanonico]);
            $normal .= ' registros';
        }
        preg_match_all('/\b(registros|especimenes|ejemplares|especies|generos|familias)\b/', $normal, $unidades);
        $unidades = $this->unidadesSolicitadas($normal) ?: array_unique(array_map(static fn (string $unidad): string => in_array($unidad, ['especimenes', 'ejemplares'], true) ? 'registros' : $unidad, $unidades[1]));
        $ids = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($seleccion->filtros,
            $seleccion->parametros['nivel'] ?? '', $seleccion->parametros['taxon'] ?? '')->select('te.id');
        $query = $this->publicos()->whereIn('e.id', $ids);
        $options = [['label' => 'Abrir registros de la selección', 'url' => route('portal.catalogo', $seleccion->parametros + ['vista' => 'registros'])]];
        if (preg_match('/\bdonde\b.*\b(?:recolect|colect|encontr)/', $normal)) return $this->localidades($query, $entities, $options);
        if (count($unidades) > 1) return $this->contarUnidades($query, $entities, $options, array_values($unidades), $pagina ? 'en la selección aplicada de la página' : 'en la consulta pública anterior');
        if (count($unidades) !== 1) return $this->aclaracion('Indica qué unidad quieres contar en la selección: registros, especies, géneros o familias.', $entities);
        $unidad = reset($unidades);
        $poblacion = $pagina ? 'en la selección aplicada de la página' : 'en la consulta pública anterior';
        if (in_array($unidad, ['generos', 'familias'], true)) {
            return $this->taxonesPorRango($query, $entities, $options, $unidad === 'generos' ? 'genero' : 'familia', $poblacion);
        }
        if ($unidad === 'especies') {
            return $this->taxonesPorRango($query->where('t.rango', 'especie'), $entities, $options, 'especie', $poblacion);
        }
        $total = $query->count('e.id');
        $texto = 'Hay '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').' '.$poblacion.'.';
        if (isset($tipoCanonico)) $texto .= ' Condición de tipo: '.$tipoCanonico.'. Este conteo es de registros, no de individuos.';
        return $this->resultado($texto, 'catalogo.count', $entities, $total, [], $options);
    }

    private function unidadesSolicitadas(string $normal): array
    {
        if (preg_match('/\bregistros\b.*\bindividuos?\s+o\s+(?:dos\s+)?especies\b/', $normal)) return ['registros', 'especies'];
        $unidad = '(?:registros|especimenes|ejemplares|especies|generos|familias)';
        preg_match_all('/\b(?:cuant[oa]s?|numero(?: de)?|total(?: de)?)\s+(?:(?:los|las|distint[oa]s?|public[oa]s?)\s+)*('.$unidad.')(?:\s+y\s+(?:cuant[oa]s?\s+)?('.$unidad.'))?\b/', $normal, $pedidas, PREG_SET_ORDER);
        $unidades = [];
        foreach ($pedidas as $pedida) {
            foreach (array_slice($pedida, 1) as $nombre) {
                if ($nombre !== '') $unidades[] = in_array($nombre, ['ejemplares', 'especimenes'], true) ? 'registros' : $nombre;
            }
        }
        return array_values(array_unique($unidades));
    }

    private function contarUnidades(Builder $query, array $entities, array $options, array $unidades, ?string $poblacion = null): array
    {
        $partes = []; $conteos = [];
        foreach ($unidades as $unidad) {
            if ($unidad === 'registros') {
                $total = (clone $query)->count('e.id');
                $partes[] = 'Hay '.$total.' '.($total === 1 ? 'registro publicado' : 'registros publicados').($poblacion ? ' '.$poblacion : '').'.';
            } else {
                $rango = ['especies' => 'especie', 'generos' => 'genero', 'familias' => 'familia'][$unidad];
                $respuesta = $this->taxonesPorRango(clone $query, $entities, [], $rango, $poblacion);
                $total = $respuesta['datos']['total'];
                $partes[] = $respuesta['texto'];
            }
            $conteos[$unidad] = $total;
        }
        $partes[] = 'Los registros no equivalen necesariamente a individuos; el número de individuos requiere el campo público correspondiente. Las especies se cuentan según la jerarquía interna publicada.';
        $respuesta = $this->resultado(implode("\n\n", $partes), 'catalogo.conteos', $entities, (clone $query)->count('e.id'), [], $options);
        $respuesta['datos']['conteos'] = $conteos;
        return $respuesta;
    }

    /** Solo abre directamente una especie si toda la selección tiene esa identidad pública. */
    public function parametrosTarjetasEspecie(array $parametros): array
    {
        $seleccion = SeleccionPaginaChat::desde($parametros);
        if ($seleccion === null) return $parametros;
        if (($seleccion->parametros['nivel'] ?? '') === 'species') return $seleccion->parametros;
        $filas = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($seleccion->filtros,
            $seleccion->parametros['nivel'] ?? '', $seleccion->parametros['taxon'] ?? '')
            ->leftJoin('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->select('t.id', 't.nombre_cientifico', 't.rango', 'ed.scientific_name_visible')->distinct()->limit(2)->get();
        if ($filas->count() !== 1 || ! $filas[0]->scientific_name_visible || $filas[0]->rango !== 'especie') return $seleccion->parametros;
        return array_replace($seleccion->parametros, ['nivel' => 'species', 'taxon' => $filas[0]->nombre_cientifico]);
    }

    private function familias(array $entities, array $options): array
    {
        // Agrupa ejemplares antes del recorrido: no materializa la colección ni
        // repite el linaje una vez por cada registro.
        $seleccion = $this->seleccion($entities)->where('d.scientific_name_visible', true)->where('d.family_visible', true)
            ->select('e.taxon_id')->selectRaw('COUNT(*) AS registros')->groupBy('e.taxon_id');
        $sqlSeleccion = $seleccion->toSql();
        $nombreValido = CalidadDatoPublico::textoValido('nombre_cientifico');
        $linajeConfirmado = $this->linajeConfirmado();
        $sql = <<<SQL
            WITH RECURSIVE linaje AS (
                SELECT t.id AS raiz, t.id, t.padre_id, t.rango, t.nombre_cientifico, s.registros, ARRAY[t.id] AS camino, 0 AS profundidad
                FROM ({$sqlSeleccion}) s JOIN taxonomia.taxones t ON t.id = s.taxon_id
                UNION ALL
                SELECT l.raiz, p.id, p.padre_id, p.rango, p.nombre_cientifico, l.registros, l.camino || p.id, l.profundidad + 1
                FROM linaje l JOIN taxonomia.taxones p ON p.id = l.padre_id
                WHERE l.profundidad < 29 AND NOT p.id = ANY(l.camino)
            )
            SELECT nombre_cientifico, SUM(registros) AS registros FROM linaje WHERE rango = 'familia' AND {$nombreValido} AND {$linajeConfirmado}
            GROUP BY nombre_cientifico ORDER BY registros DESC, nombre_cientifico LIMIT 10
            SQL;
        $rows = array_map(static fn ($row) => $row->nombre_cientifico.' ('.$row->registros.' registros)', DB::select($sql, $seleccion->getBindings()));
        $text = $rows === [] ? 'No encontré familias con registros publicados.'
            : 'Estas familias tienen registros publicados: '.implode('; ', $rows).'. Puedes abrir el catálogo para ver más.';
        return $this->resultado($text, 'catalogo.families', $entities, count($rows), $rows, $options);
    }

    private function taxonesPorRango(Builder $query, array $entities, array $options, string $rango, ?string $poblacion = null): array
    {
        // Parte de la misma selección pública que el enlace; cada identificación
        // se recorre una vez aunque tenga miles de ejemplares en la colección.
        $visible = match ($rango) { 'familia' => 'd.family_visible', 'genero' => 'd.genus_visible', default => 'd.scientific_name_visible' };
        $seleccion = $query->where('d.scientific_name_visible', true)->where($visible, true)
            ->select('e.taxon_id')->distinct();
        $sqlSeleccion = $seleccion->toSql();
        $nombreValido = CalidadDatoPublico::textoValido('nombre_cientifico');
        $linajeConfirmado = $this->linajeConfirmado();
        $sql = <<<SQL
            WITH RECURSIVE linaje AS (
                SELECT t.id AS raiz, t.id, t.rango, t.nombre_cientifico, t.padre_id, ARRAY[t.id] AS camino, 0 AS profundidad
                FROM ({$sqlSeleccion}) seleccion JOIN taxonomia.taxones t ON t.id = seleccion.taxon_id
                UNION ALL
                SELECT l.raiz, t.id, t.rango, t.nombre_cientifico, t.padre_id, l.camino || t.id, l.profundidad + 1
                FROM linaje l JOIN taxonomia.taxones t ON t.id = l.padre_id
                WHERE l.profundidad < 29 AND NOT t.id = ANY(l.camino)
            )
            SELECT DISTINCT nombre_cientifico FROM linaje WHERE rango = ? AND {$nombreValido} AND {$linajeConfirmado}
            SQL;
        $taxones = DB::query()->fromRaw('('.$sql.') AS taxones_publicos', [...$seleccion->getBindings(), $rango]);
        $total = (clone $taxones)->count();
        $limite = $rango === 'especie' ? 8 : 10;
        $rows = (clone $taxones)->orderBy('nombre_cientifico')->limit($limite)->pluck('nombre_cientifico')->all();
        $plural = match ($rango) { 'familia' => 'familias', 'genero' => 'géneros', default => 'especies' };
        $singular = match ($rango) { 'familia' => 'familia', 'genero' => 'género', default => 'especie' };
        $poblacion ??= isset($entities['taxon']) ? 'dentro de '.$entities['taxon'] : 'en el catálogo público';
        $text = 'Hay '.$total.' '.($total === 1 ? $singular.($rango === 'genero' ? ' publicado' : ' publicada') : $plural.($rango === 'genero' ? ' publicados' : ' publicadas')).' '.$poblacion.'. Se cuenta cada nombre científico válido para la jerarquía interna publicada de '.$singular.' una vez entre las identificaciones de registros divulgados. Este conteo no certifica la aceptación del nombre por una autoridad externa ni la identificación física del ejemplar.';
        if ($rows !== []) $text .= ' '.implode('; ', $rows).($total > $limite ? '; se muestran '.($rango === 'genero' ? 'los primeros ' : 'las primeras ').$limite.'.' : '.');
        $intent = match ($rango) { 'familia' => 'catalogo.families', 'genero' => 'catalogo.genera', default => 'catalogo.species' };
        return $this->resultado($text, $intent, $entities, $total, $rows, $options);
    }

    /** Confirma candidato y ancestros; una nota inferior no invalida sus padres. */
    private function linajeConfirmado(): string
    {
        $nombreValido = CalidadDatoPublico::textoValido('ancestro.nombre_cientifico');

        return <<<SQL
            NOT EXISTS (
                SELECT 1 FROM linaje ancestro WHERE ancestro.raiz = linaje.raiz AND (
                    (ancestro.profundidad >= linaje.profundidad AND NOT {$nombreValido})
                    OR ancestro.padre_id = ANY(ancestro.camino)
                    OR (ancestro.profundidad = 29 AND EXISTS (
                        SELECT 1 FROM taxonomia.taxones pendiente WHERE pendiente.id = ancestro.padre_id
                    ))
                )
            )
            SQL;
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

    private function aclaracion(string $texto, array $contexto, bool $sinDatos = false): array
    {
        $respuesta = ['texto' => $texto, 'intent' => 'catalogo.aclaracion', 'fuente' => 'aclaracion',
            'entidades' => $contexto, 'datos' => ['total' => null, 'filas' => []],
            'opciones' => [['label' => 'Abrir catálogo', 'url' => route('portal.catalogo', $this->parametros($contexto))]]];
        if ($sinDatos) unset($respuesta['datos']);
        return $respuesta;
    }
}
