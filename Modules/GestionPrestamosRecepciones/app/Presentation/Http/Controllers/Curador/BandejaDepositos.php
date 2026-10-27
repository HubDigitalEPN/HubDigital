<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Componente Livewire para la bandeja de revisión documental de solicitudes de
 * depósito y donación pendientes de curaduría.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de recepciones'])]
final class BandejaDepositos extends Component
{
    use HandlesDomainExceptions;

    /** Vista activa: revisión documental, actas pendientes o historial resuelto. */
    #[Url]
    public string $vista = 'pendientes';

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $tipoTramite = '';

    #[Url]
    public string $ordenDireccion = 'desc';

    /**
     * Cambia entre la cola por revisar y el historial de resueltas.
     */
    public function cambiarVista(string $vista): void
    {
        $this->vista = in_array($vista, ['pendientes', 'actas', 'resueltas'], true)
            ? $vista
            : 'pendientes';
    }

    /**
     * Restablece todos los filtros a su valor por defecto.
     */
    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'tipoTramite');
        $this->ordenDireccion = 'desc';
    }

    /**
     * Alterna la dirección de orden por fecha de la cola.
     */
    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Clasifica una solicitud como prioritaria, posicionándola al inicio de la cola.
     */
    public function priorizar(string $id, PriorizarSolicitudEnColaHandler $handler): void
    {
        ($handler)(new PriorizarSolicitudEnColaInput(
            solicitudId: $id,
            prioridad: PrioridadSolicitud::Prioritaria->value,
        ));

        $this->dispatch('toast', message: 'Solicitud marcada como prioritaria.');
    }

    /**
     * Renderiza la bandeja con los filtros aplicados.
     *
     * Lectura directa vía Eloquent siguiendo el patrón de los componentes de depósito
     * del módulo (MisDepositos, DetalleDeposito).
     */
    public function render(UsuarioNombrePort $usuarios): View
    {
        $esResueltas = $this->vista === 'resueltas';
        $esActas = $this->vista === 'actas';

        $estadosRecepcionConstatada = [
            EstadoRecepcionLote::VerificadoFisicamente->value,
            EstadoRecepcionLote::VerificadoConObservaciones->value,
        ];

        // El receptor termina aquí su trabajo. Desde este momento la acción está
        // en curaduría hasta que el acta final quede firmada electrónicamente.
        $recepcionesPendientes = RecepcionLoteEloquentModel::query()
            ->whereIn('estado', $estadosRecepcionConstatada)
            ->whereNull('acta_firmada_ruta')
            ->orderByDesc('verificado_en')
            ->get()
            ->keyBy('solicitud_deposito_id');

        $idsActasPendientes = $recepcionesPendientes->keys()->all();

        // Pendientes: cola por revisar. Resueltas: solicitudes ya decididas por el curador.
        $estadosResueltas = [
            EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
            EstadoSolicitudDeposito::RequiereCorreccion->value,
            EstadoSolicitudDeposito::RechazoPermanente->value,
            EstadoSolicitudDeposito::Rechazada->value,
        ];

        // Búsqueda por nombre del depositante: se resuelven primero los IDs de usuario
        // cuyo nombre coincide, ya que el nombre no vive en la tabla de solicitudes.
        $idsPorNombre = $this->busqueda !== '' ? $usuarios->buscarIdsPorNombre($this->busqueda) : [];

        $query = SolicitudDepositoEloquentModel::query()
            ->when($this->tipoTramite !== '', fn ($q) => $q->where('tipo_tramite', $this->tipoTramite))
            ->when($this->busqueda !== '', function ($q) use ($idsPorNombre): void {
                $q->where(function ($sub) use ($idsPorNombre): void {
                    $sub->where('numero', 'ilike', '%'.$this->busqueda.'%')
                        ->orWhere('nombre_investigador_documento', 'ilike', '%'.$this->busqueda.'%');

                    if ($idsPorNombre !== []) {
                        $sub->orWhereIn('investigador_id', $idsPorNombre);
                    }
                });
            });

        if ($esActas) {
            $query->whereIn('id', $idsActasPendientes);
        } elseif ($esResueltas) {
            $query->whereIn('estado', $estadosResueltas);
        } else {
            $query->whereIn('estado', [
                EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia->value,
                EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
            ]);
        }

        $solicitudes = ($esResueltas || $esActas)
            ? $query->orderBy('updated_at', $this->ordenDireccion === 'asc' ? 'asc' : 'desc')->get()
            : $query
                ->orderByRaw('CASE WHEN prioridad = ? THEN 0 ELSE 1 END', [PrioridadSolicitud::Prioritaria->value])
                ->orderBy('created_at', $this->ordenDireccion === 'asc' ? 'asc' : 'desc')
                ->get();

        $idsInvestigadores = $solicitudes
            ->pluck('investigador_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nombres = $idsInvestigadores !== [] ? $usuarios->obtenerNombres($idsInvestigadores) : [];

        $hayFiltros = $this->busqueda !== '' || $this->tipoTramite !== '';

        // Total de pendientes por revisar (independiente de los filtros) para el contador.
        $totalPendientes = SolicitudDepositoEloquentModel::query()
            ->whereIn('estado', [
                EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia->value,
                EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
            ])
            ->count();

        $totalActasPendientes = $recepcionesPendientes->count();

        // Solo se exponen al Blade las recepciones que aún requieren actuación del
        // curador; esto permite destacar el mismo acceso directo también en el historial.
        $recepciones = $recepcionesPendientes;
        $recepcionesFirmadas = RecepcionLoteEloquentModel::query()
            ->whereIn('solicitud_deposito_id', $solicitudes->pluck('id'))
            ->whereNotNull('firmada_en')->whereNotNull('acta_firmada_ruta')->where('acta_firmada_ruta', '<>', '')
            ->get()->keyBy('solicitud_deposito_id');

        return view('gestionprestamosrecepciones::curador.bandeja-depositos', compact(
            'solicitudes', 'nombres', 'hayFiltros', 'esResueltas', 'esActas',
            'totalPendientes', 'totalActasPendientes', 'recepciones', 'recepcionesFirmadas',
        ));
    }
}
