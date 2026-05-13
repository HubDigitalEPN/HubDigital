<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes\ListarEntidadesDepositantesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones\ListarTaxonesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;

#[Layout('layouts.app', params: ['title' => 'Especímenes'])]
final class EspecimenIndex extends Component
{
    // ── Búsqueda ──────────────────────────────────────────────────────────────

    public array $especimenes = [];

    public bool $buscado = false;

    #[Rule('required|string|in:taxon,localidad,estado')]
    public string $criterio = 'taxon';

    #[Rule('required|string|min:2|max:255')]
    public string $valor = '';

    // ── Registro ──────────────────────────────────────────────────────────────

    public bool $showModal = false;

    public array $taxones = [];

    public array $entidades = [];

    #[Rule('required|string|max:100')]
    public string $codigoCatalogo = '';

    #[Rule('required|string|uuid')]
    public string $taxonId = '';

    #[Rule('required|string|max:255')]
    public string $localidad = '';

    #[Rule('required|date_format:Y-m-d')]
    public string $fechaColecta = '';

    #[Rule('required|string|max:255')]
    public string $colector = '';

    public string $entidadDepositanteId = '';

    // ── Feedback ──────────────────────────────────────────────────────────────

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        ListarTaxonesHandler $taxonesHandler,
        ListarEntidadesDepositantesHandler $entidadesHandler,
    ): void {
        $this->taxones = array_map(
            fn ($t) => ['id' => $t->id, 'label' => "{$t->nombreCientifico} ({$t->rango})"],
            $taxonesHandler->handle()->items,
        );

        $this->entidades = array_map(
            fn ($e) => ['id' => $e->id, 'label' => $e->nombre],
            $entidadesHandler->handle()->items,
        );

        $this->fechaColecta = date('Y-m-d');
    }

    public function abrirModal(): void
    {
        $this->reset('codigoCatalogo', 'taxonId', 'localidad', 'colector', 'entidadDepositanteId', 'errorMessage');
        $this->resetValidation();
        $this->fechaColecta = date('Y-m-d');
        $this->showModal = true;
    }

    public function registrarEspecimen(RegistrarEspecimenHandler $handler): void
    {
        $this->validateOnly('codigoCatalogo');
        $this->validateOnly('taxonId');
        $this->validateOnly('localidad');
        $this->validateOnly('fechaColecta');
        $this->validateOnly('colector');

        try {
            $handler->handle(new RegistrarEspecimenInput(
                codigoCatalogo: $this->codigoCatalogo,
                taxonId: $this->taxonId,
                localidad: $this->localidad,
                fechaColecta: $this->fechaColecta,
                colector: $this->colector,
                entidadDepositanteId: $this->entidadDepositanteId !== '' ? $this->entidadDepositanteId : null,
            ));

            $this->showModal = false;
            $this->successMessage = "Especímen '{$this->codigoCatalogo}' registrado correctamente.";
            $this->errorMessage = null;

            if ($this->buscado) {
                $this->errorMessage = null;
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function buscar(BuscarEspecimenesHandler $handler): void
    {
        $this->validate([
            'criterio' => 'required|string|in:taxon,localidad,estado',
            'valor' => 'required|string|min:2|max:255',
        ]);

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
        $this->reset('criterio', 'valor', 'especimenes', 'buscado', 'errorMessage', 'successMessage');
        $this->resetValidation();
        $this->criterio = 'taxon';
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.index');
    }
}
