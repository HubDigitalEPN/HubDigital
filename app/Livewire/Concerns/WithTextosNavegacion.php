<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\EditorPortal;
use App\Support\PortalCopy;
use Livewire\Attributes\Locked;

trait WithTextosNavegacion
{
    public bool $textoNavegacionAbierto = false;
    #[Locked] public string $textoNavegacionClave = '', $textoNavegacionVersion = '';
    public string $textoNavegacion = '';

    public function seleccionarTextoNavegacion(string $clave): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        abort_unless(str_starts_with($clave, 'navegacion.') && is_string(config('portal-copy.'.$clave)), 422);
        $this->resetValidation(); $this->textoNavegacionClave = $clave;
        $this->textoNavegacionVersion = EditorPortal::version($clave);
        $this->textoNavegacion = PortalCopy::text($clave); $this->textoNavegacionAbierto = true;
    }

    public function guardarTextoNavegacion(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        abort_unless(str_starts_with($this->textoNavegacionClave, 'navegacion.') && is_string(config('portal-copy.'.$this->textoNavegacionClave)), 422);
        $this->validate(['textoNavegacion' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u']]);
        EditorPortal::guardar($this->textoNavegacionClave, trim($this->textoNavegacion), $this->textoNavegacionVersion);
        $this->textoNavegacionAbierto = false; $this->dispatch('foto-editorial-publicada');
    }
}
