<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Contenidos y textos')]
final class ContenidosTextos extends Component
{
    #[Url]
    public string $seccion = 'portal';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function render()
    {
        $secciones = ['portal' => 'Portales: edición visual'];
        if (in_array(auth()->user()->rolActivo(), [\App\Enums\RolUsuario::ADMIN, \App\Enums\RolUsuario::CURADOR], true)) $secciones['laboratorio'] = 'El Laboratorio: estructura y fotos';
        $secciones['fichas'] = 'Fichas del explorador';
        if (auth()->user()->esAdministrador()) {
            $secciones += ['depositos' => 'Formulario de depósitos', 'sistema' => 'Textos generales del sistema'];
        }
        abort_unless(array_key_exists($this->seccion, $secciones), 403);

        return view('livewire.administracion.contenidos-textos', compact('secciones'));
    }
}
