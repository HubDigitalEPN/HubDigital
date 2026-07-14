<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionHandler;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionInput;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenDivulgableEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Cátalogo divulgado'])]
final class TablaEspecimenesDivulgados extends Component
{
    use WithPagination;

    #[Url(as: 'catalogo')]
    public string $busquedaCatalogo = '';

    #[Url(as: 'taxa')]
    public string $busquedaTaxonomia = '';

    #[Url(as: 'desde')]
    public string $fechaDesde = '';

    #[Url(as: 'hasta')]
    public string $fechaHasta = '';

    #[Url(as: 'colector')]
    public string $colector = '';

    public bool $modalConfigAbierto = false;

    public ?string $occurrenceIDActivo = null;

    /** @var array<string, bool> */
    public array $configuracionEditando = [];

    public bool $configGuardada = false;

    private const FLAG_MAP = [
        'occurrenceIDVisible' => 'occurrence_id_visible',
        'scientificNameVisible' => 'scientific_name_visible',
        'individualCountVisible' => 'individual_count_visible',
        'typeStatusVisible' => 'type_status_visible',
        'typeNotesVisible' => 'type_notes_visible',
        'specimenNotesVisible' => 'specimen_notes_visible',
        'occurrenceStatusVisible' => 'occurrence_status_visible',
        'samplingProtocolVisible' => 'sampling_protocol_visible',
        'recordedByVisible' => 'recorded_by_visible',
        'familyVisible' => 'family_visible',
        'genusVisible' => 'genus_visible',
        'countryVisible' => 'country_visible',
        'stateProvinceVisible' => 'state_province_visible',
        'localityNameVisible' => 'locality_name_visible',
        'decimalLatitudeVisible' => 'decimal_latitude_visible',
        'decimalLongitudeVisible' => 'decimal_longitude_visible',
        'elevationVisible' => 'elevation_visible',
        'eventDateVisible' => 'event_date_visible',
        'casteVisible' => 'caste_visible',
        'lifeStageVisible' => 'life_stage_visible',
    ];

    #[Computed]
    public function especimenesDivulgablesIndexados()
    {
        return EspecimenDivulgableEloquentModel::query()
            ->join(
                'taxonomia.especimenes',
                'taxonomia.especimenes.id',
                '=',
                'divulgacion.especimenes_divulgables.especimen_id'
            )
            ->select('divulgacion.especimenes_divulgables.*', 'taxonomia.especimenes.occurrence_id')
            ->get()
            ->keyBy('occurrence_id');
    }

    public function abrirConfiguracion(string $occurrenceID): void
    {
        $this->occurrenceIDActivo = $occurrenceID;
        $this->configGuardada = false;

        $registro = $this->especimenesDivulgablesIndexados()[$occurrenceID] ?? null;

        if ($registro !== null) {
            $config = [];
            foreach (self::FLAG_MAP as $camel => $snake) {
                $config[$camel] = (bool) $registro->{$snake};
            }
            $this->configuracionEditando = $config;
        } else {
            $this->configuracionEditando = array_fill_keys(array_keys(self::FLAG_MAP), true);
        }

        $this->modalConfigAbierto = true;
    }

    public function cerrarConfiguracion(): void
    {
        $this->modalConfigAbierto = false;
        $this->occurrenceIDActivo = null;
        $this->configuracionEditando = [];
    }

    public function toggleFlag(string $campo): void
    {
        if (isset($this->configuracionEditando[$campo])) {
            $this->configuracionEditando[$campo] = ! $this->configuracionEditando[$campo];
        }
    }

    public function guardarConfiguracion(ModificarConfiguracionDivulgacionHandler $handler): void
    {
        if ($this->occurrenceIDActivo === null) {
            return;
        }

        $configuracion = [];
        foreach (self::FLAG_MAP as $camel => $snake) {
            $configuracion[$camel] = $this->configuracionEditando[$camel] ?? true;
        }

        $handler->handle(new ModificarConfiguracionDivulgacionInput(
            especimenes: [
                [
                    'occurrenceID' => $this->occurrenceIDActivo,
                    'configuracion' => $configuracion,
                ],
            ],
        ));

        $this->configGuardada = true;
        $this->modalConfigAbierto = false;
    }

    public function updatedBusquedaCatalogo(): void
    {
        $this->resetPage();
    }

    public function updatedBusquedaTaxonomia(): void
    {
        $this->resetPage();
    }

    public function updatedFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFechaHasta(): void
    {
        $this->resetPage();
    }

    public function updatedColector(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->busquedaCatalogo = '';
        $this->busquedaTaxonomia = '';
        $this->fechaDesde = '';
        $this->fechaHasta = '';
        $this->colector = '';
        $this->resetPage();
    }

    public function tieneFiltros(): bool
    {
        return $this->busquedaCatalogo !== ''
            || $this->busquedaTaxonomia !== ''
            || $this->fechaDesde !== ''
            || $this->fechaHasta !== ''
            || $this->colector !== '';
    }

