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

    public array $gridColumnFilters = [];

    public function filtrarColumnaGrilla(string $columna, string $valor, string $operador = 'contains'): void
    {
        abort_unless(array_key_exists($columna, $this->gridColumns()) && mb_strlen($valor) <= 240
            && in_array($operador, ['contains', 'equals', 'starts', 'notContains', 'empty'], true), 422);
        if ($valor === '' && $operador !== 'empty') unset($this->gridColumnFilters[$columna]);
        else $this->gridColumnFilters[$columna] = ['value' => $valor, 'operator' => $operador];
        $this->resetPage();
    }

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
        $this->reset('gridSort', 'gridDirection', 'gridPerPage', 'gridColumnFilters');
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
        foreach ($this->gridColumnFilters as $campo => $filtro) {
            $columnaFiltro = $this->gridColumns()[$campo] ?? null;
            $operador = is_array($filtro) ? ($filtro['operator'] ?? '') : '';
            $valor = is_array($filtro) ? ($filtro['value'] ?? null) : null;
            abort_unless($columnaFiltro && is_string($valor) && mb_strlen($valor) <= 240 && in_array($operador, ['contains', 'equals', 'starts', 'notContains', 'empty'], true), 422);
            $sql = 'LOWER(CAST('.$consulta->getGrammar()->wrap($columnaFiltro).' AS TEXT))';
            if ($operador === 'empty') $consulta->where(fn ($q) => $q->whereNull($columnaFiltro)->orWhereRaw($sql." = ''"));
            elseif ($operador === 'equals') $consulta->whereRaw($sql.' = ?', [mb_strtolower($valor)]);
            else {
                $patron = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($valor));
                $consulta->whereRaw($sql.($operador === 'notContains' ? ' NOT LIKE ?' : ' LIKE ?'), [$operador === 'starts' ? $patron.'%' : '%'.$patron.'%']);
            }
        }
        $columna = $this->gridColumns()[$this->gridSort] ?? $predeterminada;

        return $consulta->orderBy($columna, $this->gridDirection === 'desc' ? 'desc' : 'asc');
    }
}
