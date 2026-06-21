<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas\ConsultarBandejaActasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas\ConsultarBandejaActasInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoInput;

/**
 * Componente Livewire para la visualización y gestión de la bandeja de actas de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de actas'])]
final class BandejaActas extends Component
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

    public string $successMessage = '';

    public ?string $pendingActaId = null;

    public bool $showEnviarActaModal = false;

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
     * @param string $actaId
     * @return void
     */
    public function prepararEnvioActa(string $actaId): void
    {
        $this->pendingActaId = $actaId;
        $this->showEnviarActaModal = true;
    }

    /**
     * @param EnviarActaPrestamoHandler $handler
     * @return void
     */
    public function enviarActa(EnviarActaPrestamoHandler $handler): void
    {
        $handler->handle(new EnviarActaPrestamoInput(
            actaId: (string) $this->pendingActaId,
            curadorId: (string) auth()->id(),
        ));

        $this->pendingActaId = null;
        $this->showEnviarActaModal = false;
        $this->successMessage = 'Acta enviada al investigador correctamente.';
    }

    /**
     * @param ConsultarBandejaActasHandler $handler
     * @return View
     */
    public function render(ConsultarBandejaActasHandler $handler): View
    {
        $output = $handler->handle(new ConsultarBandejaActasInput(
            busqueda: $this->busqueda,
            estado: $this->estado,
            busquedaInvestigador: $this->busquedaInvestigador,
            ordenCampo: $this->ordenCampo,
            ordenDireccion: $this->ordenDireccion,
        ));

        return view('gestionprestamosrecepciones::curador.bandeja-actas', ['actas' => $output->filas]);
    }
}
