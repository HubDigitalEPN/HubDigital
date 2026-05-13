<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega\GenerarActaEntregaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes\ListarEntidadesDepositantesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

#[Layout('layouts.app', params: ['title' => 'Entidades Depositantes'])]
final class EntidadDepositanteIndex extends Component
{
    public array $entidades = [];

    public array $tipos = [];

    public int $page = 1;

    public int $perPage = 15;

    public bool $showModal = false;

    #[Rule('required|string|max:255')]
    public string $nombre = '';

    #[Rule('required|string')]
    public string $tipo = '';

    #[Rule('required|string|max:255')]
    public string $contacto = '';

    public bool $showEditModal = false;

    public string $editandoId = '';

    #[Rule('required|string|max:255')]
    public string $editNombre = '';

    #[Rule('required|string')]
    public string $editTipo = '';

    #[Rule('required|string|max:255')]
    public string $editContacto = '';

    public bool $showActaModal = false;

    public string $actaEntidadId = '';

    public string $actaEntidadNombre = '';

    public ?string $actaPdfRuta = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarEntidadesDepositantesHandler $handler): void
    {
        $this->tipos = array_column(TipoEntidadDepositante::cases(), 'value');
        $this->cargarEntidades($handler);
    }

    public function abrirModal(): void
    {
        $this->reset('nombre', 'tipo', 'contacto', 'successMessage', 'errorMessage');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function registrarEntidad(
        RegistrarEntidadDepositanteHandler $registrarHandler,
        ListarEntidadesDepositantesHandler $listarHandler,
    ): void {
        $this->validateOnly('nombre');
        $this->validateOnly('tipo');
        $this->validateOnly('contacto');

        try {
            $registrarHandler->handle(new RegistrarEntidadDepositanteInput(
                nombre: $this->nombre,
                tipo: $this->tipo,
                contacto: $this->contacto,
            ));

            $this->cargarEntidades($listarHandler);
            $this->showModal = false;
            $this->successMessage = 'Entidad depositante registrada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function abrirEditModal(string $id): void
    {
        $entidad = collect($this->entidades)->firstWhere('id', $id);

        if ($entidad === null) {
            return;
        }

        $this->editandoId = $id;
        $this->editNombre = $entidad['nombre'];
        $this->editTipo = $entidad['tipo'];
        $this->editContacto = $entidad['contacto'];
        $this->errorMessage = null;
        $this->showEditModal = true;
    }

    public function actualizarEntidad(
        ActualizarEntidadDepositanteHandler $actualizarHandler,
        ListarEntidadesDepositantesHandler $listarHandler,
    ): void {
        $this->validateOnly('editNombre');
        $this->validateOnly('editTipo');
        $this->validateOnly('editContacto');

        try {
            $actualizarHandler->handle(new ActualizarEntidadDepositanteInput(
                entidadId: $this->editandoId,
                nombre: $this->editNombre,
                tipo: $this->editTipo,
                contacto: $this->editContacto,
            ));

            $this->cargarEntidades($listarHandler);
            $this->showEditModal = false;
            $this->successMessage = 'Entidad depositante actualizada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function generarActa(string $id, GenerarActaEntregaHandler $handler): void
    {
        $entidad = collect($this->entidades)->firstWhere('id', $id);

        $this->actaEntidadId = $id;
        $this->actaEntidadNombre = $entidad['nombre'] ?? '';
        $this->actaPdfRuta = null;
        $this->errorMessage = null;

        try {
            $output = $handler->handle(new GenerarActaEntregaInput(
                entidadDepositanteId: $id,
            ));

            $this->actaPdfRuta = $output->pdfRuta;
            $this->showActaModal = true;
            $this->successMessage = "Acta generada para {$output->entidadNombre}: {$output->totalEspecimenes} especímenes.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function nextPage(): void
    {
        if ($this->page < (int) ceil(count($this->entidades) / $this->perPage)) {
            $this->page++;
        }
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function goToPage(int $p): void
    {
        $this->page = max(1, min($p, (int) ceil(count($this->entidades) / $this->perPage)));
    }

    private function cargarEntidades(ListarEntidadesDepositantesHandler $handler): void
    {
        $this->page = 1;
        $output = $handler->handle();
        $this->entidades = array_map(
            fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->nombre,
                'tipo' => $e->tipo,
                'contacto' => $e->contacto,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        $total = count($this->entidades);
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->perPage) : 1;
        $offset = ($this->page - 1) * $this->perPage;

        return view('inventariogestioncoleccion::admin.taxonomia.entidades.index', [
            'entidadesPaginadas' => array_slice($this->entidades, $offset, $this->perPage),
            'totalPaginas' => $totalPaginas,
            'totalItems' => $total,
            'inicio' => $total > 0 ? $offset + 1 : 0,
            'fin' => min($offset + $this->perPage, $total),
        ]);
    }
}
