<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
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
    }

    public function cambiarVistaCelda(string $vista): void
    {
        if (! in_array($vista, ['grupos', 'registros'], true)) return;
        $this->vistaCelda = $vista;
        $this->paginaCelda = 1;
    }

    public function navegarCelda(string $id): void
    {
        foreach ($this->detalleCelda['grupos'] as $grupo) {
            if ($grupo['id'] !== $id) continue;
            $this->rutaCelda[] = ['id' => $id, 'nombre' => $grupo['nombre']];
            $this->paginaCelda = 1;
            unset($this->detalleCelda);
            return;
        }
    }

    public function volverCelda(int $profundidad): void
    {
        $this->rutaCelda = array_slice($this->rutaCelda, 0, max(0, $profundidad));
        $this->paginaCelda = 1;
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
        $rangos = DB::table('taxonomia.taxones')->whereIn('id', array_column($this->rutaCelda, 'id'))->pluck('rango');
        if ($rangos->contains('familia')) $consulta->where('ed.family_visible', true);
        if ($rangos->contains('genero')) $consulta->where('ed.genus_visible', true);

        return $consulta->whereRaw('ROUND((te.decimal_latitude * 4)::numeric) / 4 = ?', [$this->celdaMapa['lat']])
            ->whereRaw('ROUND((te.decimal_longitude * 4)::numeric) / 4 = ?', [$this->celdaMapa['lon']]);
    }

    #[Computed]
    public function detalleCelda(): array
    {
        $base = $this->consultaCelda();
        $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango'])->keyBy('id');
        $filas = (clone $base)->selectRaw('te.taxon_id, ed.scientific_name_visible, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.scientific_name_visible', 'ed.family_visible', 'ed.genus_visible')->get();
        $seleccion = array_column($this->rutaCelda, 'id');
        $grupos = [];
        $idsTaxones = [];
        $incluyeReservados = false;
        $directos = 0;
        foreach ($filas as $fila) {
            $ruta = [];
            $id = $fila->scientific_name_visible ? $fila->taxon_id : null;
            $visitados = [];
            while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($ruta) < 30) {
                $visitados[$id] = true;
                if (($taxones[$id]->rango !== 'familia' || $fila->family_visible)
                    && ($taxones[$id]->rango !== 'genero' || $fila->genus_visible)) array_unshift($ruta, $id);
                $id = $taxones[$id]->padre_id;
            }
            if (array_slice($ruta, 0, count($seleccion)) !== $seleccion) continue;
            if ($fila->scientific_name_visible) $idsTaxones[] = $fila->taxon_id;
            else $incluyeReservados = true;
            $hijo = $ruta[count($seleccion)] ?? null;
            if ($hijo === null) {
                $directos += (int) $fila->total;
                continue;
            }
            $grupos[$hijo] ??= ['id' => $hijo, 'nombre' => $taxones[$hijo]->nombre_cientifico, 'rango' => $taxones[$hijo]->rango, 'total' => 0];
            $grupos[$hijo]['total'] += (int) $fila->total;
        }
        if ($seleccion !== []) {
            $base->where(function (Builder $query) use ($idsTaxones, $incluyeReservados): void {
                $query->where(fn (Builder $q) => $q->where('ed.scientific_name_visible', true)->whereIn('te.taxon_id', $idsTaxones));
                if ($incluyeReservados) $query->orWhere('ed.scientific_name_visible', false);
            });
        }
        $total = (clone $base)->count();
        $ultima = max(1, (int) ceil($total / 12));
        $pagina = min($this->paginaCelda, $ultima);
        $registros = $imagenes = [];
        if ($this->vistaCelda === 'registros' || $grupos === []) {
            $ids = (clone $base)->orderBy('te.fila_origen_excel')->orderBy('te.id')->offset(($pagina - 1) * 12)->limit(12)->pluck('te.id')->all();
            $registros = $this->cargarDetallesPorEspecimenIds($ids, app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
            $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registros, 'occurrence_id'))));
        }
        uasort($grupos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));

        return compact('total', 'grupos', 'directos', 'registros', 'imagenes', 'pagina', 'ultima');
    }
}
