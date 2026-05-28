<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja\ActualizarCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja\ActualizarCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja\EliminarCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja\EliminarCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Cajas'])]
final class CajaIndex extends Component
{
    use TraduceErroresPersistencia;

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

    #[Rule('nullable|string|max:1000')]
    public ?string $observacion = null;

    public bool $esEspecial = false;

    #[Rule('nullable|integer|min:1|max:32767')]
    public ?int $capacidadMaxima = null;

    public bool $showEditCajaModal = false;

    public string $editandoCajaId = '';

    #[Rule('nullable|string|max:255')]
    public ?string $editNombre = null;

    public bool $editEsEspecial = false;

    #[Rule('nullable|string|max:1000')]
    public ?string $editObservacion = null;

    #[Rule('nullable|integer|min:1|max:32767')]
    public ?int $editCapacidadMaxima = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    protected function validationAttributes(): array
    {
        return [
            'capacidadMaxima' => 'capacidad máxima',
            'editCapacidadMaxima' => 'capacidad máxima',
            'codigo' => 'código',
            'codigoRfid' => 'código RFID',
            'ranuraIdSeleccionada' => 'ranura',
        ];
    }

    protected function messages(): array
    {
        return [
            'capacidadMaxima.max' => 'La capacidad máxima no puede superar 32.767 especímenes.',
            'capacidadMaxima.min' => 'La capacidad máxima debe ser al menos 1.',
            'capacidadMaxima.integer' => 'La capacidad máxima debe ser un número entero.',
            'editCapacidadMaxima.max' => 'La capacidad máxima no puede superar 32.767 especímenes.',
            'editCapacidadMaxima.min' => 'La capacidad máxima debe ser al menos 1.',
            'editCapacidadMaxima.integer' => 'La capacidad máxima debe ser un número entero.',
        ];
    }

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

    public function updatedGabineteIdSeleccionado(string $value): void
    {
        if ($value === '') {
            $this->ranurasDisponibles = [];
            $this->ranuraIdSeleccionada = '';

            return;
        }

        $handler = app(ListarRanurasGabineteHandler::class);
        $output = $handler->handle(new ListarRanurasGabineteInput($value));
        $this->ranurasDisponibles = array_values(array_map(
            fn ($r) => ['id' => $r->id, 'label' => "Ranura {$r->numeroRanura}"],
            array_filter($output->items, fn ($r) => $r->cajaActualId === null && $r->activa),
        ));
        $this->ranuraIdSeleccionada = '';
    }

    public function crearCaja(
        CrearCajaHandler $crearHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        $this->validate();

        try {
            $crearHandler->handle(new CrearCajaInput(
                codigo: $this->codigo,
                codigoRfid: strtoupper($this->codigoRfid),
                esEspecial: $this->esEspecial,
                observacion: $this->observacion ?: null,
                nombre: $this->nombre ?: null,
                capacidadMaxima: $this->capacidadMaxima,
            ));

            $this->cargarCajas($listarHandler);
            $this->showCrearModal = false;
            $this->reset('codigo', 'codigoRfid', 'nombre', 'observacion', 'esEspecial', 'capacidadMaxima');
            $this->resetValidation();
            $this->successMessage = 'Caja creada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function abrirEditCajaModal(string $id): void
    {
        $caja = collect($this->cajas)->firstWhere('id', $id);

        if ($caja === null) {
            return;
        }

        $this->editandoCajaId = $id;
        $this->editNombre = $caja['nombre'];
        $this->editEsEspecial = $caja['esEspecial'];
        $this->editObservacion = $caja['observacion'];
        $this->editCapacidadMaxima = $caja['capacidadMaxima'];
        $this->errorMessage = null;
        $this->showEditCajaModal = true;
    }

    public function actualizarCaja(
        ActualizarCajaHandler $actualizarHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        $this->validate([
            'editNombre' => 'nullable|string|max:255',
            'editObservacion' => 'nullable|string|max:1000',
            'editCapacidadMaxima' => 'nullable|integer|min:1|max:32767',
        ]);

        try {
            $actualizarHandler->handle(new ActualizarCajaInput(
                cajaId: $this->editandoCajaId,
                esEspecial: $this->editEsEspecial,
                observacion: $this->editObservacion ?: null,
                nombre: $this->editNombre ?: null,
                capacidadMaxima: $this->editCapacidadMaxima,
            ));

            $this->cargarCajas($listarHandler);
            $this->showEditCajaModal = false;
            $this->successMessage = 'Caja actualizada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function eliminarCaja(
        string $id,
        EliminarCajaHandler $eliminarHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        try {
            $eliminarHandler->handle(new EliminarCajaInput(cajaId: $id));
            $this->cargarCajas($listarHandler);
            $this->successMessage = 'Caja eliminada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function registrarRetiro(
        string $id,
        RegistrarRetiroCajaHandler $retiroHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        try {
            $retiroHandler->handle(new RegistrarRetiroCajaInput(cajaId: $id));
            $this->cargarCajas($listarHandler);
            $this->successMessage = 'Retiro registrado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
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
            $output = $handler->handle(new RegistrarIngresoCajaInput(
                cajaId: $this->cajaIdParaIngreso,
                ranuraId: $this->ranuraIdSeleccionada,
            ));

            $this->cargarCajas($listarHandler);
            $this->showIngresoModal = false;
            $this->successMessage = $output->alertaGenerada
                ? 'Ingreso registrado. Se generó una alerta; revísala en el panel de alertas.'
                : 'Ingreso registrado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
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
                'esEspecial' => $c->esEspecial,
                'observacion' => $c->observacion,
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
