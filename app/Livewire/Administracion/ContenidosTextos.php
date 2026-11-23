<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithFotosEditoriales;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Contenidos y textos')]
final class ContenidosTextos extends Component
{
    use WithFileUploads, WithFotosEditoriales;

    #[Url]
    public string $seccion = 'portal';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function render()
    {
        $secciones = ['portal' => 'Portales: edición visual'];
        $secciones += ['fichas' => 'Fichas del explorador', 'logos' => 'Logos del sistema'];
        if (auth()->user()->esAdministrador()) {
            $secciones += ['depositos' => 'Formulario de depósitos', 'sistema' => 'Textos generales del sistema'];
        }
        abort_unless(array_key_exists($this->seccion, $secciones) || $this->seccion === 'laboratorio', 403);

        return view('livewire.administracion.contenidos-textos', compact('secciones'));
    }
}
