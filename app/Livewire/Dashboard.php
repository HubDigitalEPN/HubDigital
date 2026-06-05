<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;
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
            RolUsuario::DEPOSITANTE => view('livewire.dashboard.depositante-panel'),
        };
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
