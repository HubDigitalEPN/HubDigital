<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Agrega linajes públicos; al cliente entrega únicamente las ramas solicitadas. */
final class ExploradorTaxonomicoPublico
{
    private array $indices = [];

    private const RANGOS = ['phylum' => 'Filo', 'filo' => 'Filo', 'subfilo' => 'Subfilo',
        'clase' => 'Clase', 'class' => 'Clase', 'subclase' => 'Subclase', 'orden' => 'Orden', 'order' => 'Orden',
        'suborden' => 'Suborden', 'infraorden' => 'Infraorden', 'superfamilia' => 'Superfamilia',
        'familia' => 'Familia', 'family' => 'Familia', 'subfamilia' => 'Subfamilia', 'tribu' => 'Tribu',
        'subtribu' => 'Subtribu', 'genero' => 'Género', 'género' => 'Género', 'genus' => 'Género',
        'subgenero' => 'Subgénero', 'especie' => 'Especie', 'species' => 'Especie',
        'subespecie' => 'Subespecie', 'subspecies' => 'Subespecie'];

    public function consultar(FiltrosBusqueda $filtros, ?string $padre = null, string $busqueda = '', array $conservar = []): array
    {
        $indice = $this->indice($filtros);
        $nodos = $indice['nodos'];
        $busqueda = mb_substr(trim($busqueda), 0, 120);
        $expandidos = [];
        $hayMas = false;
        if ($busqueda !== '') {
            // Cada búsqueda consulta PostgreSQL; no busca en una copia cliente del catálogo.
            $normalizada = Str::lower(Str::ascii($busqueda));
            $equivalentes = [];
            if (mb_strlen($normalizada) >= 2) {
                foreach (config('portal-taxonomy-aliases', []) as $nombre => $alias) {
                    if (array_any($alias, static fn (string $texto): bool => str_contains(Str::lower(Str::ascii($texto)), $normalizada))) {
                        $equivalentes[] = Str::lower($nombre);
                    }
                }
            }
            $ids = DB::table('taxonomia.taxones')->whereIn('id', array_column($nodos, 'id'))
                ->where(function ($consulta) use ($normalizada, $equivalentes): void {
                    $consulta->whereRaw("translate(lower(nombre_cientifico), 'áéíóúüñ', 'aeiouun') LIKE ?", ['%'.addcslashes($normalizada, '\\%_').'%']);
                    if ($equivalentes !== []) $consulta->orWhereIn(DB::raw('lower(nombre_cientifico)'), $equivalentes);
                })
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
        // Reabrir conserva sólo nodos y rutas que siguen siendo públicos en este contexto.
        // El cliente aporta claves, nunca nombres ni contadores que debamos confiar.
        $claves = array_fill_keys(array_slice(array_filter($conservar, 'is_string'), 0, 5000), true);
        foreach (array_intersect_key($nodos, $claves) as $clave => $nodo) {
            foreach ($indice['rutas'][$clave] as $ancestro) $resultado[$ancestro] ??= $nodos[$ancestro];
        }
        // Las claves conservadas son las ramas abiertas. Incluir sus hijos
        // mantiene la navegación incluso si una rama supera la precarga limitada.
        foreach ($nodos as $clave => $nodo) {
            if ($nodo['padre'] !== null && isset($claves[$nodo['padre']])) $resultado[$clave] ??= $nodo;
        }
        // La misma lectura entrega los hijos inmediatos de las ramas visibles.
        // No se expande ninguna rama todavía: su primer clic reutiliza estos datos.
        $precarga = [];
        if ($busqueda === '') {
            $restantes = 300;
            $porPadre = [];
            foreach ($nodos as $nodo) if ($nodo['padre'] !== null) $porPadre[$nodo['padre']][] = $nodo;
            foreach ($resultado as $clave => $nodo) {
                $hijos = $porPadre[$clave] ?? [];
                if ($hijos === [] || count($hijos) > $restantes) continue;
                $precarga[$clave] = $hijos;
                $restantes -= count($hijos);
            }
        }
        return ['nodos' => array_values($resultado), 'expandidos' => array_keys($expandidos), 'precarga' => $precarga,
            'hayMas' => $hayMas, 'totalCatalogo' => $indice['total']];
    }

    public function taxon(FiltrosBusqueda $filtros, string $id): ?array
    {
        if (!Str::isUuid($id)) return null;
        $id = strtolower($id);
        return array_find($this->indice($filtros)['nodos'], static fn (array $n): bool => $n['id'] === $id);
    }

    /** Estructura agregada y pública para el visor local; nunca contiene ejemplares. */
    public function estructura(FiltrosBusqueda $filtros): array
    {
        $indice = $this->indice($filtros);
        return ['nodos' => array_values($indice['nodos']), 'expandidos' => [], 'precarga' => [],
            'hayMas' => false, 'totalCatalogo' => $indice['total'], 'completo' => true];
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
        // Los triggers existentes cambian la revisión por taxonomía, registros
        // y permisos de divulgación. La lectura nunca reutiliza otra revisión.
        $revision = (int) DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version');
        $clave = 'portal:indice-taxonomico:v3:'.$revision.':'.sha1(json_encode($filtros));
        if (isset($this->indices[$clave])) return $this->indices[$clave];
        try {
            $guardado = Cache::get($clave);
            if (is_array($guardado)) return $this->indices[$clave] = $guardado;
        } catch (\Throwable $error) {
            Log::warning('Caché del explorador no disponible', ['operacion' => 'leer', 'tipo' => $error::class]);
        }
        $indice = $this->construirIndice($filtros);
        try { Cache::put($clave, $indice, 3600); }
        catch (\Throwable $error) { Log::warning('Caché del explorador no disponible', ['operacion' => 'guardar', 'tipo' => $error::class]); }
        return $this->indices[$clave] = $indice;
    }

    private function construirIndice(FiltrosBusqueda $filtros): array
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
        // La ayuda describe exclusivamente el linaje público de esta ruta.
        $hijos = [];
        foreach ($nodos as $nodo) if ($nodo['padre'] !== null) $hijos[$nodo['padre']] = ($hijos[$nodo['padre']] ?? 0) + 1;
        foreach ($nodos as $clave => &$nodo) {
            $nodo['hijos'] = $hijos[$clave] ?? 0;
            $nodo['linaje'] = implode(' → ', array_map(static fn (string $ancestro): string => $nodos[$ancestro]['nombre'], $rutas[$clave]));
        }
        unset($nodo);
        uasort($nodos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']) ?: strcmp($a['clave'], $b['clave']));
        return ['nodos' => $nodos, 'rutas' => $rutas, 'total' => $total];
    }
}
