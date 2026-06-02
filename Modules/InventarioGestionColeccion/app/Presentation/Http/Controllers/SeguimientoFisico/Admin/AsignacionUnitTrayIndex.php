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

#[Layout('layouts.app', params: ['title' => 'Asignación de Unit Trays'])]
final class AsignacionUnitTrayIndex extends Component
{
    use TraduceErroresPersistencia;

    public array $cajas = [];

    public string $cajaSeleccionada = '';

    public ?int $numeroNuevoTray = null;

    public array $unitTrays = [];

    public string $unitTraySeleccionado = '';

    public array $especimenes = [];

    public array $especimenesSeleccionados = [];

    public ?string $successMessage = null;

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
        $this->cargarProtegido(fn () => $this->cargarUnitTrays($value));
    }

    public function crearUnitTray(CrearUnitTrayHandler $handler): void
    {
        $this->validate([
            'cajaSeleccionada' => 'required|string',
            'numeroNuevoTray' => 'required|integer|min:1',
        ]);

        try {
            $handler->handle(new CrearUnitTrayInput(
                cajaId: $this->cajaSeleccionada,
                numero: $this->numeroNuevoTray,
            ));
            $this->numeroNuevoTray = null;
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Unit tray creado en la caja seleccionada.');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function seleccionarUnitTray(string $unitTrayId): void
    {
        $this->unitTraySeleccionado = $unitTrayId;
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
            $handler->handle(new ActualizarEspecimenesUnitTrayInput(
                unitTrayId: $this->unitTraySeleccionado,
                especimenIds: array_values($this->especimenesSeleccionados),
            ));
            $this->especimenes = $especimenesHandler->handle()->items;
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Especímenes asignados al unit tray.');
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
    }
}
