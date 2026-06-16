<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Visitante;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla mostrada cuando el enlace de acceso del visitante no es válido o ha
 * expirado. No expone ninguna acción salvo el mensaje informativo.
 */
#[Layout('inventariogestioncoleccion::components.layouts.visitante', params: ['title' => 'Acceso no disponible'])]
final class AccesoInvalido extends Component
{
    public function render(): View
    {
        return view('inventariogestioncoleccion::visitante.acceso-invalido');
    }
}
