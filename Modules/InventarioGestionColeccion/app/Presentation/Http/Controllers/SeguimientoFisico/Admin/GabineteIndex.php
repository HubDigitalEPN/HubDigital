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

#[Layout('layouts.app', params: ['title' => 'Gabinetes'])]
final class GabineteIndex extends Component
{
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

    public function mount(ListarGabineteHandler $handler): void
    {
        $this->cargarGabinetes($handler);
    }

    public function abrirModal(): void
    {
        $this->reset('codigo', 'nombre', 'successMessage', 'errorMessage');
        $this->resetValidation();
        $this->totalRanuras = 1;
        $this->showModal = true;
    }

    public function crearGabinete(
        CrearGabineteHandler $crearHandler,
        ListarGabineteHandler $listarHandler,
    ): void {
        $this->validateOnly('codigo');
        $this->validateOnly('nombre');
        $this->validateOnly('totalRanuras');

        try {
            $crearHandler->handle(new CrearGabineteInput(
                codigo: $this->codigo,
                nombre: $this->nombre,
                totalRanuras: $this->totalRanuras,
            ));

            $this->cargarGabinetes($listarHandler);
            $this->showModal = false;
            $this->successMessage = 'Gabinete creado correctamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

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
                'familiaTaxonomicaEsperadaId' => $r->familiaTaxonomicaEsperadaId,
                'activa' => $r->activa,
            ],
            $ranuras->items,
        );

        $this->showEditModal = true;
    }

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
                familiaTaxonomicaEsperadaId: $this->editRanuras[$idx]['familiaTaxonomicaEsperadaId'] ?? null,
                activa: $nuevaActiva,
            ));

            $this->editRanuras[$idx]['activa'] = $nuevaActiva;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

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
            $this->errorMessage = $e->getMessage();
        }
    }

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
            $this->errorMessage = $e->getMessage();
        }
    }

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
