<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\FiltrosPendientes;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesInput;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenDivulgableEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Sincronizar Especímenes'])]
final class SincronizarEspecimenes extends Component
{
    private ProveedorEspecimenesPort $proveedorEspecimenes;

    public function boot(ProveedorEspecimenesPort $proveedorEspecimenes): void
    {
        $this->proveedorEspecimenes = $proveedorEspecimenes;
    }

    public int $paso = 1;

    /** @var string[] */
    public array $seleccionados = [];

    public ?string $especimenActivoId = null;

    /**
     * @var array<string, array<string, bool>>
     *                                         occurrenceID => [ campoVisible => bool, ... ]
     */
    public array $configuracionPorEspecimen = [];

    /** @var string[] */
    public array $occurrenceIDsActualizados = [];

    public bool $sincronizando = false;

    public int $pagina = 1;

    public string $busquedaCatalogo = '';

    public string $busquedaTaxonomia = '';

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public string $colector = '';

    private const int POR_PAGINA = 20;

    /** @var array<string, array<string, bool>> */
    private array $cacheConfiguracionesExistentes = [];

    private const GRUPOS = [
        'Identificación' => [
            ['key' => 'occurrenceIDVisible', 'label' => 'occurrenceID', 'desc' => 'ID del registro', 'sensible' => false],
        ],
        'Taxonomía' => [
            ['key' => 'scientificNameVisible', 'label' => 'Nombre científico', 'desc' => 'Nombre taxonómico completo', 'sensible' => false],
            ['key' => 'familyVisible', 'label' => 'Familia', 'desc' => 'Familia taxonómica', 'sensible' => false],
            ['key' => 'genusVisible', 'label' => 'Género', 'desc' => 'Género taxonómico', 'sensible' => false],
        ],
        'Registro' => [
            ['key' => 'individualCountVisible', 'label' => 'Cantidad individuos', 'desc' => 'Número de individuos', 'sensible' => false],
            ['key' => 'typeStatusVisible', 'label' => 'Tipo de estatus', 'desc' => 'Estado del tipo nomenclatural', 'sensible' => false],
            ['key' => 'casteVisible', 'label' => 'Casta', 'desc' => 'Casta del individuo', 'sensible' => false],
            ['key' => 'lifeStageVisible', 'label' => 'Estadio de vida', 'desc' => 'Etapa del ciclo de vida', 'sensible' => false],
            ['key' => 'typeNotesVisible', 'label' => 'Notas de tipo', 'desc' => 'Anotaciones sobre el tipo', 'sensible' => true],
            ['key' => 'specimenNotesVisible', 'label' => 'Notas del espécimen', 'desc' => 'Observaciones del espécimen', 'sensible' => true],
            ['key' => 'occurrenceStatusVisible', 'label' => 'Estado de ocurrencia', 'desc' => 'Presente / Ausente', 'sensible' => false],
        ],
        'Recolección' => [
            ['key' => 'samplingProtocolVisible', 'label' => 'Protocolo de muestreo', 'desc' => 'Método de recolección', 'sensible' => false],
            ['key' => 'eventDateVisible', 'label' => 'Fecha de recolección', 'desc' => 'Fecha de colecta', 'sensible' => false],
            ['key' => 'recordedByVisible', 'label' => 'Registrado por', 'desc' => 'Nombre del colector', 'sensible' => true],
        ],
        'Localización' => [
            ['key' => 'countryVisible', 'label' => 'País', 'desc' => 'País de origen', 'sensible' => false],
            ['key' => 'stateProvinceVisible', 'label' => 'Provincia', 'desc' => 'Estado o provincia', 'sensible' => false],
            ['key' => 'localityNameVisible', 'label' => 'Localidad', 'desc' => 'Nombre del sitio de colecta', 'sensible' => true],
            ['key' => 'elevationVisible', 'label' => 'Elevación', 'desc' => 'Rango altitudinal (m)', 'sensible' => false],
            ['key' => 'decimalLatitudeVisible', 'label' => 'Latitud', 'desc' => 'Coordenada decimal', 'sensible' => true],
            ['key' => 'decimalLongitudeVisible', 'label' => 'Longitud', 'desc' => 'Coordenada decimal', 'sensible' => true],
        ],
    ];

