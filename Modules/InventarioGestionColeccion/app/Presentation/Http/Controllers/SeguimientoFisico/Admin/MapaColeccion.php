<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Página de curador que aloja el mapa interactivo de la colección. Es deliberadamente
 * delgada: solo define la ruta, el layout y el acceso (role:curador). Toda la lógica
 * del mapa vive en el componente reutilizable MapaInteractivo, que el portal público
 * podrá montar más adelante con su propio modo.
 */
#[Layout('layouts.app', params: ['title' => 'Mapa de la colección'])]
final class MapaColeccion extends Component
{
    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.mapa');
    }
}
