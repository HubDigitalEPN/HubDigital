<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

trait ExploraCeldaMapa
{
    #[Locked]
    public ?array $celdaMapa = null;

    #[Locked]
    public array $rutaCelda = [];

    #[Locked]
    public string $vistaCelda = 'grupos';

    #[Locked]
    public int $paginaCelda = 1;

    public function abrirCelda(float $latitud, float $longitud): void
    {
        if (! is_finite($latitud) || ! is_finite($longitud) || abs($latitud) > 90 || abs($longitud) > 180) return;
        $this->celdaMapa = ['lat' => $latitud, 'lon' => $longitud];
        $this->rutaCelda = [];
        $this->vistaCelda = 'grupos';
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
        $this->dispatch('abrir-detalle-celda');
    }

    public function cerrarCelda(): void
    {
        $this->celdaMapa = null;
        $this->rutaCelda = [];
        unset($this->detalleCelda);
    }

    public function cambiarVistaCelda(string $vista): void
    {
        if (! in_array($vista, ['grupos', 'registros'], true)) return;
        $this->vistaCelda = $vista;
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    /** Solo permite navegar a las ramas públicas del árbol visible. */
    public function navegarCelda(string $id): void
    {
        $ruta = $this->detalleCelda['rutas'][$id] ?? null;
        if ($ruta === null) return;
        $this->rutaCelda = $ruta;
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    public function volverCelda(int $profundidad): void
    {
        $this->rutaCelda = array_slice($this->rutaCelda, 0, max(0, $profundidad));
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    public function paginarCelda(int $pagina): void
    {
        $this->paginaCelda = max(1, min($pagina, $this->detalleCelda['ultima']));
        unset($this->detalleCelda);
    }

    private function consultaCelda(): Builder
    {
        $consulta = app(EloquentProveedorEspecimenesParaArbol::class)
            ->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon)
            ->where('ed.decimal_latitude_visible', true)->where('ed.decimal_longitude_visible', true);
        if ($this->celdaMapa === null) return $consulta->whereRaw('1 = 0');
        return $consulta->where('te.decimal_latitude', $this->celdaMapa['lat'])
            ->where('te.decimal_longitude', $this->celdaMapa['lon']);
    }

    #[Computed]
    public function detalleCelda(): array
    {
        $base = $this->consultaCelda();
        $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango', 'autor', 'anio_descripcion'])->keyBy('id');
        $filas = (clone $base)->selectRaw('te.taxon_id, ed.scientific_name_visible, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.scientific_name_visible', 'ed.family_visible', 'ed.genus_visible')->get();
        $seleccion = array_column($this->rutaCelda, 'id');
        $grupos = $nodos = $rutasDisponibles = $idsTaxones = [];
        $incluyeReservados = false;
        $directos = $total = 0;
        foreach ($filas as $fila) {
            $ruta = [];
            $id = $fila->scientific_name_visible ? $fila->taxon_id : null;
            $visitados = [];
            while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($visitados) < 30) {
                $visitados[$id] = true;
                $t = $taxones[$id];
                if (($t->rango !== 'familia' || $fila->family_visible) && ($t->rango !== 'genero' || $fila->genus_visible)) {
                    array_unshift($ruta, ['id' => $id, 'nombre' => $t->nombre_cientifico, 'rango' => $t->rango]);
                }
                $id = $t->padre_id;
            }
            foreach ($ruta as $i => $nodo) {
                $idNodo = $nodo['id'];
                $nodos[$idNodo] ??= $nodo + ['padre_id' => $ruta[$i - 1]['id'] ?? null, 'total' => 0];
                $nodos[$idNodo]['total'] += (int) $fila->total;
                $rutasDisponibles[$idNodo] = array_slice($ruta, 0, $i + 1);
            }
            if (array_slice(array_column($ruta, 'id'), 0, count($seleccion)) !== $seleccion) continue;
            $total += (int) $fila->total;
            if ($fila->scientific_name_visible) $idsTaxones[] = $fila->taxon_id;
            else $incluyeReservados = true;
            $hijo = $ruta[count($seleccion)] ?? null;
            if ($hijo === null) { $directos += (int) $fila->total; continue; }
            $grupos[$hijo['id']] ??= $hijo + ['total' => 0];
            $grupos[$hijo['id']]['total'] += (int) $fila->total;
        }
        if ($seleccion !== []) {
            if (in_array('familia', array_column($this->rutaCelda, 'rango'), true)) $base->where('ed.family_visible', true);
            if (in_array('genero', array_column($this->rutaCelda, 'rango'), true)) $base->where('ed.genus_visible', true);
            $base->where(function (Builder $query) use ($idsTaxones, $incluyeReservados): void {
                $query->where(fn (Builder $q) => $q->where('ed.scientific_name_visible', true)->whereIn('te.taxon_id', array_unique($idsTaxones)));
                if ($incluyeReservados) $query->orWhere('ed.scientific_name_visible', false);
            });
        }
        uasort($grupos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
        $totalGrupos = count($grupos);
        $mostrarRegistros = $this->vistaCelda === 'registros' || $grupos === [];
        $ultima = max(1, (int) ceil(($mostrarRegistros ? $total : $totalGrupos) / 12));
        $pagina = min($this->paginaCelda, $ultima);
        $grupos = array_slice($grupos, $mostrarRegistros ? 0 : ($pagina - 1) * 12, 12, true);
        $registros = $imagenes = [];
        if ($mostrarRegistros) {
            $ids = (clone $base)->orderBy('te.fila_origen_excel')->orderBy('te.id')->offset(($pagina - 1) * 12)->limit(12)->pluck('te.id')->all();
            $registros = $this->cargarDetallesPorEspecimenIds($ids, app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
            $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registros, 'occurrence_id'))));
        }
        $arbol = [];
        foreach ($seleccion as $id) if (isset($nodos[$id])) $arbol[$id] = $nodos[$id];
        foreach ($grupos as $id => $grupo) $arbol[$id] = $nodos[$id];
        $idSeleccionado = $seleccion === [] ? null : end($seleccion);
        $seleccionado = $idSeleccionado ? ($nodos[$idSeleccionado] ?? null) : null;
        if ($seleccionado && $totalGrupos === 0) {
            $hermanos = array_filter($nodos, static fn (array $n): bool => $n['padre_id'] === $seleccionado['padre_id'] && $n['id'] !== $seleccionado['id']);
            uasort($hermanos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
            foreach (array_slice($hermanos, 0, 12, true) as $id => $nodo) $arbol[$id] = $nodo;
        }
        $rutas = array_intersect_key($rutasDisponibles, $arbol);
        $informacion = null;
        if ($seleccionado) {
            $rangos = ['reino' => 'kingdom', 'phylum' => 'phylum', 'clase' => 'class', 'orden' => 'order', 'familia' => 'family', 'genero' => 'genus', 'especie' => 'species'];
            $jerarquia = [];
            foreach ($this->rutaCelda as $nodo) if (isset($rangos[$nodo['rango']])) $jerarquia[$rangos[$nodo['rango']]] = $nodo['nombre'];
            $seleccionado['jerarquia'] = $jerarquia;
            $seleccionado['ilustracion'] = IlustracionTaxonomica::paraTaxon($jerarquia + ['nombre' => $seleccionado['nombre'], 'ancestros' => $this->rutaCelda]);
            $informacion = ['taxon' => $seleccionado['nombre'], 'rango' => $seleccionado['rango'], 'cantidad' => $total,
                'jerarquia' => $jerarquia, 'autor' => $taxones[$idSeleccionado]->autor,
                'anio_descripcion' => $taxones[$idSeleccionado]->anio_descripcion];
        }
        $arbol = array_values($arbol);
        return compact('total', 'grupos', 'totalGrupos', 'directos', 'registros', 'imagenes', 'pagina', 'ultima', 'arbol', 'rutas', 'seleccionado', 'informacion');
    }
}
