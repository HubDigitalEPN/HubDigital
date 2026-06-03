<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray\CrearUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray\CrearUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Asignación de unit trays'])]
final class AsignacionUnitTrayIndex extends Component
{
    use TraduceErroresPersistencia;

    public array $cajas = [];

    public string $cajaSeleccionada = '';

    public string $cajaSeleccionadaLabel = '';

    public array $unitTrays = [];

    public string $unitTraySeleccionado = '';

    public array $especimenes = [];

    public array $especimenesSeleccionados = [];

    public ?string $successMessage = null;

    public ?string $warningMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        ListarCajasHandler $cajasHandler,
        ListarEspecimenesAsignablesHandler $especimenesHandler,
    ): void {
        $this->cargarProtegido(function () use ($cajasHandler, $especimenesHandler) {
            $this->cajas = array_map(
                fn ($c) => ['id' => $c->id, 'label' => "{$c->codigo}".($c->nombre ? " — {$c->nombre}" : '')],
                $cajasHandler->handle()->items,
            );
            $this->especimenes = $especimenesHandler->handle()->items;
        });
    }

    public function updatedCajaSeleccionada(string $value): void
    {
        $this->unitTraySeleccionado = '';
        $this->especimenesSeleccionados = [];
        $this->cajaSeleccionadaLabel = $this->labelDeCaja($value);
        $this->limpiarMensajes();
        $this->cargarProtegido(fn () => $this->cargarUnitTrays($value));
    }

    public function crearUnitTray(CrearUnitTrayHandler $handler): void
    {
        $this->validate(['cajaSeleccionada' => 'required|string']);

        try {
            $handler->handle(new CrearUnitTrayInput(cajaId: $this->cajaSeleccionada));
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Unit tray creado y numerado automáticamente.');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function seleccionarUnitTray(string $unitTrayId): void
    {
        $this->unitTraySeleccionado = $unitTrayId;
        $this->limpiarMensajes();
        $this->especimenesSeleccionados = array_values(array_map(
            fn ($e) => $e['id'],
            array_filter($this->especimenes, fn ($e) => $e['unitTrayId'] === $unitTrayId),
        ));
    }

    public function asignarEspecimenes(
        ActualizarEspecimenesUnitTrayHandler $handler,
        ListarEspecimenesAsignablesHandler $especimenesHandler,
    ): void {
        $this->validate(['unitTraySeleccionado' => 'required|string']);

        try {
            $output = $handler->handle(new ActualizarEspecimenesUnitTrayInput(
                unitTrayId: $this->unitTraySeleccionado,
                especimenIds: array_values($this->especimenesSeleccionados),
            ));
            $this->especimenes = $especimenesHandler->handle()->items;
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Especímenes asignados al unit tray.');
            $this->advertirFueraDeLugar($output->especimenesFueraDeLugar);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.unit-trays.index');
    }

    private function cargarUnitTrays(string $cajaId): void
    {
        $this->unitTrays = $cajaId === ''
            ? []
            : app(ListarUnitTraysPorCajaHandler::class)
                ->handle(new ListarUnitTraysPorCajaInput($cajaId))->items;
    }

    private function flash(string $mensaje): void
    {
        $this->successMessage = $mensaje;
        $this->errorMessage = null;
        $this->warningMessage = null;
    }

    private function limpiarMensajes(): void
    {
        $this->successMessage = null;
        $this->warningMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Soft alert: avisa, sin bloquear, qué especímenes no parecen pertenecer al tray.
     *
     * @param  string[]  $codigos
     */
    private function advertirFueraDeLugar(array $codigos): void
    {
        if ($codigos === []) {
            return;
        }

        $lista = implode(', ', $codigos);
        $this->warningMessage = count($codigos) === 1
            ? "El especimen {$lista} no parece pertenecer a este unit tray según su taxonomía. Revisa su ubicación."
            : "Estos especímenes no parecen pertenecer a este unit tray según su taxonomía: {$lista}. Revisa su ubicación.";
    }

    private function labelDeCaja(string $cajaId): string
    {
        foreach ($this->cajas as $caja) {
            if ($caja['id'] === $cajaId) {
                return $caja['label'];
            }
        }

        return '';
    }
}
