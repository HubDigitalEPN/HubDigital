<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\LocalidadPublica;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Resuelve métricas del portal antes de la ayuda de navegación o de fuentes externas. */
final class EstadisticasCatalogoChat
{
    public function responder(string $pregunta, ?array $seleccionPortal): ?array
    {
        $normal = rtrim(app(TextoChat::class)->normalizar($pregunta), '?.! ');
        $normal = preg_replace('/^(?:hola|ola) /', '', $normal) ?? $normal;
        $normal = preg_replace('/^en (cual|que) (localidad|provincia) hay /', '$1 $2 tiene ', $normal) ?? $normal;
        $ambito = '(?:este portal|el portal|(?:toda )?la coleccion|esta seleccion|la seleccion (?:actual|aplicada))';
        $ranking = preg_match('/^(?:y )?(?:cual|que)(?: es)?(?: la)? (localidad|provincia) (?:tiene|tienen|hay con|con|reune) (?:mas|mayor (?:cantidad|numero) de) (especies|registros)(?: (?:en|de) '.$ambito.')?$/', $normal, $m);
        $total = preg_match('/^cuantas especies(?: distintas)? (?:tiene|hay en|hay) (?:en )?'.$ambito.'$/', $normal);
        $geografia = preg_match('/^cuantas (ubicaciones|localidades|provincias)(?: (?:distintas|publicas))? (?:tenemos|hay|tiene)(?: (?:en |de )?'.$ambito.')?$/', $normal, $dimensionGeografica);
        if (!$ranking && !$total && !$geografia) return null;
        if (!config('chatbot.specimen_search', true)) return ['texto' => 'La consulta de la colección no está disponible en este momento.', 'fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion', 'opciones' => []];
        $deSeleccion = str_contains($normal, 'seleccion') || ($geografia && $seleccionPortal !== null && $seleccionPortal !== []
            && !preg_match('/\b(?:portal|coleccion)\b/', $normal));
        $seleccion = $deSeleccion && $seleccionPortal !== null ? SeleccionPaginaChat::desde($seleccionPortal) : null;
        if ($deSeleccion && $seleccion === null) return ['texto' => 'Abre el catálogo y aplica los filtros para consultar esa selección.', 'fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion', 'opciones' => []];
        $filtros = $seleccion?->filtros ?? FiltrosBusqueda::vacio();
        $parametros = $seleccion?->parametros ?? [];
        $base = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($filtros, $parametros['nivel'] ?? '', $parametros['taxon'] ?? '');
        $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'rango', 'nombre_cientifico'])->keyBy('id');
        $validos = CalidadDatoPublico::taxonesConLinajeValido($taxones);
        $idsPg = '{'.implode(',', $validos).'}';
        $especie = "ed.scientific_name_visible AND t.rango = 'especie' AND ".CalidadDatoPublico::textoValido('t.nombre_cientifico').' AND t.id = ANY(?::uuid[])';
        $base->leftJoin('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id');
        $alcance = $deSeleccion ? 'en la selección aplicada de la página' : 'en el catálogo público completo';
        $opciones = [['label' => 'Ver registros consultados', 'url' => route('portal.catalogo', $parametros + ['vista' => 'registros'])]];
        if ($geografia) {
            $unidad = $dimensionGeografica[1];
            if ($unidad === 'ubicaciones') {
                $cantidad = (int) $base->selectRaw('COUNT(DISTINCT (te.decimal_latitude::numeric, te.decimal_longitude::numeric)) AS cantidad')->value('cantidad');
                $detalle = 'Cada ubicación reúne los registros que comparten el mismo par de coordenadas públicas. Los números de las agrupaciones del mapa cuentan ubicaciones cercanas, no especies ni ejemplares.';
            } else {
                $campo = $unidad === 'provincias' ? 'te.state_province' : LocalidadPublica::sql('te');
                $permiso = $unidad === 'provincias' ? 'ed.state_province_visible' : 'ed.locality_name_visible';
                $cantidad = $base->where($permiso, true)->whereRaw(CalidadDatoPublico::textoValido($campo))
                    ->selectRaw('MIN('.$campo.') AS nombre')->groupByRaw(NormalizacionGeografica::sql($campo))
                    ->pluck('nombre')->filter(NormalizacionGeografica::contieneNombre(...))->count();
                $detalle = 'Se cuentan nombres públicos distintos, reuniendo sus variantes de escritura. Una localidad puede tener varias ubicaciones con coordenadas diferentes.';
            }
            $etiqueta = $cantidad === 1 ? ['ubicaciones' => 'ubicación', 'localidades' => 'localidad', 'provincias' => 'provincia'][$unidad] : $unidad;
            return $this->resultado('Hay '.$cantidad.' '.$etiqueta.' '.$alcance.'. '.$detalle, 'catalogo.'.$unidad, $cantidad, [], $opciones);
        }
        if ($total) {
            $conteo = $base->selectRaw("COUNT(*) AS registros, COUNT(DISTINCT t.nombre_cientifico) FILTER (WHERE {$especie}) AS cantidad", [$idsPg])->first();
            $cantidad = (int) $conteo->cantidad;
            $texto = 'Hay '.$cantidad.' '.($cantidad === 1 ? 'especie distinta publicada' : 'especies distintas publicadas').' '.$alcance.'. Se cuenta cada nombre científico válido a rango especie una vez, con su linaje publicado; los registros identificados solo hasta familia o género no se cuentan como especies.';
            if ($cantidad === 0 && (int) $conteo->registros > 0) $texto = 'Hay '.$conteo->registros.' registros publicados '.$alcance.'. No hay identificaciones públicas válidas a nivel de especie: los ejemplares pueden estar identificados hasta familia u otro grupo. El conteo de especies distintas es 0; esto no significa que falten los registros que ves en el mapa.';
            return $this->resultado($texto, 'catalogo.species', $cantidad, [], $opciones);
        }
        $dimension = $m[1]; $unidad = $m[2];
        $campo = $dimension === 'provincia' ? 'te.state_province' : LocalidadPublica::sql('te');
        $permiso = $dimension === 'provincia' ? 'ed.state_province_visible' : 'ed.locality_name_visible';
        $clave = NormalizacionGeografica::sql($campo);
        $valor = $unidad === 'especies' ? "COUNT(DISTINCT t.nombre_cientifico) FILTER (WHERE {$especie})" : 'COUNT(*)';
        $filas = $base->where($permiso, true)->whereRaw(CalidadDatoPublico::textoValido($campo))
            ->selectRaw("MIN({$campo}) AS nombre, {$valor} AS cantidad", $unidad === 'especies' ? [$idsPg] : [])
            ->groupByRaw($clave)->orderByDesc('cantidad')->orderBy('nombre')->get()
            ->filter(static fn (object $fila): bool => NormalizacionGeografica::contieneNombre($fila->nombre))->values();
        $maximo = (int) ($filas->first()->cantidad ?? 0);
        if ($maximo === 0) return $this->resultado('No hay datos públicos suficientes para comparar '.$dimension.' por '.$unidad.' '.$alcance.'.', 'catalogo.ranking_'.$dimension, 0, [], $opciones);
        // Conserva todos los empates antes de presentar el máximo; no escoge un ganador arbitrario.
        $ganadores = $filas->filter(static fn (object $fila): bool => (int) $fila->cantidad === $maximo);
        $nombres = $ganadores->pluck('nombre')->all();
        $etiquetaCantidad = $maximo === 1 ? rtrim($unidad, 's') : $unidad;
        $texto = ($ganadores->count() === 1 ? 'La '.$dimension.' con más '.$unidad.' es ' : 'Las '.($dimension === 'provincia' ? 'provincias' : 'localidades').' con más '.$unidad.' son ').implode('; ', $nombres).': '.$maximo.' '.$etiquetaCantidad.($ganadores->count() > 1 ? ' cada una' : '').' '.$alcance.'.';
        $texto .= $unidad === 'especies' ? ' Se cuentan especies distintas, no ejemplares ni nombres a rango familia.' : ' Cada registro corresponde a una entrada publicada del catálogo.';
        $texto .= ' La comparación incluye únicamente ubicaciones publicadas.';
        return $this->resultado($texto, 'catalogo.ranking_'.$dimension, $maximo, $nombres, $opciones);
    }

    private function resultado(string $texto, string $intent, int $total, array $rows, array $opciones): array
    {
        return compact('texto', 'intent', 'total', 'rows', 'opciones') + ['datos' => compact('total', 'rows'), 'fuente' => 'catalogo', 'confianza' => 'HIGH', 'confianza_valor' => 1.0, 'entidades' => []];
    }
}
