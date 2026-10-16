<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Fuentes de revocación de firmas')]
final class FuentesRevocacionFirma extends Component
{
    use WithInteractiveGrid;
    use WithPagination;

    public string $busqueda = '';

    public ?string $editandoCodigo = null;
    public string $codigo = '';
    public string $entidad = '';
    public string $patronEmisor = '';
    public string $crlUrl = '';
    public string $ocspUrl = '';
    public bool $activo = true;
    public int $tiempoEsperaSegundos = 3;

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
        $this->reset(['editandoCodigo', 'codigo', 'entidad', 'patronEmisor', 'crlUrl', 'ocspUrl']);
        $this->activo = true;
        $this->tiempoEsperaSegundos = 3;
        $this->resetValidation();
        $this->modal('fuente-revocacion-editor')->show();
    }

    public function editar(string $codigo): void
    {
        $fila = DB::table('recepciones.fuentes_revocacion_firma')->where('codigo', $codigo)->first();
        abort_unless($fila, 404);
        $this->editandoCodigo = $fila->codigo;
        $this->codigo = $fila->codigo;
        $this->entidad = $fila->entidad;
        $this->patronEmisor = $fila->patron_emisor;
        $this->crlUrl = $fila->crl_url ?? '';
        $this->ocspUrl = $fila->ocsp_url ?? '';
        $this->activo = (bool) $fila->activo;
        $this->tiempoEsperaSegundos = (int) $fila->tiempo_espera_segundos;
        $this->resetValidation();
        $this->modal('fuente-revocacion-editor')->show();
    }

    public function guardar(): void
    {
        $this->codigo = strtoupper(trim($this->codigo));
        $this->entidad = trim($this->entidad);
        $this->patronEmisor = trim($this->patronEmisor);
        $this->crlUrl = trim($this->crlUrl);
        $this->ocspUrl = trim($this->ocspUrl);

        $this->validate([
            'codigo' => ['required', 'regex:/^[A-Z][A-Z0-9_]{2,49}$/'],
            'entidad' => ['required', 'string', 'min:3', 'max:180'],
            'patronEmisor' => ['required', 'string', 'min:3', 'max:180', 'not_regex:/[|=]/'],
            'crlUrl' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
            'ocspUrl' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\/\//i', 'not_regex:/[|]/'],
            'activo' => ['boolean'],
            'tiempoEsperaSegundos' => ['required', 'integer', 'between:1,5'],
        ], [
            'codigo.regex' => 'Usa letras mayúsculas, números y guiones bajos.',
            'patronEmisor.not_regex' => 'El patrón no puede contener | ni =.',
            'crlUrl.starts_with' => 'La CRL debe publicarse por HTTPS.',
        ]);

        if ($this->editandoCodigo === null && DB::table('recepciones.fuentes_revocacion_firma')
            ->where('codigo', $this->codigo)->exists()) {
            $this->addError('codigo', 'Este código ya está registrado.');
            return;
        }

        $datos = [
            'entidad' => $this->entidad,
            'patron_emisor' => $this->patronEmisor,
            'crl_url' => $this->crlUrl !== '' ? $this->crlUrl : null,
            'ocsp_url' => $this->ocspUrl !== '' ? $this->ocspUrl : null,
            'activo' => $this->activo,
            'tiempo_espera_segundos' => $this->tiempoEsperaSegundos,
            'updated_at' => now(),
        ];
        if ($this->editandoCodigo !== null) {
            DB::table('recepciones.fuentes_revocacion_firma')->where('codigo', $this->editandoCodigo)->update($datos);
        } else {
            DB::table('recepciones.fuentes_revocacion_firma')->insert(['codigo' => $this->codigo, 'created_at' => now()] + $datos);
        }
        $this->modal('fuente-revocacion-editor')->close();
        session()->flash('fuente-guardada', 'La política de revocación quedó guardada.');
    }

    public function cambiarEstado(string $codigo): void
    {
        $fila = DB::table('recepciones.fuentes_revocacion_firma')->where('codigo', $codigo)->first();
        abort_unless($fila, 404);
        DB::table('recepciones.fuentes_revocacion_firma')->where('codigo', $codigo)
            ->update(['activo' => ! $fila->activo, 'updated_at' => now()]);
    }

    public function render(): View
    {
        return view('livewire.administracion.fuentes-revocacion-firma', [
            'fuentes' => $this->ordenarConsultaGrilla(DB::table('recepciones.fuentes_revocacion_firma')
                ->when(trim($this->busqueda) !== '', fn ($query) => $query->where(function ($subquery): void {
                    $subquery->where('entidad', 'ilike', '%'.trim($this->busqueda).'%')->orWhere('patron_emisor', 'ilike', '%'.trim($this->busqueda).'%');
                })), 'entidad')->orderBy('codigo')->paginate($this->filasGrilla()),
        ]);
    }

    protected function gridColumns(): array
    {
        return ['entidad' => 'entidad', 'emisor' => 'patron_emisor', 'estado' => 'activo'];
    }
}
