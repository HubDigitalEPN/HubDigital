<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;

#[Layout('layouts.admin')]
final class GabineteShow extends Component
{
    public string $gabineteId = '';

    public array $gabinete = [];

    public array $ranuras = [];

    public bool $showAgregarRanura = false;

    #[Rule('required|integer|min:1|max:25')]
    public int $numeroRanura = 1;

    #[Rule('nullable|string|max:255')]
    public ?string $familiaTaxonomicaEsperadaId = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        string $id,
        ListarGabineteHandler $listarGabinetes,
        ListarRanurasGabineteHandler $listarRanuras,
    ): void {
        $this->gabineteId = $id;
        $this->cargarGabinete($listarGabinetes);
        $this->cargarRanuras($listarRanuras);
    }

    public function agregarRanura(
        CrearRanuraGabineteHandler $crearHandler,
        ListarRanurasGabineteHandler $listarHandler,
    ): void {
        $this->validate();

        try {
            $crearHandler->handle(new CrearRanuraGabineteInput(
                gabineteId: $this->gabineteId,
                numeroRanura: $this->numeroRanura,
                familiaTaxonomicaEsperadaId: $this->familiaTaxonomicaEsperadaId ?: null,
            ));

            $this->cargarRanuras($listarHandler);
            $this->showAgregarRanura = false;
            $this->reset('numeroRanura', 'familiaTaxonomicaEsperadaId');
            $this->resetValidation();
            $this->successMessage = 'Ranura agregada correctamente.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function cargarGabinete(ListarGabineteHandler $handler): void
    {
        foreach ($handler->handle()->items as $g) {
            if ($g->id === $this->gabineteId) {
                $this->gabinete = [
                    'id' => $g->id,
                    'codigo' => $g->codigo,
                    'nombre' => $g->nombre,
                    'totalRanuras' => $g->totalRanuras,
                    'activo' => $g->activo,
                ];
                break;
            }
        }
    }

    private function cargarRanuras(ListarRanurasGabineteHandler $handler): void
    {
        $output = $handler->handle(new ListarRanurasGabineteInput($this->gabineteId));
        $this->ranuras = array_map(
            fn ($r) => [
                'id' => $r->id,
                'gabineteId' => $r->gabineteId,
                'numeroRanura' => $r->numeroRanura,
                'familiaTaxonomicaEsperadaId' => $r->familiaTaxonomicaEsperadaId,
                'activa' => $r->activa,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.gabinetes.show');
    }
}
