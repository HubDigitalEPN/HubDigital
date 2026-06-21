<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas\ConsultarBandejaActasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas\ConsultarBandejaActasInput;

/**
 * Componente Livewire para la bandeja de actas del investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Mis actas de préstamo'])]
final class BandejaActas extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $ordenCampo = 'fecha';

    #[Url]
    public string $ordenDireccion = 'desc';

    /**
     * Alterna la dirección del ordenamiento.
     *
     * @return void
     */
    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Limpia los filtros de búsqueda y ordenamiento.
     *
     * @return void
     */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->estado = '';
        $this->ordenCampo = 'fecha';
        $this->ordenDireccion = 'desc';
    }

    /**
     * Renderiza el componente.
     *
     * @return \Illuminate\View\View
     */
    public function render(ConsultarBandejaActasHandler $handler): View
    {
        $output = $handler->handle(new ConsultarBandejaActasInput(
            busqueda: $this->busqueda,
            estado: $this->estado,
            investigadorPropio: (string) auth()->id(),
            ordenCampo: $this->ordenCampo,
            ordenDireccion: $this->ordenDireccion,
        ));

        return view('gestionprestamosrecepciones::investigador.bandeja-actas', ['actas' => $output->filas]);
    }
}
