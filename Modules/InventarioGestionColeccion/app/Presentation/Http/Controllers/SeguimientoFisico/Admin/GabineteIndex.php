<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;

#[Layout('layouts.admin')]
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
        $this->validate();

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
