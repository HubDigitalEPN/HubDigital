<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Componente Livewire para listar los depósitos del investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Mis Depósitos'])]
final class MisDepositos extends Component
{
    #[Url(as: 'tipo')]
    public string $filtroTipo = '';

    #[Url(as: 'estado')]
    public string $filtroEstado = '';

    #[Url(as: 'desde')]
    public string $filtroDesde = '';

    #[Url(as: 'hasta')]
    public string $filtroHasta = '';

    #[Url(as: 'enProceso')]
    public bool $soloEnProceso = false;

    /**
     * Limpia los filtros de búsqueda.
     */
    public function limpiarFiltros(): void
    {
        $this->filtroTipo = '';
        $this->filtroEstado = '';
        $this->filtroDesde = '';
        $this->filtroHasta = '';
        $this->soloEnProceso = false;
    }

    /**
     * Renderiza el componente.
     */
    public function render(): View
    {
        $erroresFechas = validator(['filtroDesde' => $this->filtroDesde, 'filtroHasta' => $this->filtroHasta], [
            'filtroDesde' => ['nullable', 'date_format:Y-m-d'],
            'filtroHasta' => array_filter(['nullable', 'date_format:Y-m-d', $this->filtroDesde !== '' ? 'after_or_equal:filtroDesde' : null]),
        ], [
            '*.date_format' => 'Usa una fecha válida con año, mes y día.',
            '*.after_or_equal' => 'La fecha final debe ser posterior o igual a la inicial.',
        ])->errors();
        $this->setErrorBag($erroresFechas);
        $estadosActivos = [
            EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
            EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia->value,
            EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value,
            EstadoSolicitudDeposito::RequiereCorreccion->value,
        ];
        $borrador = SolicitudDepositoEloquentModel::query()
            ->where('investigador_id', (string) auth()->id())
            ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->orderByDesc('updated_at')->first(['id', 'numero', 'paso_actual', 'updated_at']);
        $query = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value);

        if ($this->filtroTipo !== '') {
            $query->where('tipo_tramite', $this->filtroTipo);
        }

        if ($this->filtroEstado === 'por_revisar') {
            $query->whereIn('estado', [EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value, EstadoSolicitudDeposito::PendienteDeRevisionDocumentalPrevia->value]);
        } elseif ($this->filtroEstado === 'rechazadas') {
            $query->whereIn('estado', [EstadoSolicitudDeposito::Rechazada->value, EstadoSolicitudDeposito::RechazoPermanente->value]);
        } elseif ($this->filtroEstado !== '') {
            $query->where('estado', $this->filtroEstado);
        }
        if ($this->soloEnProceso) $query->whereIn('estado', $estadosActivos);

        if ($this->filtroDesde !== '' && ! $erroresFechas->has('filtroDesde')) {
            $query->whereDate('created_at', '>=', $this->filtroDesde);
        }

        if ($this->filtroHasta !== '' && ! $erroresFechas->has('filtroHasta')) {
            $query->whereDate('created_at', '<=', $this->filtroHasta);
        }

        $depositos = $query->orderByDesc('created_at')->get();

        $activas = $depositos->whereIn('estado', $estadosActivos);
        $historial = $depositos->whereNotIn('estado', $estadosActivos);

        $hayFiltros = $this->filtroTipo !== '' || $this->filtroEstado !== ''
            || $this->filtroDesde !== '' || $this->filtroHasta !== '' || $this->soloEnProceso;

        // Estado de la recepción física por solicitud (una sola consulta) para rotular
        // en el listado los depósitos ya recibidos y los que tienen acta firmada por descargar.
        $ids = $depositos->pluck('id')->all();
        $recepciones = $ids === []
            ? collect()
            : RecepcionLoteEloquentModel::whereIn('solicitud_deposito_id', $ids)
                ->get(['solicitud_deposito_id', 'estado', 'acta_firmada_ruta'])
                ->keyBy('solicitud_deposito_id');

        return view('gestionprestamosrecepciones::investigador.mis-depositos', compact(
            'depositos', 'activas', 'historial', 'hayFiltros', 'recepciones', 'borrador'
        ));
    }
}
