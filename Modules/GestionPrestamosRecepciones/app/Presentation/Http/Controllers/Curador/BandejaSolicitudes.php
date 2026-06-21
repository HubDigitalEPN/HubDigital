<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes\ConsultarBandejaSolicitudesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes\ConsultarBandejaSolicitudesInput;

/**
 * Componente Livewire para la gestión y listado de solicitudes de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de solicitudes'])]
final class BandejaSolicitudes extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url(as: 'inv')]
    public string $busquedaInvestigador = '';

    #[Url]
    public string $ordenCampo = 'fecha';

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
        $this->ordenCampo = 'fecha';
        $this->ordenDireccion = 'desc';
    }

    /**
     * @param ConsultarBandejaSolicitudesHandler $handler
     * @return View
     */
    public function render(ConsultarBandejaSolicitudesHandler $handler): View
    {
        $output = $handler->handle(new ConsultarBandejaSolicitudesInput(
            busqueda: $this->busqueda,
            estado: $this->estado,
            busquedaInvestigador: $this->busquedaInvestigador,
            ordenCampo: $this->ordenCampo,
            ordenDireccion: $this->ordenDireccion,
        ));

        return view('gestionprestamosrecepciones::curador.bandeja-solicitudes', ['solicitudes' => $output->filas]);
    }
}
