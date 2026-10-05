<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\NormalizadorNombreCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Instituciones de depositantes')]
final class InstitucionesCatalogo extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $busquedaPendientes = '';

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
        $this->modal('institucion-editor')->show();
    }

    public function editar(int $id): void
    {
        $fila = DB::table('usuarios.instituciones_catalogo')->find($id);
        abort_unless($fila, 404);
        $this->editandoId = $id;
        $this->nombre = $fila->nombre;
        $this->resetValidation();
        $this->modal('institucion-editor')->show();
    }

    public function guardar(): void
    {
        $this->nombre = NormalizadorNombreCatalogo::desde('institucion', $this->nombre);
        $this->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
        ], ['nombre.unique' => 'Esta institución ya está registrada.']);

        if (DB::table('usuarios.instituciones_catalogo')->whereRaw('lower(nombre) = lower(?)', [$this->nombre])
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '<>', $this->editandoId))
            ->exists()) {
            $this->addError('nombre', 'Esta institución ya está registrada.');
            return;
        }

        if ($this->editandoId !== null) {
            DB::table('usuarios.instituciones_catalogo')->where('id', $this->editandoId)
                ->update(['nombre' => $this->nombre, 'updated_at' => now()]);
        } else {
            DB::table('usuarios.instituciones_catalogo')->insert([
                'nombre' => $this->nombre, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->modal('institucion-editor')->close();
        session()->flash('institucion-guardada', 'La institución está disponible en el paso 4.');
    }

    public function cambiarEstado(int $id): void
    {
        $fila = DB::table('usuarios.instituciones_catalogo')->find($id);
        abort_unless($fila, 404);
        DB::table('usuarios.instituciones_catalogo')->where('id', $id)
            ->update(['activo' => ! $fila->activo, 'updated_at' => now()]);
    }

    public function render(): View
    {
        $query = DB::table('usuarios.instituciones_catalogo');
        if (trim($this->busqueda) !== '') {
            $query->where('nombre', 'ilike', '%'.trim($this->busqueda).'%');
        }

        return view('livewire.administracion.instituciones-catalogo', [
            'instituciones' => $query->orderByDesc('activo')->orderBy('nombre')->paginate(20),
            'activas' => DB::table('usuarios.instituciones_catalogo')->where('activo', true)->count(),
            'pendientes' => Schema::hasTable('usuarios.catalogos_nombres_pendientes')
                ? DB::table('usuarios.catalogos_nombres_pendientes')->where('tipo', 'institucion')->where('resuelto', false)
                    ->when(trim($this->busquedaPendientes) !== '', fn ($q) => $q->where('valor_original', 'ilike', '%'.trim($this->busquedaPendientes).'%'))
                    ->orderBy('valor_original')->get()
                : collect(),
        ]);
    }
}