    public function toggleSeleccion(string $occurrenceID): void
    {
        if (in_array($occurrenceID, $this->seleccionados, true)) {
            $this->seleccionados = array_values(
                array_filter($this->seleccionados, fn ($id) => $id !== $occurrenceID)
            );
            unset($this->configuracionPorEspecimen[$occurrenceID]);
            if ($this->especimenActivoId === $occurrenceID) {
                $this->especimenActivoId = null;
            }
        } else {
            $this->seleccionados[] = $occurrenceID;
            $this->inicializarConfiguracionLote([$occurrenceID]);
            if ($this->especimenActivoId === null) {
                $this->especimenActivoId = $occurrenceID;
            }
        }
    }

    public function seleccionarTodos(): void
    {
        $idsPagina = array_map(
            fn (DatosEspecimenProveedor $d) => $d->occurrenceId,
            $this->especimenesPagina()
        );

        $nuevosIds = array_values(array_filter(
            $idsPagina,
            fn ($id) => ! in_array($id, $this->seleccionados, true)
        ));

        if (count($nuevosIds) > 0) {
            $this->seleccionados = array_merge($this->seleccionados, $nuevosIds);
            $this->inicializarConfiguracionLote($nuevosIds);
        }

        if ($this->especimenActivoId === null && count($this->seleccionados) > 0) {
            $this->especimenActivoId = $this->seleccionados[0];
        }
    }

    public function irAPagina(int $pagina): void
    {
        $total = max(1, (int) ceil($this->totalPendientes() / self::POR_PAGINA));
        $this->pagina = max(1, min($pagina, $total));
    }

    public function deseleccionarTodos(): void
    {
        $this->seleccionados = [];
        $this->configuracionPorEspecimen = [];
        $this->especimenActivoId = null;
    }

    public function setEspecimenActivo(string $occurrenceID): void
    {
        if (in_array($occurrenceID, $this->seleccionados, true)) {
            $this->especimenActivoId = $occurrenceID;
        }
    }

    public function toggleCampo(string $occurrenceID, string $campo): void
    {
        if (isset($this->configuracionPorEspecimen[$occurrenceID][$campo])) {
            $this->configuracionPorEspecimen[$occurrenceID][$campo]
                = ! $this->configuracionPorEspecimen[$occurrenceID][$campo];
        }
    }

    public function habilitarTodo(string $occurrenceID): void
    {
        if (isset($this->configuracionPorEspecimen[$occurrenceID])) {
            foreach ($this->configuracionPorEspecimen[$occurrenceID] as $campo => $_) {
                $this->configuracionPorEspecimen[$occurrenceID][$campo] = true;
            }
        }
    }

    public function copiarConfigDeActivo(string $occurrenceIDDestino): void
    {
        if ($this->especimenActivoId === null
            || ! isset($this->configuracionPorEspecimen[$this->especimenActivoId])) {
            return;
        }
        $this->configuracionPorEspecimen[$occurrenceIDDestino]
            = $this->configuracionPorEspecimen[$this->especimenActivoId];
    }

    public function copiarConfigATodos(): void
    {
        if ($this->especimenActivoId === null
            || ! isset($this->configuracionPorEspecimen[$this->especimenActivoId])) {
            return;
        }
        foreach ($this->seleccionados as $id) {
            if ($id !== $this->especimenActivoId) {
                $this->configuracionPorEspecimen[$id]
                    = $this->configuracionPorEspecimen[$this->especimenActivoId];
            }
        }
    }

    public function avanzarPaso(): void
    {
        if ($this->paso === 1 && count($this->seleccionados) === 0) {
            return;
        }

        if ($this->paso === 1 && $this->especimenActivoId === null && ! empty($this->seleccionados)) {
            $this->especimenActivoId = $this->seleccionados[0];
        }

        if ($this->paso < 3) {
            $this->paso++;
        }
    }

