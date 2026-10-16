<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Grupos animales de depósitos')]
final class GruposAnimalesCatalogo extends Component
{
    use WithInteractiveGrid;
    use WithPagination;

    public string $busqueda = '';
    public ?string $editandoCodigo = null;
    public string $codigo = '';
    public string $nombre = '';
    public string $rango = 'phylum';

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function nuevo(): void
    {
        $this->reset(['editandoCodigo', 'codigo', 'nombre']);
        $this->rango = 'phylum';
        $this->resetValidation();
        $this->modal('grupo-animal-editor')->show();
    }

    public function editar(string $codigo): void
    {
        $fila = DB::table('recepciones.catalogo_grupos_invertebrados')->where('codigo', $codigo)->first();
        abort_unless($fila, 404);
        $this->editandoCodigo = $codigo;
        $this->codigo = $fila->codigo;
        $this->nombre = $fila->nombre;
        $this->rango = $fila->rango_referencia;
        $this->resetValidation();
        $this->modal('grupo-animal-editor')->show();
    }

    public function guardar(): void
    {
        $this->codigo = mb_strtoupper(trim($this->codigo));
        $this->nombre = trim(preg_replace('/\s+/u', ' ', $this->nombre) ?? '');
        $this->validate([
            'codigo' => ['required', 'regex:/^[A-Z][A-Z0-9_]{2,49}$/'],
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
            'rango' => ['required', Rule::in(['phylum', 'subphylum', 'class', 'order', 'family'])],
        ], [
            'codigo.regex' => 'Usa el nombre científico en mayúsculas, sin espacios ni tildes.',
            'codigo.unique' => 'Este código ya existe.',
            'nombre.unique' => 'Este grupo ya existe.',
        ]);

        $duplicado = DB::table('recepciones.catalogo_grupos_invertebrados')
            ->when($this->editandoCodigo !== null, fn ($q) => $q->where('codigo', '<>', $this->editandoCodigo));
        if ((clone $duplicado)->where('codigo', $this->codigo)->exists()) {
            $this->addError('codigo', 'Este código ya existe.');
            return;
        }
        if ((clone $duplicado)->where('nombre', $this->nombre)->exists()) {
            $this->addError('nombre', 'Este grupo ya existe.');
            return;
        }

        $datos = ['nombre' => $this->nombre, 'rango_referencia' => $this->rango, 'updated_at' => now()];
        if ($this->editandoCodigo !== null) {
            DB::table('recepciones.catalogo_grupos_invertebrados')->where('codigo', $this->editandoCodigo)->update($datos);
        } else {
            DB::table('recepciones.catalogo_grupos_invertebrados')->insert($datos + [
                'codigo' => $this->codigo,
                'orden_visual' => (int) DB::table('recepciones.catalogo_grupos_invertebrados')->max('orden_visual') + 10,
                'activo' => true,
                'created_at' => now(),
            ]);
        }
        $this->modal('grupo-animal-editor')->close();
    }

    public function cambiarEstado(string $codigo): void
    {
        $fila = DB::table('recepciones.catalogo_grupos_invertebrados')->where('codigo', $codigo)->first();
        abort_unless($fila, 404);
        DB::table('recepciones.catalogo_grupos_invertebrados')->where('codigo', $codigo)
            ->update(['activo' => ! $fila->activo, 'updated_at' => now()]);
    }

    public function render(): View
    {
        $consulta = DB::table('recepciones.catalogo_grupos_invertebrados');
        if (trim($this->busqueda) !== '') {
            $consulta->where(function ($q): void {
                $q->where('nombre', 'ilike', '%'.trim($this->busqueda).'%')
                    ->orWhere('codigo', 'ilike', '%'.trim($this->busqueda).'%');
            });
        }

        return view('livewire.administracion.grupos-animales-catalogo', [
            'grupos' => $this->ordenarConsultaGrilla($consulta, 'nombre')->orderBy('codigo')->paginate($this->filasGrilla()),
        ]);
    }

    protected function gridColumns(): array
    {
        return ['nombre' => 'nombre', 'codigo' => 'codigo', 'rango' => 'rango_referencia', 'estado' => 'activo'];
    }
}
