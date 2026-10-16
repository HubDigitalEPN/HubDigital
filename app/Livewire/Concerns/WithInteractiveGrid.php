<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait WithInteractiveGrid
{
    public string $gridSort = '';

    public string $gridDirection = 'asc';

    public int $gridPerPage = 15;

    /** @return array<string, string> */
    abstract protected function gridColumns(): array;

    public function ordenarGrilla(string $columna): void
    {
        abort_unless(array_key_exists($columna, $this->gridColumns()), 422);
        $this->gridDirection = $this->gridSort === $columna && $this->gridDirection === 'asc' ? 'desc' : 'asc';
        $this->gridSort = $columna;
        $this->resetPage();
    }

    public function aplicarOrdenGrilla(string $columna, string $direccion): void
    {
        abort_unless(array_key_exists($columna, $this->gridColumns()) && in_array($direccion, ['asc', 'desc'], true), 422);
        $this->gridSort = $columna;
        $this->gridDirection = $direccion;
        $this->resetPage();
    }

    public function buscarGrilla(): void
    {
        $this->resetPage();
    }

    public function updatedGridPerPage(): void
    {
        $this->gridPerPage = $this->filasGrilla();
        $this->resetPage();
    }

    public function restablecerGrilla(): void
    {
        $this->reset('gridSort', 'gridDirection', 'gridPerPage');
        foreach (['busqueda', 'filtroRol', 'provinciaFiltro'] as $filtro) {
            if (property_exists($this, $filtro)) {
                $this->reset($filtro);
            }
        }
        $this->resetPage();
    }

    protected function filasGrilla(): int
    {
        return in_array($this->gridPerPage, [15, 25, 50, 100], true) ? $this->gridPerPage : 15;
    }

    protected function ordenarConsultaGrilla(EloquentBuilder|QueryBuilder $consulta, string $predeterminada): EloquentBuilder|QueryBuilder
    {
        $columna = $this->gridColumns()[$this->gridSort] ?? $predeterminada;

        return $consulta->orderBy($columna, $this->gridDirection === 'desc' ? 'desc' : 'asc');
    }
}
