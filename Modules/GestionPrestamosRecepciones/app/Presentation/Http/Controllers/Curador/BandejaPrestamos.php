<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos\ConsultarBandejaPrestamosHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos\ConsultarBandejaPrestamosInput;

/**
 * Componente Livewire para la visualización de la bandeja de préstamos activos.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de préstamos'])]
final class BandejaPrestamos extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url(as: 'inv')]
    public string $busquedaInvestigador = '';

    #[Url]
    public string $ordenCampo = 'fecha_inicio';

    #[Url]
    public string $ordenDireccion = 'desc';

    /**
     * @return void
     */
    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    /**
     * @return void
     */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->estado = '';
        $this->busquedaInvestigador = '';
        $this->ordenCampo = 'fecha_inicio';
        $this->ordenDireccion = 'desc';
    }

    /**
     * @param ConsultarBandejaPrestamosHandler $handler
     * @return View
     */
    public function render(ConsultarBandejaPrestamosHandler $handler): View
    {
        $output = $handler->handle(new ConsultarBandejaPrestamosInput(
            busqueda: $this->busqueda,
            estado: $this->estado,
            busquedaInvestigador: $this->busquedaInvestigador,
            ordenCampo: $this->ordenCampo,
            ordenDireccion: $this->ordenDireccion,
        ));

        return view('gestionprestamosrecepciones::curador.bandeja-prestamos', ['prestamos' => $output->filas]);
    }
}
