<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(
        ListarCajasHandler $cajasHandler,
        ListarAlertasHandler $alertasHandler,
    ): View {
        $user = Auth::user();

        return match ($user->rol) {
            RolUsuario::CURADOR => view('livewire.dashboard.curador-panel', [
                'statSolicitudesPendientes' => SolicitudPrestamoModel::query()
                    ->where('estado', 'enviada')
                    ->count(),
                'statAprobadas' => SolicitudPrestamoModel::query()
                    ->where('estado', 'aprobada')
                    ->count(),
                'statActasPorValidar' => ActaPrestamoModel::query()
                    ->where('estado', 'pendiente_validacion')
                    ->count(),
                ...$this->resumenSeguimientoFisico($cajasHandler, $alertasHandler),
            ]),
            RolUsuario::PRESTAMISTA => view('livewire.dashboard.prestamista-panel', [
                'statTotal' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->count(),
                'statAprobadas' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->where('estado', 'aprobada')
                    ->count(),
                'statPendientes' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->whereIn('estado', ['enviada', 'observada'])
                    ->count(),
            ]),
            RolUsuario::DEPOSITANTE => view(
                'livewire.dashboard.depositante-panel',
                $this->estadisticasDepositante((string) $user->id),
            ),
        };
    }

    /**
     * Métricas agregadas de los aportes del depositante para su dashboard.
     *
     * Mismo criterio que MisDepositos: solo solicitudes del investigador
     * autenticado que ya salieron de "En Borrador".
     *
     * @return array<string, mixed>
     */
    private function estadisticasDepositante(string $investigadorId): array
    {
        $base = SolicitudDepositoEloquentModel::query()
            ->where('investigador_id', $investigadorId)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value);

        $pendiente = EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value;
        $pausada = EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value;
        $rechazada = EstadoSolicitudDeposito::Rechazada->value;

        // Una sola consulta para sumas, conteos por estado y diversidad.
        $resumen = (clone $base)->selectRaw(
            'COUNT(*) AS total_enviadas,
             COALESCE(SUM(nro_individuos), 0) AS individuos,
             COALESCE(SUM(nro_morfoespecies), 0) AS morfoespecies,
             COALESCE(SUM(nro_lotes), 0) AS lotes,
             COUNT(DISTINCT grupo_animal) AS grupos_taxonomicos,
             COUNT(DISTINCT provincia_origen) AS provincias_origen,
             COUNT(*) FILTER (WHERE estado = ?) AS pendientes_revision,
             COUNT(*) FILTER (WHERE estado = ?) AS pausadas_asesoria,
             COUNT(*) FILTER (WHERE estado = ?) AS rechazadas',
            [$pendiente, $pausada, $rechazada],
        )->first();

        $depositosAnio = (clone $base)
            ->where('tipo_tramite', TipoTramite::Deposito->value)
            ->whereYear('created_at', now()->year)
            ->count();

        $donacionesRealizadas = (clone $base)
            ->where('tipo_tramite', TipoTramite::Donacion->value)
            ->count();

        return [
            'totalEnviadas' => (int) $resumen->total_enviadas,
            'individuos' => (int) $resumen->individuos,
            'morfoespecies' => (int) $resumen->morfoespecies,
            'lotes' => (int) $resumen->lotes,
            'gruposTaxonomicos' => (int) $resumen->grupos_taxonomicos,
            'provinciasOrigen' => (int) $resumen->provincias_origen,
            'pendientesRevision' => (int) $resumen->pendientes_revision,
            'pausadasAsesoria' => (int) $resumen->pausadas_asesoria,
            'rechazadas' => (int) $resumen->rechazadas,
            'depositosAnio' => $depositosAnio,
            'cupoMaximoDepositos' => 3,
            'donacionesRealizadas' => $donacionesRealizadas,
        ];
    }

    /**
     * @return array{statCajasTotal: int, statCajasFueraDeLugar: int, statAlertasActivas: int}
     */
    private function resumenSeguimientoFisico(
        ListarCajasHandler $cajasHandler,
        ListarAlertasHandler $alertasHandler,
    ): array {
        $cajas = $cajasHandler->handle()->items;

        // Una caja está "fuera de su lugar" solo cuando no está alojada en una ranura.
        // en_gabinete, ubicacion_incorrecta y pendiente_clasificacion siguen presentes en
        // su ranura (banderas de negocio), por lo que NO cuentan como ausentes.
        $estadosFueraDeRanura = ['en_transito', 'extraccion_prolongada', 'extraviada'];

        return [
            'statCajasTotal' => count($cajas),
            'statCajasFueraDeLugar' => count(
                array_filter($cajas, fn ($c) => in_array($c->estado, $estadosFueraDeRanura, true)),
            ),
            'statAlertasActivas' => count(
                $alertasHandler->handle(new ListarAlertasInput('activa'))->items,
            ),
        ];
    }
}
