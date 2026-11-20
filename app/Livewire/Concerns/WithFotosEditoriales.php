<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\PaginaLaboratorio;
use App\Support\EditorPortal;
use App\Support\FotosEditoriales;
use Livewire\Attributes\Locked;

trait WithFotosEditoriales
{
    public bool $fotoEditorialAbierta = false, $restaurarFotoEditorial = false;
    #[Locked] public string $fotoEditorialId = '', $fotoEditorialVersion = '';
    public string $fotoEditorialAlt = '';
    public $fotoEditorial;

    public function seleccionarFotoEditorial(string $id, string $alt = ''): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $clave = FotosEditoriales::clave($id);
        $this->resetValidation();
        $this->fotoEditorialId = $id;
        $this->fotoEditorialVersion = EditorPortal::version($clave);
        $this->fotoEditorialAlt = FotosEditoriales::alt($id, mb_substr($alt, 0, 500));
        $this->fotoEditorial = null;
        $this->restaurarFotoEditorial = false;
        $this->fotoEditorialAbierta = true;
    }

    public function guardarFotoEditorial(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $clave = FotosEditoriales::clave($this->fotoEditorialId);
        $this->validate(['fotoEditorialAlt' => ['required', 'string', 'max:500'], 'restaurarFotoEditorial' => ['boolean'],
            'fotoEditorial' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192', 'dimensions:max_width=4096,max_height=4096']]);
        try {
            $foto = $this->fotoEditorial ? app(PaginaLaboratorio::class)->prepararFoto((string) file_get_contents($this->fotoEditorial->getRealPath()))
                : ($this->restaurarFotoEditorial ? [] : FotosEditoriales::leer($this->fotoEditorialId));
            $foto['alt'] = trim($this->fotoEditorialAlt);
            EditorPortal::guardar($clave, json_encode($foto, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $this->fotoEditorialVersion);
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->addError('fotoEditorial', 'Otra persona cambió esta fotografía. Vuelve a seleccionarla antes de guardar.'); return;
        } catch (\Throwable $error) {
            report($error); $this->addError('fotoEditorial', 'No se pudo publicar la fotografía. Se conserva la anterior.'); return;
        }
        $this->fotoEditorialAbierta = false; $this->fotoEditorial = null;
        $this->dispatch('foto-editorial-publicada');
        session()->flash('foto-editorial-guardada', 'Transacción Efectuada');
    }
}
