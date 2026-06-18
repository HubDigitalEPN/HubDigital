<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Componente Livewire para listar las solicitudes de préstamo del investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Mis solicitudes de préstamo'])]
final class MisSolicitudes extends Component
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

    public ?string $pendingSolicitudId = null;

    public bool $showEnviarSolicitudModal = false;

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
     * Prepara el envío de una solicitud mostrando el modal de confirmación.
     *
     * @param string $id
     * @return void
     */
    public function prepararEnvioSolicitud(string $id): void
    {
        $this->pendingSolicitudId = $id;
        $this->showEnviarSolicitudModal = true;
    }

    /**
     * Envía una solicitud de préstamo.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler $handler
     * @return void
     */
    public function enviarSolicitud(EnviarSolicitudPrestamoHandler $handler): void
    {
        $id = (string) $this->pendingSolicitudId;

        $handler->handle(new EnviarSolicitudPrestamoInput(
            solicitudId: $id,
            investigadorId: (string) auth()->id(),
        ));

        $this->redirectRoute('prestamos.investigador.solicitud.detalle', ['id' => $id], navigate: true);
    }

    /**
     * Renderiza el componente.
     *
     * @return \Illuminate\View\View
     */
    public function render(): View
    {
        $query = SolicitudPrestamoModel::query()
            ->where('investigador_id', (string) auth()->id());

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->where(fn ($q) => $q
                ->where('titulo_estudio', 'ilike', "%{$term}%")
                ->orWhere('numero_solicitud', 'ilike', "%{$term}%")
            );
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        $campo = $this->ordenCampo === 'titulo' ? 'titulo_estudio' : 'created_at';
        $solicitudes = $query->orderBy($campo, $this->ordenDireccion)->get();

        $actasPorSolicitud = ActaPrestamoModel::query()
            ->whereIn('solicitud_prestamo_id', $solicitudes->pluck('id'))
            ->get()
            ->keyBy('solicitud_prestamo_id');

        return view('gestionprestamosrecepciones::investigador.mis-solicitudes', compact('solicitudes', 'actasPorSolicitud'));
    }
}
