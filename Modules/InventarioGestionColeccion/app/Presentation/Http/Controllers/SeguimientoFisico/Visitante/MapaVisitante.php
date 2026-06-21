<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Visitante;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Página del visitante que aloja el mapa de la colección en modo visitante. Es
 * deliberadamente delgada: monta el componente reutilizable MapaInteractivo y usa
 * un layout mínimo sin la navegación del curador, de modo que el visitante solo
 * puede ver y recorrer el mapa, nada más. El acceso lo controla el middleware
 * `visitante` sobre la ruta.
 */
#[Layout('inventariogestioncoleccion::components.layouts.visitante', params: ['title' => 'Mapa de la colección'])]
final class MapaVisitante extends Component
{
    public function render(): View
    {
        return view('inventariogestioncoleccion::visitante.mapa');
    }
}
