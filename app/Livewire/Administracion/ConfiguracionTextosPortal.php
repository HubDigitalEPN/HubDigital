<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\PortalCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Textos del portal')]
final class ConfiguracionTextosPortal extends Component
{
    public array $textos = [];
    #[\Livewire\Attributes\Url] public string $grupo = 'inicio';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function mount(): void
    {
        $guardados = PortalCopy::all();
        foreach (config('portal-copy', []) as $grupo => $campos) {
            foreach ($campos as $clave => $defecto) $this->textos[$grupo][$clave] = $guardados[$grupo.'.'.$clave] ?? $defecto;
        }
    }

    public function restaurar(string $grupo, string $clave): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $defecto = config('portal-copy.'.$grupo.'.'.$clave);
        abort_unless(is_string($defecto), 422);
        $this->textos[$grupo][$clave] = $defecto;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $reglas = ['textos' => ['required', 'array']];
        foreach (config('portal-copy', []) as $grupo => $campos) {
            foreach ($campos as $clave => $defecto) $reglas['textos.'.$grupo.'.'.$clave] = ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'];
        }
        $reglas['textos.contacto.email'][] = 'email';
        $reglas['textos.depositos.entrega_contacto'][] = 'email';
        $this->validate($reglas, ['*.required' => 'Completa el texto o restaura el original.', '*.max' => 'El texto admite hasta 5000 caracteres.']);

        DB::transaction(function (): void {
            foreach (config('portal-copy', []) as $grupo => $campos) {
                foreach ($campos as $clave => $defecto) {
                    DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => $grupo.'.'.$clave], [
                        'contenido' => trim($this->textos[$grupo][$clave]), 'updated_by' => auth()->id(), 'updated_at' => now(),
                    ]);
                }
            }
        });
        PortalCopy::forget();
        session()->flash('portal-textos', 'Textos guardados. Se muestran en el portal al recargar la página.');
    }

    public function render(): View
    {
        $grupos = ['inicio' => 'Inicio', 'navegacion' => 'Cabecera y pie de página', 'contacto' => 'Contacto', 'depositos' => 'Portal de depósitos', 'catalogo' => 'Colección Biológica', 'importacion' => 'Importar y actualizar catálogo', 'privacidad' => 'Política de privacidad', 'terminos' => 'Términos de uso', 'licencia' => 'Licencia', 'citar' => 'Cómo citar', 'creditos' => 'Créditos y financiamiento', 'indicadores' => 'Explicaciones de indicadores', 'figuras' => 'Pies de figuras'];
        abort_unless(array_key_exists($this->grupo, $grupos), 422);
        return view('livewire.administracion.configuracion-textos-portal', ['grupos' => $grupos, 'campos' => config('portal-copy.'.$this->grupo, [])]);
    }
}
