<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Poll;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Monitoreo IoT'])]
final class Dashboard extends Component
{
    use TraduceErroresPersistencia;

    public array $gabinetes = [];

    public array $cajasPorId = [];

    public array $resumenEstados = [];

    public ?string $errorMessage = null;

    public function mount(
        ListarGabineteHandler $gabineteHandler,
        ListarCajasHandler $cajasHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->refrescar($gabineteHandler, $cajasHandler, $ranurasHandler);
    }

    #[Poll(5000)]
    public function refrescar(
        ListarGabineteHandler $gabineteHandler,
        ListarCajasHandler $cajasHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->cargarProtegido(function () use ($gabineteHandler, $cajasHandler, $ranurasHandler) {
            $cajasOutput = $cajasHandler->handle();

            $cajasPorId = [];
            $resumen = [];
            foreach ($cajasOutput->items as $c) {
                $cajasPorId[$c->id] = ['id' => $c->id, 'codigo' => $c->codigo, 'estado' => $c->estado];
                $resumen[$c->estado] = ($resumen[$c->estado] ?? 0) + 1;
            }

            $gabinetes = [];
            foreach ($gabineteHandler->handle()->items as $g) {
                $ranurasOutput = $ranurasHandler->handle(new ListarRanurasGabineteInput($g->id));

                $ranuras = array_map(
                    fn ($r) => [
                        'id' => $r->id,
                        'numeroRanura' => $r->numeroRanura,
                        'cajaActual' => $r->cajaActualId ? ($cajasPorId[$r->cajaActualId] ?? null) : null,
                    ],
                    $ranurasOutput->items,
                );

                usort($ranuras, fn ($a, $b) => $a['numeroRanura'] <=> $b['numeroRanura']);

                $gabinetes[] = [
                    'id' => $g->id,
                    'codigo' => $g->codigo,
                    'nombre' => $g->nombre,
                    'totalRanuras' => $g->totalRanuras,
                    'ranuras' => $ranuras,
                ];
            }

            // Solo se publica el estado completo si toda la carga tuvo éxito, para no
            // dejar el tablero con datos a medias durante un fallo de conexión.
            $this->cajasPorId = $cajasPorId;
            $this->resumenEstados = $resumen;
            $this->gabinetes = $gabinetes;
            $this->errorMessage = null;
        });
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.dashboard');
    }
}
