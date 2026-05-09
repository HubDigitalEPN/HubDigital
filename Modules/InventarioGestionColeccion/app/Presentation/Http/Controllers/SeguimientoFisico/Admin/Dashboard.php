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

#[Layout('layouts.app', params: ['title' => 'Monitoreo IoT'])]
final class Dashboard extends Component
{
    public array $gabinetes = [];

    public array $cajasPorId = [];

    public array $resumenEstados = [];

    public function mount(
        ListarGabineteHandler $gabineteHandler,
        ListarCajasHandler $cajasHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->refrescar($gabineteHandler, $cajasHandler, $ranurasHandler);
    }

    #[Poll('5s')]
    public function refrescar(
        ListarGabineteHandler $gabineteHandler,
        ListarCajasHandler $cajasHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $cajasOutput = $cajasHandler->handle();

        $cajasPorId = [];
        $resumen = [];
        foreach ($cajasOutput->items as $c) {
            $cajasPorId[$c->id] = ['id' => $c->id, 'codigo' => $c->codigo, 'estado' => $c->estado];
            $resumen[$c->estado] = ($resumen[$c->estado] ?? 0) + 1;
        }
        $this->cajasPorId = $cajasPorId;
        $this->resumenEstados = $resumen;

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

        $this->gabinetes = $gabinetes;
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.dashboard');
    }
}
