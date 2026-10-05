<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\NormalizadorNombreCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Cargos de depositantes')]
final class CargosCatalogo extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public ?int $editandoId = null;

    public string $nombre = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function nueva(): void
    {
        $this->editandoId = null;
        $this->nombre = '';
        $this->resetValidation();
        $this->modal('cargo-editor')->show();
    }

    public function editar(int $id): void
    {
        $fila = DB::table('usuarios.cargos_catalogo')->find($id);
        abort_unless($fila, 404);
        $this->editandoId = $id;
        $this->nombre = $fila->nombre;
        $this->resetValidation();
        $this->modal('cargo-editor')->show();
    }

    public function guardar(): void
    {
        $this->nombre = NormalizadorNombreCatalogo::desde('cargo', $this->nombre);
        $this->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        if (DB::table('usuarios.cargos_catalogo')->whereRaw('lower(nombre) = lower(?)', [$this->nombre])
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '<>', $this->editandoId))
            ->exists()) {
            $this->addError('nombre', 'Este cargo ya está registrado.');
            return;
        }

        if ($this->editandoId !== null) {
            DB::table('usuarios.cargos_catalogo')->where('id', $this->editandoId)
                ->update(['nombre' => $this->nombre, 'updated_at' => now()]);
        } else {
            DB::table('usuarios.cargos_catalogo')->insert([
                'nombre' => $this->nombre, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->modal('cargo-editor')->close();
        session()->flash('cargo-guardado', 'El cargo está disponible para los depositantes.');
    }

    public function cambiarEstado(int $id): void
    {
        $fila = DB::table('usuarios.cargos_catalogo')->find($id);
        abort_unless($fila, 404);
        DB::table('usuarios.cargos_catalogo')->where('id', $id)
            ->update(['activo' => ! $fila->activo, 'updated_at' => now()]);
    }

    public function render(): View
    {
        $query = DB::table('usuarios.cargos_catalogo');
        if (trim($this->busqueda) !== '') {
            $query->where('nombre', 'ilike', '%'.trim($this->busqueda).'%');
        }

        return view('livewire.administracion.cargos-catalogo', [
            'cargos' => $query->orderByDesc('activo')->orderBy('nombre')->paginate(20),
            'activos' => DB::table('usuarios.cargos_catalogo')->where('activo', true)->count(),
        ]);
    }
}
