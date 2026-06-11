<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Componente Livewire para la visualización del panel principal de préstamos.
 */
#[Layout('layouts.app')]
#[Title('Préstamos')]
class PanelPrestamos extends Component
{
    /**
     * @return View
     */
    public function render(): View
    {
        return view('gestionprestamosrecepciones::curador.panel-prestamos');
    }
}
