<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Services\ColumnasRegistroPublico;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

final class RegistrosTaxonExplorador extends Component
{
    use CargaRegistrosPublicos;

    #[Locked]
    public string $taxonId = '';

    #[Locked]
    public string $nombreTaxon = '';

    #[Locked]
    public int $pagina = 1;

    #[Locked]
    public int $registrosPorPagina = 24;

    #[Locked]
    public ?string $registroFichaId = null;

    #[On('abrir-registros-taxon')]
    public function abrir(string $id): void
    {

        $taxon = app(ExploradorTaxonomicoPublico::class)->taxon(FiltrosBusqueda::vacio(), $id);
        abort_if($taxon === null, 404);
        $this->taxonId = $taxon['id'];
        $this->nombreTaxon = $taxon['nombre'];
        $this->pagina = 1;
        $this->registroFichaId = null;
        $this->dispatch('registros-taxon-abiertos');
    }

    public function cambiarPagina(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
        $this->registroFichaId = null;
        $this->dispatch('pagina-registros-taxon');
    }

    public function ajustarRegistrosPorPagina(int $cantidad): void
    {

        abort_unless(in_array($cantidad, [15, 24, 25, 50, 100], true), 422);
        $this->registrosPorPagina = $cantidad;
        $this->cambiarPagina(1);
    }

    public function abrirFichaRegistro(string $id): void
    {

        abort_unless(Str::isUuid($id) && $this->taxonId !== '', 404);
        abort_unless(app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($this->filtros())
            ->where('te.id', $id)->exists(), 404);
        $this->registroFichaId = $id;
        unset($this->fichaRegistro);
        $this->dispatch('pagina-registros-taxon');
    }

    public function cerrarFichaRegistro(): void
    {
        $this->registroFichaId = null;
        unset($this->fichaRegistro);
        $this->dispatch('pagina-registros-taxon');
    }

    private function filtros(): FiltrosBusqueda
    {
        return FiltrosBusqueda::desde(['filtroTaxonId' => $this->taxonId]);
    }

    #[Computed]
    public function columnasPublicas(): array
    {
        return app(ColumnasRegistroPublico::class)->visibles();
    }

    #[Computed]
    public function fichaRegistro(): ?array
    {

        if ($this->registroFichaId === null || $this->taxonId === '') return null;
        $repositorio = app(EloquentProveedorEspecimenesParaArbol::class);
        if (!$repositorio->consultaPublica($this->filtros())->where('te.id', $this->registroFichaId)->exists()) return null;
        $registro = $this->cargarDetallesPorEspecimenIds([$this->registroFichaId], app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class))[0] ?? null;
        if ($registro === null) return null;
        $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter([$registro->occurrence_id])));
        return ['registro' => $registro, 'fotos' => $imagenes[$registro->occurrence_id ?? ''] ?? [],
            'familia_publica' => $repositorio->familiaPublicaPorEspecimenId($this->registroFichaId, $this->filtros())];
    }

    public function render(): View
    {

        $paginacion = ['ids' => [], 'total' => 0, 'pagina' => 1, 'ultima' => 1];
        if ($this->taxonId !== '') $paginacion = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica($this->filtros(), $this->pagina, tamano: $this->registrosPorPagina);
        $registros = $this->cargarDetallesPorEspecimenIds($paginacion['ids'], app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
        $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registros, 'occurrence_id'))));
        return view('catalogopublico::livewire.registros-taxon-explorador', compact('paginacion', 'registros', 'imagenes'));
    }
}
