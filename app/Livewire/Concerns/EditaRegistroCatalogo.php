<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Locked;
use ReflectionMethod;

trait EditaRegistroCatalogo
{
    #[Locked]
    public bool $soloEditorCatalogo = false;

    #[Locked]
    public ?string $accionCatalogo = null;

    #[Locked]
    public ?string $argumentoCatalogo = null;

    public function iniciarEditorCatalogo(): void
    {
        if (!$this->soloEditorCatalogo || $this->accionCatalogo === null) {
            return;
        }
        $metodo = $this->accionCatalogo;
        $this->accionCatalogo = null;
        $argumentos = [];
        if ($this->argumentoCatalogo !== null) {
            $parametro = (new ReflectionMethod($this, $metodo))->getParameters()[0]->getName();
            $argumentos[$parametro] = $this->argumentoCatalogo;
        }
        app()->call([$this, $metodo], $argumentos);
    }

    protected function actualizarGrillaCatalogos(): void
    {
        if ($this->soloEditorCatalogo) {
            $this->dispatch('registro-catalogo-actualizado');
        }
    }
}
