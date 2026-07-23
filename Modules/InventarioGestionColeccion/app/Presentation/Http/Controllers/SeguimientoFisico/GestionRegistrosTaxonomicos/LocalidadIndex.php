<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades\ListarLocalidadesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadesParaSelector\ListarLocalidadesParaSelectorHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadesParaSelector\ListarLocalidadesParaSelectorInput;
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

    /**
     * Todas las localidades canónicas (id, nombre, rango) para el selector
     * "Localidad padre" de los modales. Es la lista COMPLETA, no la página
     * visible: sin esto solo se podían elegir padres de la página actual.
     *
     * @var array<int, array{id: string, nombreCanonico: string, rango: string}>
     */
    public array $localidadesParaPadre = [];

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

    #[Rule('nullable|string|max:120')]
    public ?string $municipality = null;

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

    #[Rule('nullable|string|max:120')]
    public ?string $editMunicipality = null;

    /** Ubicación detectada por coordenadas (texto legible) para mostrar en el modal. */
    public ?string $ubicacionDetectada = null;

    /** Poblado más cercano detectado, ofrecible como nombre canónico. */
    public ?string $pobladoDetectado = null;

    /** Aviso de la geocodificación (p. ej. sin coordenadas o sin resultado). */
    public ?string $geoMensaje = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarLocalidadesHandler $handler): void
    {
        $this->rangos = RangoLocalidad::valoresAceptados();
        $this->cargarLocalidades($handler);
        $this->cargarSelectorPadres();
    }

    private function cargarSelectorPadres(): void
    {
        $this->localidadesParaPadre = app(ListarLocalidadesParaSelectorHandler::class)
            ->handle(new ListarLocalidadesParaSelectorInput)
            ->items;
    }

    public function abrirModal(): void
    {
        $this->reset(
            'nombreCanonico', 'rango', 'padreId',
            'latitud', 'longitud', 'geodeticDatum',
            'country', 'stateProvince', 'municipality',
            'ubicacionDetectada', 'pobladoDetectado', 'geoMensaje',
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
                municipality: $this->municipality,
            ));

            $this->cargarLocalidades($listarHandler, $this->page);
            $this->cargarSelectorPadres();
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
        $this->editMunicipality = $localidad['municipality'] ?? null;
        $this->reset('ubicacionDetectada', 'pobladoDetectado', 'geoMensaje');
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
                municipality: $this->editMunicipality,
            ));

            $this->cargarLocalidades($listarHandler, $this->page);
            $this->cargarSelectorPadres();
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

    /**
     * Geocodificación inversa para el modal de ALTA: con la lat/long ingresadas
     * consulta el servicio y rellena país/provincia/cantón, y guarda el poblado
     * más cercano para ofrecerlo como nombre. Solo sobrescribe lo que el servicio
     * resuelva; si no hay resultado, deja los campos como están.
     */
    public function detectarUbicacion(GeocodificadorInversoPort $geo): void
    {
        $this->geoMensaje = null;
        $this->ubicacionDetectada = null;
        $this->pobladoDetectado = null;

        if ($this->latitud === null || $this->longitud === null) {
            $this->geoMensaje = 'Ingresa latitud y longitud para detectar la ubicación.';

            return;
        }

        $ubicacion = $geo->geocodificar((float) $this->latitud, (float) $this->longitud);

        if ($ubicacion === null) {
            $this->geoMensaje = 'No se pudo determinar la ubicación para esas coordenadas.';

            return;
        }

        $this->country = $ubicacion->country ?? $this->country;
        $this->stateProvince = $ubicacion->stateProvince ?? $this->stateProvince;
        $this->municipality = $ubicacion->municipality ?? $this->municipality;
        $this->pobladoDetectado = $ubicacion->poblado;
        $this->ubicacionDetectada = $ubicacion->displayName;
    }

    /** Igual que detectarUbicacion pero para el modal de EDICIÓN. */
    public function detectarUbicacionEdicion(GeocodificadorInversoPort $geo): void
    {
        $this->geoMensaje = null;
        $this->ubicacionDetectada = null;
        $this->pobladoDetectado = null;

        if ($this->editLatitud === null || $this->editLongitud === null) {
            $this->geoMensaje = 'Ingresa latitud y longitud para detectar la ubicación.';

            return;
        }

        $ubicacion = $geo->geocodificar((float) $this->editLatitud, (float) $this->editLongitud);

        if ($ubicacion === null) {
            $this->geoMensaje = 'No se pudo determinar la ubicación para esas coordenadas.';

            return;
        }

        $this->editCountry = $ubicacion->country ?? $this->editCountry;
        $this->editStateProvince = $ubicacion->stateProvince ?? $this->editStateProvince;
        $this->editMunicipality = $ubicacion->municipality ?? $this->editMunicipality;
        $this->pobladoDetectado = $ubicacion->poblado;
        $this->ubicacionDetectada = $ubicacion->displayName;
    }

    public function usarPobladoComoNombre(): void
    {
        if ($this->pobladoDetectado !== null && $this->pobladoDetectado !== '') {
            $this->nombreCanonico = $this->pobladoDetectado;
        }
    }

    public function usarPobladoComoNombreEdicion(): void
    {
        if ($this->pobladoDetectado !== null && $this->pobladoDetectado !== '') {
            $this->editNombreCanonico = $this->pobladoDetectado;
        }
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
