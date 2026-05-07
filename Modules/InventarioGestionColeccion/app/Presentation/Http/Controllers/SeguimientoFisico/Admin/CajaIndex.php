<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaInput;

#[Layout('layouts.admin')]
final class CajaIndex extends Component
{
    public array $cajas = [];

    public string $busqueda = '';

    public bool $showCrearModal = false;

    public bool $showIngresoModal = false;

    public string $cajaIdParaIngreso = '';

    public array $gabinetes = [];

    public string $gabineteIdSeleccionado = '';

    public array $ranurasDisponibles = [];

    public string $ranuraIdSeleccionada = '';

    #[Rule('required|string|max:100')]
    public string $codigo = '';

    #[Rule('required|string|size:8|regex:/^[0-9A-Fa-f]{8}$/')]
    public string $codigoRfid = '';

    #[Rule('nullable|string|max:255')]
    public ?string $nombre = null;

    #[Rule('nullable|string|max:255')]
    public ?string $familiaTaxonomicaId = null;

    #[Rule('nullable|integer|min:1')]
    public ?int $capacidadMaxima = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        ListarCajasHandler $cajasHandler,
        ListarGabineteHandler $gabineteHandler,
    ): void {
        $this->cargarCajas($cajasHandler);

        $this->gabinetes = array_map(
            fn ($g) => ['id' => $g->id, 'label' => "{$g->codigo} — {$g->nombre}"],
            $gabineteHandler->handle()->items,
        );
    }

    public function updatedGabineteIdSeleccionado(
        string $value,
        ListarRanurasGabineteHandler $handler,
    ): void {
        if ($value === '') {
            $this->ranurasDisponibles = [];
            $this->ranuraIdSeleccionada = '';

            return;
        }

        $output = $handler->handle(new ListarRanurasGabineteInput($value));
        $this->ranurasDisponibles = array_map(
            fn ($r) => ['id' => $r->id, 'label' => "Ranura {$r->numeroRanura}"],
            array_filter($output->items, fn ($r) => $r->cajaActualId === null && $r->activa),
        );
        $this->ranuraIdSeleccionada = '';
    }

    public function crearCaja(
        CrearCajaHandler $crearHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        $this->validate([
            'codigo' => 'required|string|max:100',
            'codigoRfid' => 'required|string|size:8|regex:/^[0-9A-Fa-f]{8}$/',
            'nombre' => 'nullable|string|max:255',
            'familiaTaxonomicaId' => 'nullable|string|max:255',
            'capacidadMaxima' => 'nullable|integer|min:1',
        ]);

        try {
            $crearHandler->handle(new CrearCajaInput(
                codigo: $this->codigo,
                codigoRfid: strtoupper($this->codigoRfid),
                nombre: $this->nombre ?: null,
                familiaTaxonomicaId: $this->familiaTaxonomicaId ?: null,
                capacidadMaxima: $this->capacidadMaxima,
            ));

            $this->cargarCajas($listarHandler);
            $this->showCrearModal = false;
            $this->reset('codigo', 'codigoRfid', 'nombre', 'familiaTaxonomicaId', 'capacidadMaxima');
            $this->resetValidation();
            $this->successMessage = 'Caja creada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function abrirIngresoModal(string $cajaId): void
    {
        $this->cajaIdParaIngreso = $cajaId;
        $this->gabineteIdSeleccionado = '';
        $this->ranuraIdSeleccionada = '';
        $this->ranurasDisponibles = [];
        $this->showIngresoModal = true;
    }

    public function registrarIngreso(
        RegistrarIngresoCajaHandler $handler,
        ListarCajasHandler $listarHandler,
    ): void {
        $this->validate(['ranuraIdSeleccionada' => 'required|string']);

        try {
            $handler->handle(new RegistrarIngresoCajaInput(
                cajaId: $this->cajaIdParaIngreso,
                ranuraId: $this->ranuraIdSeleccionada,
            ));

            $this->cargarCajas($listarHandler);
            $this->showIngresoModal = false;
            $this->successMessage = 'Ingreso registrado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function getCajasFiltradas(): array
    {
        if ($this->busqueda === '') {
            return $this->cajas;
        }

        $busqueda = strtolower($this->busqueda);

        return array_values(array_filter(
            $this->cajas,
            fn ($c) => str_contains(strtolower($c['codigo']), $busqueda)
                || str_contains(strtolower($c['codigoRfid']), $busqueda),
        ));
    }

    private function cargarCajas(ListarCajasHandler $handler): void
    {
        $this->cajas = array_map(
            fn ($c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'codigoRfid' => $c->codigoRfid,
                'nombre' => $c->nombre,
                'familiaTaxonomicaId' => $c->familiaTaxonomicaId,
                'capacidadMaxima' => $c->capacidadMaxima,
                'estado' => $c->estado,
            ],
            $handler->handle()->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.cajas.index', [
            'cajasFiltradas' => $this->getCajasFiltradas(),
        ]);
    }
}
