<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Localidades'])]
final class LocalidadIndex extends Component
{
    use TraduceErroresPersistencia;

    /** @var array<int, array<string, mixed>> */
    public array $localidades = [];

    /** @var string[] */
    public array $rangos = [];

    public int $page = 1;

    public int $perPage = 25;

    public int $totalPaginas = 1;

    public int $totalItems = 0;

    public bool $showModal = false;

    #[Rule('required|string|max:255')]
    public string $nombreCanonico = '';

    #[Rule('required|string')]
    public string $rango = '';

    public string $padreId = '';

    #[Rule('nullable|numeric|min:-90|max:90')]
    public ?float $latitud = null;

    #[Rule('nullable|numeric|min:-180|max:180')]
    public ?float $longitud = null;

    #[Rule('nullable|string|max:60')]
    public ?string $geodeticDatum = null;

    #[Rule('nullable|string|max:120')]
    public ?string $country = null;

    #[Rule('nullable|string|max:120')]
    public ?string $stateProvince = null;

    public bool $showEditModal = false;

    public string $editandoId = '';

    #[Rule('required|string|max:255')]
    public string $editNombreCanonico = '';

    #[Rule('required|string')]
    public string $editRango = '';

    public string $editPadreId = '';

    #[Rule('nullable|numeric|min:-90|max:90')]
    public ?float $editLatitud = null;

    #[Rule('nullable|numeric|min:-180|max:180')]
    public ?float $editLongitud = null;

    #[Rule('nullable|string|max:60')]
    public ?string $editGeodeticDatum = null;

    #[Rule('nullable|string|max:120')]
    public ?string $editCountry = null;

    #[Rule('nullable|string|max:120')]
    public ?string $editStateProvince = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarLocalidadesHandler $handler): void
    {
        $this->rangos = RangoLocalidad::valoresAceptados();
        $this->cargarLocalidades($handler);
    }

    public function abrirModal(): void
    {
        $this->reset(
            'nombreCanonico', 'rango', 'padreId',
            'latitud', 'longitud', 'geodeticDatum',
            'country', 'stateProvince',
            'successMessage', 'errorMessage'
        );
        $this->resetValidation();
        $this->showModal = true;
    }

    public function registrarLocalidad(
        RegistrarLocalidadHandler $registrarHandler,
        ListarLocalidadesHandler $listarHandler,
    ): void {
        $this->validateOnly('nombreCanonico');
        $this->validateOnly('rango');

        try {
            $registrarHandler->handle(new RegistrarLocalidadInput(
                nombreCanonico: $this->nombreCanonico,
                rango: $this->rango,
                padreId: $this->padreId !== '' ? $this->padreId : null,
                latitud: $this->latitud,
                longitud: $this->longitud,
                geodeticDatum: $this->geodeticDatum,
                country: $this->country,
                stateProvince: $this->stateProvince,
            ));

            $this->cargarLocalidades($listarHandler);
            $this->showModal = false;
            $this->successMessage = 'Localidad registrada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function abrirEditModal(string $id): void
    {
        $localidad = collect($this->localidades)->firstWhere('id', $id);

        if ($localidad === null) {
            return;
        }

        $this->editandoId = $id;
        $this->editNombreCanonico = $localidad['nombreCanonico'];
        $this->editRango = $localidad['rango'];
        $this->editPadreId = $localidad['padreId'] ?? '';
        $this->editLatitud = $localidad['latitud'];
        $this->editLongitud = $localidad['longitud'];
        $this->editGeodeticDatum = $localidad['geodeticDatum'] ?? null;
        $this->editCountry = $localidad['country'];
        $this->editStateProvince = $localidad['stateProvince'];
        $this->errorMessage = null;
        $this->showEditModal = true;
    }

    public function actualizarLocalidad(
        ActualizarLocalidadHandler $actualizarHandler,
        ListarLocalidadesHandler $listarHandler,
    ): void {
        $this->validateOnly('editNombreCanonico');
        $this->validateOnly('editRango');

        try {
            $actualizarHandler->handle(new ActualizarLocalidadInput(
                localidadId: $this->editandoId,
                nombreCanonico: $this->editNombreCanonico,
                rango: $this->editRango,
                padreId: $this->editPadreId !== '' ? $this->editPadreId : null,
                latitud: $this->editLatitud,
                longitud: $this->editLongitud,
                geodeticDatum: $this->editGeodeticDatum,
                country: $this->editCountry,
                stateProvince: $this->editStateProvince,
            ));

            $this->cargarLocalidades($listarHandler);
            $this->showEditModal = false;
            $this->successMessage = 'Localidad actualizada correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function nextPage(ListarLocalidadesHandler $handler): void
    {
        if ($this->page < $this->totalPaginas) {
            $this->page++;
            $this->cargarLocalidades($handler, $this->page);
        }
    }

    public function prevPage(ListarLocalidadesHandler $handler): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->cargarLocalidades($handler, $this->page);
        }
    }

    public function goToPage(int $p, ListarLocalidadesHandler $handler): void
    {
        $this->page = max(1, min($p, $this->totalPaginas));
        $this->cargarLocalidades($handler, $this->page);
    }

    private function cargarLocalidades(ListarLocalidadesHandler $handler, ?int $page = null): void
    {
        $output = $handler->handle(new ListarLocalidadesInput(
            page: $page ?? 1,
            perPage: $this->perPage,
        ));

        $this->page = $output->page;
        $this->totalPaginas = $output->totalPaginas;
        $this->totalItems = $output->total;
        $this->localidades = array_map(
            fn ($l) => [
                'id' => $l->id,
                'nombreCanonico' => $l->nombreCanonico,
                'rango' => $l->rango,
                'padreId' => $l->padreId,
                'padreNombre' => $l->padreNombre,
                'latitud' => $l->latitud,
                'longitud' => $l->longitud,
                'country' => $l->country,
                'stateProvince' => $l->stateProvince,
                'municipality' => $l->municipality,
                'geodeticDatum' => $l->geodeticDatum,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        $offset = ($this->page - 1) * $this->perPage;

        return view('inventariogestioncoleccion::admin.taxonomia.localidades.index', [
            'localidadesPaginadas' => $this->localidades,
            'totalPaginas' => $this->totalPaginas,
            'totalItems' => $this->totalItems,
            'inicio' => $this->totalItems > 0 ? $offset + 1 : 0,
            'fin' => min($offset + $this->perPage, $this->totalItems),
        ]);
    }
}