    public function retrocederPaso(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    public function sincronizar(SincronizarEspecimenesHandler $handler): void
    {
        $this->sincronizando = true;

        $especimenes = array_map(function (string $id): array {
            return [
                'occurrenceID' => $id,
                'configuracion' => $this->configuracionPorEspecimen[$id] ?? null,
            ];
        }, $this->seleccionados);

        $output = $handler->handle(new SincronizarEspecimenesInput(
            especimenes: $especimenes,
        ));

        $this->occurrenceIDsActualizados = $output->occurrenceIDsActualizados;
        $this->sincronizando = false;
        $this->paso = 3;
    }

    public function updatedBusquedaCatalogo(): void
    {
        $this->pagina = 1;
    }

    public function updatedBusquedaTaxonomia(): void
    {
        $this->pagina = 1;
    }

    public function updatedFechaDesde(): void
    {
        $this->pagina = 1;
    }

    public function updatedFechaHasta(): void
    {
        $this->pagina = 1;
    }

    public function updatedColector(): void
    {
        $this->pagina = 1;
    }

    public function limpiarFiltros(): void
    {
        $this->busquedaCatalogo = '';
        $this->busquedaTaxonomia = '';
        $this->fechaDesde = '';
        $this->fechaHasta = '';
        $this->colector = '';
        $this->pagina = 1;
    }

    #[Computed]
    public function filtros(): FiltrosPendientes
    {
        return new FiltrosPendientes(
            busquedaCatalogo: $this->busquedaCatalogo !== '' ? $this->busquedaCatalogo : null,
            busquedaTaxonomia: $this->busquedaTaxonomia !== '' ? $this->busquedaTaxonomia : null,
            fechaDesde: $this->fechaDesde !== '' ? $this->fechaDesde : null,
            fechaHasta: $this->fechaHasta !== '' ? $this->fechaHasta : null,
            colector: $this->colector !== '' ? $this->colector : null,
        );
    }

    /**
     * Colectores distintos entre los pendientes — para poblar el select del filtro.
     *
     * @return list<string>
     */
    #[Computed]
    public function colectoresDisponibles(): array
    {
        return $this->proveedorEspecimenes->obtenerColectoresPendientes($this->sincronizadosIds());
    }

    /**
     * IDs de espécimen (taxonomia.especimenes.id) ya sincronizados con divulgación.
     * Cacheado en el request — Livewire lo re-lee entre requests, que es lo esperado
     * para reflejar cambios recientes.
     */
    #[Computed]
    public function sincronizadosIds(): array
    {
        return EspecimenDivulgableEloquentModel::pluck('especimen_id')->all();
    }

    /**
     * Total de especímenes pendientes — una sola consulta COUNT.
     */
    #[Computed]
    public function totalPendientes(): int
    {
        return $this->proveedorEspecimenes->contar($this->sincronizadosIds(), $this->filtros());
    }

    /**
     * Página actual de especímenes pendientes.
     * Paginación real en base de datos: siempre trae a lo sumo POR_PAGINA registros.
     *
     * @return list<DatosEspecimenProveedor>
     */
    #[Computed]
    public function especimenesPagina(): array
    {
        return $this->proveedorEspecimenes->obtenerPaginado(
            offset: ($this->pagina - 1) * self::POR_PAGINA,
            limit: self::POR_PAGINA,
            especimenIdsExcluir: $this->sincronizadosIds(),
            filtros: $this->filtros(),
        );
    }

    /**
     * Vista consumida por la plantilla: paso 1 muestra la página; paso 2 muestra
     * la lista de seleccionados resolviendo cada uno por occurrence_id.
     */
    #[Computed]
    public function especimenesEnCache()
    {
        if ($this->paso === 1) {
            return collect($this->especimenesPagina())
                ->map(fn (DatosEspecimenProveedor $d) => $this->dtoParaVista($d))
                ->values();
        }

        if (count($this->seleccionados) === 0) {
            return collect();
        }

        return collect($this->proveedorEspecimenes->buscarPorOccurrenceIds($this->seleccionados))
            ->map(fn (DatosEspecimenProveedor $d) => $this->dtoParaVista($d))
            ->sortBy('occurrence_id')
            ->values();
    }

    private function dtoParaVista(DatosEspecimenProveedor $datos): \stdClass
    {
        $obj = new \stdClass;
        $obj->occurrence_id = $datos->occurrenceId;
        $obj->scientific_name = $datos->scientificName;
        $obj->type_status = $datos->typeStatus;
        $obj->colector = $datos->recordedBy;
        $obj->family = $datos->family;
        $obj->occurrence_status = $datos->occurrenceStatus;

        return $obj;
    }

    /**
     * Inicializa configuración en lote para múltiples especímenes.
     * Mejora: una query para traer las configuraciones existentes, no múltiples queries.
     *
     * @param  array<string>  $occurrenceIDs
     */
    private function inicializarConfiguracionLote(array $occurrenceIDs): void
    {
        if (count($occurrenceIDs) === 0) {
            return;
        }

        // Traer configuraciones existentes en UNA SOLA QUERY (JOIN para resolver occurrence_id)
        $existentes = EspecimenDivulgableEloquentModel::query()
            ->join(
                'taxonomia.especimenes',
                'taxonomia.especimenes.id',
                '=',
                'divulgacion.especimenes_divulgables.especimen_id'
            )
            ->whereIn('taxonomia.especimenes.occurrence_id', $occurrenceIDs)
            ->get([
                'taxonomia.especimenes.occurrence_id',
                'divulgacion.especimenes_divulgables.occurrence_id_visible',
                'divulgacion.especimenes_divulgables.scientific_name_visible',
                'divulgacion.especimenes_divulgables.individual_count_visible',
                'divulgacion.especimenes_divulgables.type_status_visible',
                'divulgacion.especimenes_divulgables.type_notes_visible',
                'divulgacion.especimenes_divulgables.specimen_notes_visible',
                'divulgacion.especimenes_divulgables.occurrence_status_visible',
                'divulgacion.especimenes_divulgables.sampling_protocol_visible',
                'divulgacion.especimenes_divulgables.recorded_by_visible',
                'divulgacion.especimenes_divulgables.family_visible',
                'divulgacion.especimenes_divulgables.genus_visible',
                'divulgacion.especimenes_divulgables.country_visible',
                'divulgacion.especimenes_divulgables.state_province_visible',
                'divulgacion.especimenes_divulgables.locality_name_visible',
                'divulgacion.especimenes_divulgables.decimal_latitude_visible',
                'divulgacion.especimenes_divulgables.decimal_longitude_visible',
                'divulgacion.especimenes_divulgables.elevation_visible',
                'divulgacion.especimenes_divulgables.event_date_visible',
                'divulgacion.especimenes_divulgables.caste_visible',
                'divulgacion.especimenes_divulgables.life_stage_visible',
            ])
            ->keyBy('occurrence_id');

        $camposDefault = array_merge(...array_values(self::GRUPOS));
        $camposKeys = array_column($camposDefault, 'key');

        foreach ($occurrenceIDs as $occurrenceID) {
            if (isset($existentes[$occurrenceID])) {
                $existente = $existentes[$occurrenceID];
                $this->configuracionPorEspecimen[$occurrenceID] = [
                    'occurrenceIDVisible' => (bool) $existente->occurrence_id_visible,
                    'scientificNameVisible' => (bool) $existente->scientific_name_visible,
                    'individualCountVisible' => (bool) $existente->individual_count_visible,
                    'typeStatusVisible' => (bool) $existente->type_status_visible,
                    'typeNotesVisible' => (bool) $existente->type_notes_visible,
                    'specimenNotesVisible' => (bool) $existente->specimen_notes_visible,
                    'occurrenceStatusVisible' => (bool) $existente->occurrence_status_visible,
                    'samplingProtocolVisible' => (bool) $existente->sampling_protocol_visible,
                    'recordedByVisible' => (bool) $existente->recorded_by_visible,
                    'familyVisible' => (bool) $existente->family_visible,
                    'genusVisible' => (bool) $existente->genus_visible,
                    'countryVisible' => (bool) $existente->country_visible,
                    'stateProvinceVisible' => (bool) $existente->state_province_visible,
                    'localityNameVisible' => (bool) $existente->locality_name_visible,
                    'decimalLatitudeVisible' => (bool) $existente->decimal_latitude_visible,
                    'decimalLongitudeVisible' => (bool) $existente->decimal_longitude_visible,
                    'elevationVisible' => (bool) $existente->elevation_visible,
                    'eventDateVisible' => (bool) $existente->event_date_visible,
                    'casteVisible' => (bool) $existente->caste_visible,
                    'lifeStageVisible' => (bool) $existente->life_stage_visible,
                ];
            } else {
                $this->configuracionPorEspecimen[$occurrenceID] = array_fill_keys($camposKeys, true);
            }
        }
    }

    public function render(): View
    {
        $totalPendientes = $this->totalPendientes();
        $totalPaginas = max(1, (int) ceil($totalPendientes / self::POR_PAGINA));

        return view('catalogopublico::livewire.sincronizar-especimenes', [
            'especimenes' => $this->especimenesEnCache(),
            'grupos' => self::GRUPOS,
            'totalPendientes' => $totalPendientes,
            'totalPaginas' => $totalPaginas,
        ]);
    }
}
