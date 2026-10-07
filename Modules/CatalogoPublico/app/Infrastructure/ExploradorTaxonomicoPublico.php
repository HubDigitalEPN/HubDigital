<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Agrega linajes públicos; al cliente entrega únicamente las ramas solicitadas. */
final class ExploradorTaxonomicoPublico
{
    private const RANGOS = ['phylum' => 'Filo', 'filo' => 'Filo', 'subfilo' => 'Subfilo',
        'clase' => 'Clase', 'class' => 'Clase', 'subclase' => 'Subclase', 'orden' => 'Orden', 'order' => 'Orden',
        'suborden' => 'Suborden', 'infraorden' => 'Infraorden', 'superfamilia' => 'Superfamilia',
        'familia' => 'Familia', 'family' => 'Familia', 'subfamilia' => 'Subfamilia', 'tribu' => 'Tribu',
        'subtribu' => 'Subtribu', 'genero' => 'Género', 'género' => 'Género', 'genus' => 'Género',
        'subgenero' => 'Subgénero', 'especie' => 'Especie', 'species' => 'Especie',
        'subespecie' => 'Subespecie', 'subspecies' => 'Subespecie'];

    public function consultar(FiltrosBusqueda $filtros, ?string $padre = null, string $busqueda = ''): array
    {
        $indice = $this->indice($filtros);
        $nodos = $indice['nodos'];
        $busqueda = mb_substr(trim($busqueda), 0, 120);
        $expandidos = [];
        $hayMas = false;
        if ($busqueda !== '') {
            // Cada búsqueda consulta PostgreSQL; no busca en una copia cliente del catálogo.
            $ids = DB::table('taxonomia.taxones')->whereIn('id', array_column($nodos, 'id'))
                ->where('nombre_cientifico', 'ILIKE', '%'.addcslashes($busqueda, '\\%_').'%')
                ->orderBy('nombre_cientifico')->orderBy('id')->limit(51)->pluck('id')->all();
            $hayMas = count($ids) > 50;
            $coincidencias = array_fill_keys(array_slice($ids, 0, 50), true);
            $visibles = [];
            foreach ($nodos as $clave => $nodo) {
                if (!isset($coincidencias[$nodo['id']])) continue;
                foreach ($indice['rutas'][$clave] as $ancestro) {
                    $visibles[$ancestro] = true;
                    if ($ancestro !== $clave) $expandidos[$ancestro] = true;
                }
            }
            $resultado = array_intersect_key($nodos, $visibles);
            foreach ($resultado as &$nodo) $nodo['coincide'] = isset($coincidencias[$nodo['id']]);
            unset($nodo);
        } else {
            if ($padre !== null && !isset($nodos[$padre])) abort(404);
            $resultado = array_filter($nodos, static fn (array $n): bool => $n['padre'] === $padre);
        }
        return ['nodos' => array_values($resultado), 'expandidos' => array_keys($expandidos),
            'hayMas' => $hayMas, 'totalCatalogo' => $indice['total']];
    }

    public function taxon(FiltrosBusqueda $filtros, string $id): ?array
    {
        if (!Str::isUuid($id)) return null;
        $id = strtolower($id);
        return array_find($this->indice($filtros)['nodos'], static fn (array $n): bool => $n['id'] === $id);
    }

    public function exacto(FiltrosBusqueda $filtros, string $nombre): array
    {
        $nombre = mb_substr(trim($nombre), 0, 120);
        if ($nombre === '') return [];
        $nodos = $this->indice($filtros)['nodos'];
        $ids = DB::table('taxonomia.taxones')->whereIn('id', array_column($nodos, 'id'))
            ->whereRaw('lower(btrim(nombre_cientifico)) = lower(?)', [$nombre])->limit(2)->pluck('id')->all();
        return array_values(array_filter($nodos, static fn (array $n): bool => in_array($n['id'], $ids, true)));
    }

