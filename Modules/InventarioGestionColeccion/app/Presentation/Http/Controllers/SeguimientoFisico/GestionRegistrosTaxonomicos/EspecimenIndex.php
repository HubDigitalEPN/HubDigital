<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;

#[Layout('layouts.app', params: ['title' => 'Especímenes'])]
final class EspecimenIndex extends Component
{
    public array $especimenes = [];

    public bool $buscado = false;

    #[Rule('required|string|in:taxon,localidad,estado')]
    public string $criterio = 'taxon';

    #[Rule('required|string|min:2|max:255')]
    public string $valor = '';

    public ?string $errorMessage = null;

    public function buscar(BuscarEspecimenesHandler $handler): void
    {
        $this->validate();

        try {
            $output = $handler->handle(new BuscarEspecimenesInput(
                criterio: $this->criterio,
                valor: $this->valor,
            ));

            $this->especimenes = $output->items;
            $this->buscado = true;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function limpiar(): void
    {
        $this->reset('criterio', 'valor', 'especimenes', 'buscado', 'errorMessage');
        $this->resetValidation();
        $this->criterio = 'taxon';
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.index');
    }
}
