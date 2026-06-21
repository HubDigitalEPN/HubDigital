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

/**
 * Tablero de monitoreo IoT del curador: muestra, casi en tiempo real, qué caja ocupa
 * cada ranura de cada gabinete y un resumen agregado de estados de las cajas. Se
 * autorefresca por polling para reflejar el último barrido del ESP32 sin recargar la
 * página. Es presentación pura: solo orquesta los casos de uso de listado y mapea sus
 * salidas a estructuras simples para la vista.
 */
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

    /**
     * Reconstruye todo el estado del tablero: indexa las cajas por id, cuenta cuántas
     * hay en cada estado y, para cada gabinete, ordena sus ranuras por número y les
     * adjunta la caja que ocupan. El componente se autorefresca cada 5 s (Poll). Para
     * no dejar el tablero a medias durante un fallo de conexión, solo publica el estado
     * completo si toda la carga tuvo éxito.
     */
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
