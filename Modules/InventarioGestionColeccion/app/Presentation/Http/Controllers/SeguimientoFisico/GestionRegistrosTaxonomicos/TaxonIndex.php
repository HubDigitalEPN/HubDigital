<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon\ActualizarTaxonInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones\ListarTaxonesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

#[Layout('layouts.app', params: ['title' => 'Taxones'])]
final class TaxonIndex extends Component
{
    public array $taxones = [];

    public array $rangos = [];

    public bool $showModal = false;

    #[Rule('required|string|max:255')]
    public string $nombreCientifico = '';

    #[Rule('required|string')]
    public string $rango = '';

    #[Rule('required|string|max:255')]
    public string $autor = '';

    #[Rule('required|integer|min:1700|max:2100')]
    public int $anioDescripcion = 2000;

    public string $padreId = '';

    public bool $showEditModal = false;

    public string $editandoId = '';

    #[Rule('required|string|max:255')]
    public string $editNombreCientifico = '';

    #[Rule('required|string|max:255')]
    public string $editAutor = '';

    #[Rule('required|integer|min:1700|max:2100')]
    public int $editAnioDescripcion = 2000;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarTaxonesHandler $handler): void
    {
        $this->rangos = array_column(RangoTaxonomico::cases(), 'value');
        $this->cargarTaxones($handler);
    }

    public function abrirModal(): void
    {
        $this->reset('nombreCientifico', 'rango', 'autor', 'padreId', 'successMessage', 'errorMessage');
        $this->resetValidation();
        $this->anioDescripcion = (int) date('Y');
        $this->showModal = true;
    }

    public function registrarTaxon(
        RegistrarTaxonHandler $registrarHandler,
        ListarTaxonesHandler $listarHandler,
    ): void {
        $this->validateOnly('nombreCientifico');
        $this->validateOnly('rango');
        $this->validateOnly('autor');
        $this->validateOnly('anioDescripcion');

        try {
            $registrarHandler->handle(new RegistrarTaxonInput(
                nombreCientifico: $this->nombreCientifico,
                rango: $this->rango,
                autor: $this->autor,
                anioDescripcion: $this->anioDescripcion,
                padreId: $this->padreId !== '' ? $this->padreId : null,
            ));

            $this->cargarTaxones($listarHandler);
            $this->showModal = false;
            $this->successMessage = 'Taxón registrado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function abrirEditModal(string $id): void
    {
        $taxon = collect($this->taxones)->firstWhere('id', $id);

        if ($taxon === null) {
            return;
        }

        $this->editandoId = $id;
        $this->editNombreCientifico = $taxon['nombreCientifico'];
        $this->editAutor = $taxon['autor'];
        $this->editAnioDescripcion = $taxon['anioDescripcion'];
        $this->errorMessage = null;
        $this->showEditModal = true;
    }

    public function actualizarTaxon(
        ActualizarTaxonHandler $actualizarHandler,
        ListarTaxonesHandler $listarHandler,
    ): void {
        $this->validateOnly('editNombreCientifico');
        $this->validateOnly('editAutor');
        $this->validateOnly('editAnioDescripcion');

        try {
            $actualizarHandler->handle(new ActualizarTaxonInput(
                taxonId: $this->editandoId,
                nombreCientifico: $this->editNombreCientifico,
                autor: $this->editAutor,
                anioDescripcion: $this->editAnioDescripcion,
            ));

            $this->cargarTaxones($listarHandler);
            $this->showEditModal = false;
            $this->successMessage = 'Taxón actualizado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function cargarTaxones(ListarTaxonesHandler $handler): void
    {
        $output = $handler->handle();
        $this->taxones = array_map(
            fn ($t) => [
                'id' => $t->id,
                'nombreCientifico' => $t->nombreCientifico,
                'rango' => $t->rango,
                'autor' => $t->autor,
                'anioDescripcion' => $t->anioDescripcion,
                'estado' => $t->estado,
                'padreId' => $t->padreId,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.taxones.index');
    }
}
