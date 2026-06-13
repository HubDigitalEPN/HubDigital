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

/**
 * Pantalla del curador para gestionar las cajas entomológicas: crear, editar y
 * eliminar cajas, registrar su ingreso a una ranura (eligiendo gabinete → ranura
 * libre) o su retiro, y buscar por código o RFID. Concentra el ciclo de vida físico
 * de la caja en la colección. Es presentación pura: arma el Input de cada caso de uso,
 * lo ejecuta y traduce los errores a mensajes legibles.
 */
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

    public bool $showEditCajaModal = false;

    public string $editandoCajaId = '';

    #[Rule('nullable|string|max:255')]
    public ?string $editNombre = null;

    public bool $editEsEspecial = false;

    #[Rule('nullable|string|max:1000')]
    public ?string $editObservacion = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    protected function validationAttributes(): array
    {
        return [
            'codigo' => 'código',
            'codigoRfid' => 'código RFID',
            'ranuraIdSeleccionada' => 'ranura',
        ];
    }

    /** Carga la lista de cajas y el catálogo de gabinetes (para el selector del modal de ingreso). */
    public function mount(
        ListarCajasHandler $cajasHandler,
        ListarGabineteHandler $gabineteHandler,
    ): void {
        $this->cargarProtegido(function () use ($cajasHandler, $gabineteHandler) {
            $this->cargarCajas($cajasHandler);

            $this->gabinetes = array_map(
                fn ($g) => ['id' => $g->id, 'label' => "{$g->codigo} — {$g->nombre}"],
                $gabineteHandler->handle()->items,
            );
        });
    }

    /**
     * Reacciona al elegir gabinete en el modal de ingreso: carga sus ranuras y deja
     * disponibles solo las activas y aún libres, para que la caja no pueda asignarse a
     * una ranura ocupada o inactiva.
     */
    public function updatedGabineteIdSeleccionado(string $value): void
    {
        if ($value === '') {
            $this->ranurasDisponibles = [];
            $this->ranuraIdSeleccionada = '';

            return;
        }

        $this->cargarProtegido(function () use ($value) {
            $handler = app(ListarRanurasGabineteHandler::class);
            $output = $handler->handle(new ListarRanurasGabineteInput($value));
            $this->ranurasDisponibles = array_values(array_map(
                fn ($r) => ['id' => $r->id, 'label' => "Ranura {$r->numeroRanura}"],
                array_filter($output->items, fn ($r) => $r->cajaActualId === null && $r->activa),
            ));
            $this->ranuraIdSeleccionada = '';
        });
    }

    /**
     * Crea una caja con los datos del modal (código, RFID en mayúsculas, nombre,
     * observación, marca de especial), recarga la lista, limpia el formulario y
     * confirma. Los fallos —p. ej. código o RFID duplicado— se muestran traducidos.
     */
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
            ));

            $this->cargarCajas($listarHandler);
            $this->showCrearModal = false;
            $this->reset('codigo', 'codigoRfid', 'nombre', 'observacion', 'esEspecial');
            $this->resetValidation();
            $this->successMessage = 'Caja creada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Precarga el modal de edición con los datos de la caja seleccionada (de la lista ya en memoria). */
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
        $this->errorMessage = null;
        $this->showEditCajaModal = true;
    }

    /**
     * Guarda los cambios de la caja en edición (nombre, observación, marca de especial)
     * sin tocar código ni RFID, recarga la lista y confirma.
     */
    public function actualizarCaja(
        ActualizarCajaHandler $actualizarHandler,
        ListarCajasHandler $listarHandler,
    ): void {
        $this->validate([
            'editNombre' => 'nullable|string|max:255',
            'editObservacion' => 'nullable|string|max:1000',
        ]);

        try {
            $actualizarHandler->handle(new ActualizarCajaInput(
                cajaId: $this->editandoCajaId,
                esEspecial: $this->editEsEspecial,
                observacion: $this->editObservacion ?: null,
                nombre: $this->editNombre ?: null,
            ));

            $this->cargarCajas($listarHandler);
            $this->showEditCajaModal = false;
            $this->successMessage = 'Caja actualizada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Elimina la caja indicada, recarga la lista y confirma; los conflictos de integridad se muestran traducidos. */
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

    /** Registra el retiro de la caja de su ranura (queda fuera del gabinete), recarga la lista y confirma. */
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

    /** Abre el modal de ingreso para la caja indicada, reseteando la selección de gabinete y ranura. */
    public function abrirIngresoModal(string $cajaId): void
    {
        $this->cajaIdParaIngreso = $cajaId;
        $this->gabineteIdSeleccionado = '';
        $this->ranuraIdSeleccionada = '';
        $this->ranurasDisponibles = [];
        $this->showIngresoModal = true;
    }

    /**
     * Registra el ingreso de la caja en la ranura elegida, recarga la lista y confirma.
     * Si el ingreso disparó una alerta (p. ej. desorden taxonómico), lo indica en el
     * mensaje para que el curador la revise en el panel de alertas.
     */
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

    /** Filtra en memoria la lista de cajas por coincidencia de código o RFID con el término de búsqueda. */
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

    /** Ejecuta el caso de uso de listado y mapea cada caja a una estructura plana para la vista (incluye su clasificación taxonómica). */
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
                'estado' => $c->estado,
                'orden' => $c->orden,
                'suborden' => $c->suborden,
                'superfamilia' => $c->superfamilia,
                'familia' => $c->familia,
                'subfamilia' => $c->subfamilia,
                'genero' => $c->genero,
                'especie' => $c->especie,
                'subfamilias' => $c->subfamilias,
                'generos' => $c->generos,
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
