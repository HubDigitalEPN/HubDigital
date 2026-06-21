<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete\ActualizarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarGabinete\ActualizarGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura\ActualizarRanuraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarRanura\ActualizarRanuraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete\DesactivarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarGabinete\DesactivarGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Pantalla del curador para administrar los gabinetes metálicos: crearlos (con su
 * número de ranuras, hasta 25), editarlos, activar o desactivar ranuras individuales y
 * desactivar el gabinete completo. Tras crear un gabinete redirige a su detalle para
 * configurar el token del ESP32. Es presentación pura: delega cada acción en su caso
 * de uso y traduce los errores a mensajes legibles.
 */
#[Layout('layouts.app', params: ['title' => 'Gabinetes'])]
final class GabineteIndex extends Component
{
    use TraduceErroresPersistencia;

    public array $gabinetes = [];

    public bool $showModal = false;

    #[Rule('required|string|max:100')]
    public string $codigo = '';

    #[Rule('required|string|max:255')]
    public string $nombre = '';

    #[Rule('required|integer|min:1|max:25')]
    public int $totalRanuras = 1;

    public bool $showEditModal = false;

    public string $editandoId = '';

    #[Rule('required|string|max:255')]
    public string $editNombre = '';

    #[Rule('required|integer|min:1|max:25')]
    public int $editTotalRanuras = 1;

    public array $editRanuras = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /** Carga la lista inicial de gabinetes. */
    public function mount(ListarGabineteHandler $handler): void
    {
        $this->cargarProtegido(fn () => $this->cargarGabinetes($handler));
    }

    /** Abre el modal de creación limpiando el formulario y dejando una ranura por defecto. */
    public function abrirModal(): void
    {
        $this->reset('codigo', 'nombre', 'successMessage', 'errorMessage');
        $this->resetValidation();
        $this->totalRanuras = 1;
        $this->showModal = true;
    }

    /**
     * Crea el gabinete con su código, nombre y número de ranuras (el caso de uso genera
     * las ranuras) y redirige a su detalle, donde el curador configura el ESP32 con el
     * id y el token de API. Deja un mensaje flash con esa instrucción.
     */
    public function crearGabinete(CrearGabineteHandler $crearHandler)
    {
        $this->validateOnly('codigo');
        $this->validateOnly('nombre');
        $this->validateOnly('totalRanuras');

        try {
            $output = $crearHandler->handle(new CrearGabineteInput(
                codigo: $this->codigo,
                nombre: $this->nombre,
                totalRanuras: $this->totalRanuras,
            ));

            session()->flash('successMessage', 'Gabinete creado. Configura el ESP32 con su ID y token de API.');

            return $this->redirectRoute('inventario.gabinetes.show', ['id' => $output->id], navigate: true);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Precarga el modal de edición con los datos del gabinete y carga sus ranuras (con
     * su estado activa/inactiva) para poder alternarlas individualmente.
     */
    public function abrirEditModal(string $id): void
    {
        $gabinete = collect($this->gabinetes)->firstWhere('id', $id);

        if ($gabinete === null) {
            return;
        }

        $this->editandoId = $id;
        $this->editNombre = $gabinete['nombre'];
        $this->editTotalRanuras = $gabinete['totalRanuras'];
        $this->errorMessage = null;

        $ranuraHandler = app(ListarRanurasGabineteHandler::class);
        $ranuras = $ranuraHandler->handle(new ListarRanurasGabineteInput($id));
        $this->editRanuras = array_map(
            fn ($r) => [
                'id' => $r->id,
                'numeroRanura' => $r->numeroRanura,
                'activa' => $r->activa,
            ],
            $ranuras->items,
        );

        $this->showEditModal = true;
    }

    /**
     * Alterna el estado activa/inactiva de una sola ranura del gabinete en edición. Una
     * ranura inactiva queda fuera del barrido del ESP32 y deja de generar lecturas; se
     * usa para slots dañados o sin lector. Persiste el cambio en el caso de uso y solo
     * refleja el nuevo estado en pantalla si la operación tuvo éxito.
     */
    public function toggleRanuraActiva(string $ranuraId): void
    {
        $idx = collect($this->editRanuras)->search(fn ($r) => $r['id'] === $ranuraId);

        if ($idx === false) {
            return;
        }

        $nuevaActiva = ! $this->editRanuras[$idx]['activa'];

        try {
            $handler = app(ActualizarRanuraHandler::class);
            $handler->handle(new ActualizarRanuraInput(
                ranuraId: $ranuraId,
                activa: $nuevaActiva,
            ));

            $this->editRanuras[$idx]['activa'] = $nuevaActiva;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Guarda los cambios de nombre y número de ranuras del gabinete en edición, recarga
     * la lista para reflejarlos, cierra el modal y confirma. Cualquier fallo se traduce
     * a un mensaje legible sin romper la vista.
     */
    public function actualizarGabinete(
        ActualizarGabineteHandler $actualizarHandler,
        ListarGabineteHandler $listarHandler,
    ): void {
        $this->validateOnly('editNombre');
        $this->validateOnly('editTotalRanuras');

        try {
            $actualizarHandler->handle(new ActualizarGabineteInput(
                gabineteId: $this->editandoId,
                nombre: $this->editNombre,
                totalRanuras: $this->editTotalRanuras,
            ));

            $this->cargarGabinetes($listarHandler);
            $this->showEditModal = false;
            $this->successMessage = 'Gabinete actualizado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Desactiva el gabinete completo (lo retira del monitoreo IoT sin borrarlo),
     * recarga la lista y confirma. Cualquier fallo se traduce a un mensaje legible.
     */
    public function desactivarGabinete(
        string $id,
        DesactivarGabineteHandler $desactivarHandler,
        ListarGabineteHandler $listarHandler,
    ): void {
        try {
            $desactivarHandler->handle(new DesactivarGabineteInput(gabineteId: $id));
            $this->cargarGabinetes($listarHandler);
            $this->successMessage = 'Gabinete desactivado.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Ejecuta el caso de uso de listado y mapea cada gabinete a una estructura plana para la vista. */
    private function cargarGabinetes(ListarGabineteHandler $handler): void
    {
        $output = $handler->handle();
        $this->gabinetes = array_map(
            fn ($g) => [
                'id' => $g->id,
                'codigo' => $g->codigo,
                'nombre' => $g->nombre,
                'totalRanuras' => $g->totalRanuras,
                'activo' => $g->activo,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.gabinetes.index');
    }
}