    /**
     * Colectores distintos entre los divulgados — para poblar el select.
     *
     * @return list<string>
     */
    #[Computed]
    public function colectoresDisponibles(): array
    {
        return DB::table('divulgacion.especimenes_divulgables as ed')
            ->join('taxonomia.especimenes as te', 'te.id', '=', 'ed.especimen_id')
            ->whereNotNull('te.colector')
            ->where('te.colector', '!=', '')
            ->distinct()
            ->orderBy('te.colector')
            ->pluck('te.colector')
            ->all();
    }

    public function render(): View
    {
        // Familia canónica (rango='familia') por especie: se resuelve una vez y
        // se aplica al filtro de búsqueda y al render, evitando el bug de asumir
        // que familia = padre directo del género.
        $familiasPorEspecie = $this->familiaCanonicaPorEspecie();

        $query = DB::table('divulgacion.especimenes_divulgables as ed')
            ->join('taxonomia.especimenes as te', 'te.id', '=', 'ed.especimen_id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->where('tx_species.rango', 'especie')
            ->select([
                'te.occurrence_id',
                'tx_species.id as species_id',
                'tx_species.nombre_cientifico as scientific_name',
                DB::raw('te.disposition as type_status'),
                'te.colector',
                'tx_genus.nombre_cientifico as genus',
                'te.occurrence_status',
                'te.country',
                DB::raw('(
                    (ed.occurrence_id_visible::int) + (ed.scientific_name_visible::int) +
                    (ed.individual_count_visible::int) + (ed.type_status_visible::int) +
                    (ed.type_notes_visible::int) + (ed.specimen_notes_visible::int) +
                    (ed.occurrence_status_visible::int) + (ed.sampling_protocol_visible::int) +
                    (ed.recorded_by_visible::int) + (ed.family_visible::int) +
                    (ed.genus_visible::int) + (ed.country_visible::int) +
                    (ed.state_province_visible::int) + (ed.locality_name_visible::int) +
                    (ed.decimal_latitude_visible::int) + (ed.decimal_longitude_visible::int) +
                    (ed.elevation_visible::int) + (ed.event_date_visible::int) +
                    (ed.caste_visible::int) + (ed.life_stage_visible::int)
                ) as campos_visibles'),
            ]);

        if ($this->busquedaCatalogo !== '') {
            $query->where('te.occurrence_id', 'ILIKE', '%'.$this->busquedaCatalogo.'%');
        }

        if ($this->busquedaTaxonomia !== '') {
            $termino = '%'.$this->busquedaTaxonomia.'%';
            $terminoLower = mb_strtolower($this->busquedaTaxonomia);
            $especiesConFamiliaMatch = [];
            foreach ($familiasPorEspecie as $speciesId => $familia) {
                if (mb_stripos($familia, $terminoLower) !== false) {
                    $especiesConFamiliaMatch[] = $speciesId;
                }
            }

            $query->where(function ($q) use ($termino, $especiesConFamiliaMatch): void {
                $q->where('tx_species.nombre_cientifico', 'ILIKE', $termino)
                    ->orWhere('tx_genus.nombre_cientifico', 'ILIKE', $termino);

                if ($especiesConFamiliaMatch !== []) {
                    $q->orWhereIn('tx_species.id', $especiesConFamiliaMatch);
                }
            });
        }

        if ($this->fechaDesde !== '') {
            $query->where('te.fecha_colecta', '>=', $this->fechaDesde);
        }

        if ($this->fechaHasta !== '') {
            $query->where('te.fecha_colecta', '<=', $this->fechaHasta);
        }

        if ($this->colector !== '') {
            $query->where('te.colector', $this->colector);
        }

        $especimenes = $query
            ->orderBy('te.occurrence_id')
            ->paginate(25);

        foreach ($especimenes->items() as $fila) {
            $fila->family = $familiasPorEspecie[$fila->species_id] ?? null;
        }

        return view('catalogopublico::livewire.tabla-especimenes-divulgados', [
            'especimenes' => $especimenes,
            'totalCampos' => count(self::FLAG_MAP),
            'especimenesDivulgablesIndexados' => $this->especimenesDivulgablesIndexados(),
        ]);
    }

    /**
     * Mapa {uuid de taxón-especie → nombre científico de su ancestro con rango='familia'}.
     * Recorre la cadena de ancestros por `padre_id` hasta encontrar el rango canónico,
     * saltándose niveles intermedios (tribu, subfamilia, suborden, etc.).
     *
     * @return array<string, string>
     */
    private function familiaCanonicaPorEspecie(): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE cadena AS (
                SELECT tx.id AS raiz, tx.rango, tx.nombre_cientifico, tx.padre_id, 0 AS profundidad
                FROM taxonomia.taxones tx
                WHERE tx.rango = 'especie'
                UNION ALL
                SELECT c.raiz, p.rango, p.nombre_cientifico, p.padre_id, c.profundidad + 1
                FROM cadena c
                JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 20
            )
            SELECT DISTINCT raiz::text AS raiz, nombre_cientifico
            FROM cadena
            WHERE rango = 'familia'
        SQL;

        $mapa = [];
        foreach (DB::select($sql) as $fila) {
            $mapa[$fila->raiz] = $fila->nombre_cientifico;
        }

        return $mapa;
    }
}
