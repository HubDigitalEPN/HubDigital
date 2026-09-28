<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\CatalogoLocalidadesEcuador;
use App\Support\CatalogoTerritorialEcuador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Localidades de Ecuador')]
final class LocalidadesEcuadorCatalogo extends Component
{
    use WithPagination;

    public string $provinciaFiltro = '';
    public string $busqueda = '';
    public ?string $editandoCodigo = null;
    public string $provincia = '';
    public string $nombre = '';
    public string $canton = '';
    public string $parroquia = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedProvinciaFiltro(): void
    {
        $this->resetPage();
    }

    public function nueva(): void
    {
        $this->reset('editandoCodigo', 'nombre', 'canton', 'parroquia');
        $this->provincia = $this->provinciaFiltro;
        $this->resetValidation();
        $this->modal('localidad-ecuador-editor')->show();
    }

    public function editar(string $codigo): void
    {
        $fila = DB::table(CatalogoLocalidadesEcuador::TABLA)->where('codigo', $codigo)->first();
        abort_unless($fila, 404);
        $this->editandoCodigo = $fila->codigo;
        $this->provincia = $fila->provincia_codigo;
        $this->nombre = $fila->nombre;
        $this->canton = $fila->canton;
        $this->parroquia = $fila->parroquia;
        $this->resetValidation();
        $this->modal('localidad-ecuador-editor')->show();
    }

    public function guardar(): void
    {
        foreach (['nombre', 'canton', 'parroquia'] as $campo) {
            $this->$campo = trim(preg_replace('/\s+/u', ' ', $this->$campo) ?? '');
        }
        $this->validate([
            'provincia' => ['required', Rule::in(array_column(CatalogoTerritorialEcuador::provincias(), 'codigo'))],
            'nombre' => ['required', 'string', 'min:2', 'max:254'],
            'canton' => ['nullable', 'string', 'max:160'],
            'parroquia' => ['nullable', 'string', 'max:254'],
        ]);
        DB::transaction(function (): void {
            $tabla = DB::table(CatalogoLocalidadesEcuador::TABLA);
            $fila = $this->editandoCodigo === null ? null
                : (clone $tabla)->where('codigo', $this->editandoCodigo)->lockForUpdate()->first();
            abort_if($this->editandoCodigo !== null && $fila === null, 404);
            if ($fila !== null && $fila->provincia_codigo !== $this->provincia
                && DB::table('recepciones.solicitudes_deposito')->where('localidad_origen_codigo', $fila->codigo)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'provincia' => 'Esta localidad ya está vinculada a expedientes. Crea otra localidad en la nueva provincia.',
                ]);
            }
            $valores = ['provincia_codigo' => $this->provincia, 'nombre' => $this->nombre,
                'canton' => $this->canton, 'parroquia' => $this->parroquia,
                'busqueda' => CatalogoLocalidadesEcuador::normalizar($this->nombre.' '.$this->canton.' '.$this->parroquia),
                'updated_at' => now()];
            if ($fila !== null) {
                (clone $tabla)->where('codigo', $fila->codigo)->update($valores);
            } else {
                (clone $tabla)->insert([...$valores, 'codigo' => 'ADM-'.Str::uuid(),
                    'fuente' => 'Alta administrativa HubDigital', 'activo' => true, 'created_at' => now()]);
            }
        });
        $this->modal('localidad-ecuador-editor')->close();
        session()->flash('localidad-guardada', 'La localidad está disponible en la lista del paso 2.');
    }

    public function cambiarEstado(string $codigo): void
    {
        DB::transaction(function () use ($codigo): void {
            $fila = DB::table(CatalogoLocalidadesEcuador::TABLA)->where('codigo', $codigo)->lockForUpdate()->first();
            abort_unless($fila, 404);
            DB::table(CatalogoLocalidadesEcuador::TABLA)->where('codigo', $codigo)
                ->update(['activo' => ! $fila->activo, 'updated_at' => now()]);
        });
    }

    public function render(): View
    {
        $query = DB::table(CatalogoLocalidadesEcuador::TABLA);
        if ($this->provinciaFiltro !== '') {
            $query->where('provincia_codigo', $this->provinciaFiltro);
        }
        $texto = CatalogoLocalidadesEcuador::normalizar($this->busqueda);
        foreach (array_filter(explode(' ', $texto)) as $palabra) {
            $query->where('busqueda', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $palabra).'%');
        }

        return view('livewire.administracion.localidades-ecuador-catalogo', [
            'localidades' => $query->orderBy('nombre')->orderBy('codigo')->paginate(25),
            'provincias' => CatalogoTerritorialEcuador::provincias(),
            'nombresProvincias' => array_column(CatalogoTerritorialEcuador::provincias(), 'nombre', 'codigo'),
        ]);
    }
}