    private function indice(FiltrosBusqueda $filtros): array
    {
        $base = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($filtros);
        $total = (clone $base)->count('te.id');
        $filas = $base->where('ed.scientific_name_visible', true)
            ->selectRaw('te.taxon_id, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.family_visible', 'ed.genus_visible')->get();
        $ids = array_values(array_filter($filas->pluck('taxon_id')->unique()->all(), Str::isUuid(...)));
        if ($ids === []) return ['nodos' => [], 'rutas' => [], 'total' => $total];
        // Solo se leen los ancestros de las identificaciones presentes, sin hidratar ejemplares.
        $taxones = collect(DB::select(<<<'SQL'
            WITH RECURSIVE ancestros AS (
                SELECT id, padre_id, nombre_cientifico, rango, ARRAY[id] AS camino, 0 AS profundidad
                FROM taxonomia.taxones WHERE id = ANY(?::uuid[])
                UNION ALL
                SELECT t.id, t.padre_id, t.nombre_cientifico, t.rango, a.camino || t.id, a.profundidad + 1
                FROM taxonomia.taxones t JOIN ancestros a ON t.id = a.padre_id
                WHERE a.profundidad < 30 AND NOT t.id = ANY(a.camino)
            ) SELECT DISTINCT id, padre_id, nombre_cientifico, rango FROM ancestros
            SQL, ['{'.implode(',', $ids).'}']))->keyBy('id');
        $validos = array_fill_keys(CalidadDatoPublico::taxonesConLinajeValido($taxones), true);
        $nodos = $rutas = $conteos = [];
        foreach ($filas as $fila) {
            $ruta = []; $visitados = []; $id = $fila->taxon_id;
            while ($id && isset($taxones[$id]) && !isset($visitados[$id]) && count($visitados) < 30) {
                $visitados[$id] = true;
                $t = $taxones[$id];
                array_unshift($ruta, ['id' => $t->id, 'nombre' => $t->nombre_cientifico, 'rango' => mb_strtolower(trim($t->rango))]);
                $id = $t->padre_id;
            }
            if ($id && isset($taxones[$id])) continue;
            $ruta = CalidadDatoPublico::rutaConfirmada($ruta);
            if ($ruta === [] || !isset($validos[$ruta[0]['id']])) continue;
            $iniciado = false; $padre = null; $camino = []; $claves = [];
            foreach ($ruta as $t) {
                if (in_array($t['rango'], ['phylum', 'filo'], true)) $iniciado = true;
                if (!$iniciado || !isset(self::RANGOS[$t['rango']])) continue;
                if (in_array($t['rango'], ['familia', 'family'], true) && !$fila->family_visible) continue;
                if (in_array($t['rango'], ['genero', 'género', 'genus'], true) && !$fila->genus_visible) continue;
                $camino[] = $t['id'];
                $clave = $t['id'].':'.sha1(implode(':', $camino));
                $claves[] = $clave;
                $hoja = in_array($t['rango'], ['especie', 'species', 'subespecie', 'subspecies'], true);
                $nodos[$clave] ??= $t + ['clave' => $clave, 'padre' => $padre, 'etiqueta' => self::RANGOS[$t['rango']],
                    'total' => 0, 'hoja' => $hoja, 'tieneHijos' => false, 'coincide' => false];
                $nodos[$clave]['total'] += (int) $fila->total;
                $conteos[$t['id']] = ($conteos[$t['id']] ?? 0) + (int) $fila->total;
                $rutas[$clave] = $claves;
                if ($padre !== null) $nodos[$padre]['tieneHijos'] = true;
                $padre = $clave;
            }
        }
        // Un mismo taxón puede tener rutas visibles distintas por los permisos de sus ancestros.
        // Su contador siempre representa el filtro por identidad, no sólo una de esas rutas.
        foreach ($nodos as &$nodo) $nodo['total'] = $conteos[$nodo['id']];
        unset($nodo);
        uasort($nodos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']) ?: strcmp($a['clave'], $b['clave']));
        return ['nodos' => $nodos, 'rutas' => $rutas, 'total' => $total];
    }
}
