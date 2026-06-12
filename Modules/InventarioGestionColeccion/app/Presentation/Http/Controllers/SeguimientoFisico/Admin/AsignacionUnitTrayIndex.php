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
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Pantalla del curador para organizar los especímenes en unit trays (bandejas) dentro de
 * cada caja: elige una caja, crea o selecciona un unit tray y le asigna especímenes
 * buscándolos de forma acotada. El catálogo de especímenes (48k+) nunca se carga de
 * golpe; solo se consulta al elegir un tray. Al asignar avisa, sin bloquear, qué
 * especímenes parecen fuera de lugar según su taxonomía. Es presentación pura: delega
 * cada acción en su caso de uso.
 */
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

    public string $busquedaEspecimen = '';

    public ?string $successMessage = null;

    public ?string $warningMessage = null;

    public ?string $errorMessage = null;

    /** Carga solo las cajas disponibles; los especímenes se buscan después de forma acotada. */
    public function mount(ListarCajasHandler $cajasHandler): void
    {
        // Solo se cargan las cajas al montar. Los especímenes (catálogo de 48k+)
        // jamás se cargan de golpe: se buscan de forma acotada al elegir un tray.
        $this->cargarProtegido(function () use ($cajasHandler) {
            $this->cajas = array_map(
                fn ($c) => ['id' => $c->id, 'label' => "{$c->codigo}".($c->nombre ? " — {$c->nombre}" : '')],
                $cajasHandler->handle()->items,
            );
        });
    }

    /** Al cambiar de caja reinicia la selección de tray/especímenes y carga los unit trays de esa caja. */
    public function updatedCajaSeleccionada(string $value): void
    {
        $this->unitTraySeleccionado = '';
        $this->especimenesSeleccionados = [];
        $this->cajaSeleccionadaLabel = $this->labelDeCaja($value);
        $this->limpiarMensajes();
        $this->cargarProtegido(fn () => $this->cargarUnitTrays($value));
    }

    /** Crea un unit tray en la caja seleccionada (el caso de uso lo numera automáticamente) y recarga la lista. */
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

    /**
     * Selecciona un unit tray y carga los especímenes asignables, marcando como
     * preseleccionados los que ya pertenecen a ese tray para que el curador edite su
     * composición.
     */
    public function seleccionarUnitTray(string $unitTrayId): void
    {
        $this->unitTraySeleccionado = $unitTrayId;
        $this->busquedaEspecimen = '';
        $this->limpiarMensajes();
        $this->cargarProtegido(fn () => $this->cargarEspecimenes());
        $this->especimenesSeleccionados = array_values(array_map(
            fn ($e) => $e['id'],
            array_filter($this->especimenes, fn ($e) => $e['unitTrayId'] === $unitTrayId),
        ));
    }

    /** Reejecuta la búsqueda acotada de especímenes cada vez que cambia el texto de búsqueda. */
    public function updatedBusquedaEspecimen(): void
    {
        $this->cargarProtegido(fn () => $this->cargarEspecimenes());
    }

    /** Descarta la selección actual de tray y especímenes, volviendo al estado inicial de la caja. */
    public function cancelarSeleccion(): void
    {
        $this->unitTraySeleccionado = '';
        $this->especimenesSeleccionados = [];
        $this->especimenes = [];
        $this->busquedaEspecimen = '';
        $this->limpiarMensajes();
    }

    /**
     * Guarda la composición del unit tray con los especímenes seleccionados, recarga
     * lista y trays, confirma y, si el caso de uso detectó especímenes que no parecen
     * pertenecer al tray según su taxonomía, lo advierte sin bloquear la operación.
     */
    public function asignarEspecimenes(ActualizarEspecimenesUnitTrayHandler $handler): void
    {
        $this->validate(['unitTraySeleccionado' => 'required|string']);

        try {
            $output = $handler->handle(new ActualizarEspecimenesUnitTrayInput(
                unitTrayId: $this->unitTraySeleccionado,
                especimenIds: array_values($this->especimenesSeleccionados),
            ));
            $this->cargarEspecimenes();
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

    /** Carga los unit trays de la caja indicada (lista vacía si no hay caja seleccionada). */
    private function cargarUnitTrays(string $cajaId): void
    {
        $this->unitTrays = $cajaId === ''
            ? []
            : app(ListarUnitTraysPorCajaHandler::class)
                ->handle(new ListarUnitTraysPorCajaInput($cajaId))->items;
    }

    /**
     * Busca los especímenes asignables al tray seleccionado de forma acotada por el texto
     * de búsqueda (nunca trae el catálogo completo). Sin tray seleccionado deja la lista vacía.
     */
    private function cargarEspecimenes(): void
    {
        if ($this->unitTraySeleccionado === '') {
            $this->especimenes = [];

            return;
        }

        $busqueda = trim($this->busquedaEspecimen);

        $this->especimenes = app(ListarEspecimenesAsignablesHandler::class)
            ->handle(new ListarEspecimenesAsignablesInput(
                busqueda: $busqueda !== '' ? $busqueda : null,
                unitTrayId: $this->unitTraySeleccionado,
            ))->items;
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
