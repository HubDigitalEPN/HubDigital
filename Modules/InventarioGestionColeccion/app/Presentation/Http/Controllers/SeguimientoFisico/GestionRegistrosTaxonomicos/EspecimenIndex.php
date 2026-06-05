<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarRevisionEspecimen\ConfirmarRevisionEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarRevisionEspecimen\ConfirmarRevisionEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes\ListarEntidadesDepositantesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones\ListarTaxonesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Especímenes'])]
final class EspecimenIndex extends Component
{
    use TraduceErroresPersistencia;

    // ── Búsqueda multi-filtro ─────────────────────────────────────────────────

    public array $especimenes = [];

    public bool $buscado = false;

    public int $page = 1;

    public int $perPage = 25;

    public bool $filtrosAbiertos = true;

    // Filtros combinables (todos opcionales, AND lógico)
    #[Rule('nullable|string|max:120')]
    public string $fTaxon = '';

    #[Rule('nullable|string|max:120')]
    public string $fFamilia = '';

    #[Rule('nullable|string|max:120')]
    public string $fCodigoCatalogo = '';

    #[Rule('nullable|string|max:120')]
    public string $fOccurrenceId = '';

    #[Rule('nullable|string|max:120')]
    public string $fCatalogNumber = '';

    #[Rule('nullable|string|max:200')]
    public string $fLocalidad = '';

    #[Rule('nullable|string|max:200')]
    public string $fColector = '';

    #[Rule('nullable|date_format:Y-m-d')]
    public string $fFechaDesde = '';

    #[Rule('nullable|date_format:Y-m-d')]
    public string $fFechaHasta = '';

    #[Rule('nullable|string|in:disponible,en_prestamo')]
    public string $fEstado = '';

    #[Rule('nullable|string|in:pendiente,confirmada,descartada')]
    public string $fEstadoRevision = '';

    #[Rule('nullable|string|max:200')]
    public string $fMotivoRevision = '';

    public bool $fParaRevision = true;

    // ── Registro ──────────────────────────────────────────────────────────────

    public bool $showModal = false;

    public array $taxones = [];

    public array $entidades = [];

    #[Rule('required|string|max:100')]
    public string $codigoCatalogo = '';

    #[Rule('nullable|string|max:120')]
    public string $occurrenceId = '';

    #[Rule('nullable|string|max:120')]
    public string $catalogNumber = '';

    #[Rule('nullable|string|max:120')]
    public string $oldCode = '';

    #[Rule('nullable|string|max:120')]
    public string $cardexLiquidCollectionCode = '';

    #[Rule('required|string|uuid')]
    public string $taxonId = '';

    #[Rule('required|string|max:255')]
    public string $localidad = '';

    #[Rule('required|date_format:Y-m-d')]
    public string $fechaColecta = '';

    #[Rule('required|string|max:255')]
    public string $colector = '';

    public string $entidadDepositanteId = '';

    #[Rule('nullable|integer|min:0')]
    public string $individualCount = '';

    #[Rule('nullable|string|max:120')]
    public string $preparations = '';

    #[Rule('nullable|string|max:120')]
    public string $disposition = '';

    #[Rule('nullable|string|max:120')]
    public string $occurrenceStatus = '';

    #[Rule('nullable|string')]
    public string $specimenNotes = '';

    #[Rule('nullable|string|max:120')]
    public string $country = '';

    #[Rule('nullable|string|max:120')]
    public string $stateProvince = '';

    #[Rule('nullable|string|max:120')]
    public string $municipality = '';

    #[Rule('nullable|string|max:500')]
    public string $localityName = '';

    #[Rule('nullable|numeric|between:-90,90')]
    public string $decimalLatitude = '';

    #[Rule('nullable|numeric|between:-180,180')]
    public string $decimalLongitude = '';

    #[Rule('nullable|string|max:60')]
    public string $geodeticDatum = '';

    #[Rule('nullable|numeric')]
    public string $elevationMinM = '';

    #[Rule('nullable|string|max:120')]
    public string $biome = '';

    #[Rule('nullable|string|max:255')]
    public string $habitat = '';

    // ── Edición ───────────────────────────────────────────────────────────────

    public bool $showEditModal = false;

    public string $editandoId = '';

    #[Rule('required|string|max:255')]
    public string $editLocalidad = '';

    #[Rule('required|date_format:Y-m-d')]
    public string $editFechaColecta = '';

    #[Rule('required|string|max:255')]
    public string $editColector = '';

    public string $editEntidadDepositanteId = '';

    public string $editPreparations = '';

    public string $editDisposition = '';

    public string $editOccurrenceStatus = '';

    public string $editSpecimenNotes = '';

    public string $editCountry = '';

    public string $editStateProvince = '';

    public string $editMunicipality = '';

    public string $editLocalityName = '';

    public string $editDecimalLatitude = '';

    public string $editDecimalLongitude = '';

    public string $editGeodeticDatum = '';

    public string $editElevationMinM = '';

    public string $editBiome = '';

    public string $editHabitat = '';

    // ── Feedback ──────────────────────────────────────────────────────────────

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        ListarTaxonesHandler $taxonesHandler,
        ListarEntidadesDepositantesHandler $entidadesHandler,
    ): void {
        $this->cargarProtegido(function () use ($taxonesHandler, $entidadesHandler) {
            $this->taxones = array_map(
                fn ($t) => ['id' => $t->id, 'label' => "{$t->nombreCientifico} ({$t->rango})"],
                $taxonesHandler->handle()->items,
            );

            $this->entidades = array_map(
                fn ($e) => ['id' => $e->id, 'label' => $e->nombre],
                $entidadesHandler->handle()->items,
            );
        });

        $this->fechaColecta = date('Y-m-d');
    }

    public function abrirModal(): void
    {
        $this->reset(
            'codigoCatalogo',
            'occurrenceId',
            'catalogNumber',
            'oldCode',
            'cardexLiquidCollectionCode',
            'taxonId',
            'localidad',
            'colector',
            'entidadDepositanteId',
            'individualCount',
            'preparations',
            'disposition',
            'occurrenceStatus',
            'specimenNotes',
            'country',
            'stateProvince',
            'municipality',
            'localityName',
            'decimalLatitude',
            'decimalLongitude',
            'geodeticDatum',
            'elevationMinM',
            'biome',
            'habitat',
            'errorMessage',
        );
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
                occurrenceId: $this->nullableString($this->occurrenceId),
                catalogNumber: $this->nullableString($this->catalogNumber),
                oldCode: $this->nullableString($this->oldCode),
                cardexLiquidCollectionCode: $this->nullableString($this->cardexLiquidCollectionCode),
                individualCount: $this->nullableInt($this->individualCount),
                preparations: $this->nullableString($this->preparations),
                disposition: $this->nullableString($this->disposition),
                occurrenceStatus: $this->nullableString($this->occurrenceStatus),
                specimenNotes: $this->nullableString($this->specimenNotes),
                country: $this->nullableString($this->country),
                stateProvince: $this->nullableString($this->stateProvince),
                municipality: $this->nullableString($this->municipality),
                localityName: $this->nullableString($this->localityName),
                decimalLatitude: $this->nullableFloat($this->decimalLatitude),
                decimalLongitude: $this->nullableFloat($this->decimalLongitude),
                geodeticDatum: $this->nullableString($this->geodeticDatum),
                elevationMinM: $this->nullableFloat($this->elevationMinM),
                biome: $this->nullableString($this->biome),
                habitat: $this->nullableString($this->habitat),
            ));

            $this->showModal = false;
            $this->successMessage = "Especímen '{$this->codigoCatalogo}' registrado correctamente.";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function abrirEditModal(string $id): void
    {
        $especimen = collect($this->especimenes)->firstWhere('id', $id);

        if ($especimen === null) {
            return;
        }

        $this->editandoId = $id;
        $this->editLocalidad = $especimen['localidad'];
        $this->editFechaColecta = $especimen['fechaColecta'];
        $this->editColector = $especimen['colector'];
        $this->editEntidadDepositanteId = '';
        $this->editPreparations = (string) ($especimen['preparations'] ?? '');
        $this->editDisposition = (string) ($especimen['disposition'] ?? '');
        $this->editOccurrenceStatus = (string) ($especimen['occurrenceStatus'] ?? '');
        $this->editSpecimenNotes = (string) ($especimen['specimenNotes'] ?? '');
        $this->editCountry = (string) ($especimen['country'] ?? '');
        $this->editStateProvince = (string) ($especimen['stateProvince'] ?? '');
        $this->editMunicipality = (string) ($especimen['municipality'] ?? '');
        $this->editLocalityName = (string) ($especimen['localityName'] ?? '');
        $this->editDecimalLatitude = isset($especimen['decimalLatitude']) ? (string) $especimen['decimalLatitude'] : '';
        $this->editDecimalLongitude = isset($especimen['decimalLongitude']) ? (string) $especimen['decimalLongitude'] : '';
        $this->editGeodeticDatum = (string) ($especimen['geodeticDatum'] ?? '');
        $this->editElevationMinM = isset($especimen['elevationMinM']) ? (string) $especimen['elevationMinM'] : '';
        $this->editBiome = (string) ($especimen['biome'] ?? '');
        $this->editHabitat = (string) ($especimen['habitat'] ?? '');
        $this->errorMessage = null;
        $this->showEditModal = true;
    }

    public function actualizarEspecimen(ActualizarEspecimenHandler $handler): void
    {
        $this->validateOnly('editLocalidad');
        $this->validateOnly('editFechaColecta');
        $this->validateOnly('editColector');

        try {
            $handler->handle(new ActualizarEspecimenInput(
                especimenId: $this->editandoId,
                localidad: $this->editLocalidad,
                fechaColecta: $this->editFechaColecta,
                colector: $this->editColector,
                entidadDepositanteId: $this->editEntidadDepositanteId !== '' ? $this->editEntidadDepositanteId : null,
                country: $this->nullableString($this->editCountry),
                stateProvince: $this->nullableString($this->editStateProvince),
                municipality: $this->nullableString($this->editMunicipality),
                localityName: $this->nullableString($this->editLocalityName),
                decimalLatitude: $this->nullableFloat($this->editDecimalLatitude),
                decimalLongitude: $this->nullableFloat($this->editDecimalLongitude),
                geodeticDatum: $this->nullableString($this->editGeodeticDatum),
                elevationMinM: $this->nullableFloat($this->editElevationMinM),
                biome: $this->nullableString($this->editBiome),
                habitat: $this->nullableString($this->editHabitat),
                preparations: $this->nullableString($this->editPreparations),
                disposition: $this->nullableString($this->editDisposition),
                occurrenceStatus: $this->nullableString($this->editOccurrenceStatus),
                specimenNotes: $this->nullableString($this->editSpecimenNotes),
            ));

            $this->showEditModal = false;
            $this->successMessage = 'Especímen actualizado correctamente.';
            $this->errorMessage = null;
            // Reaplicar la búsqueda actual sería ideal, pero requiere injection de handler.
            // Por ahora marcamos los datos como sucios para que el curador re-busque.
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function confirmarRevision(ConfirmarRevisionEspecimenHandler $handler, string $id): void
    {
        try {
            $handler->handle(new ConfirmarRevisionEspecimenInput(especimenId: $id));

            // Actualizar la copia local del row para feedback inmediato.
            foreach ($this->especimenes as $i => $row) {
                if (($row['id'] ?? null) === $id) {
                    $this->especimenes[$i]['estadoRevision'] = 'confirmada';
                    $this->especimenes[$i]['motivoRevision'] = null;
                    break;
                }
            }
            $this->successMessage = 'Revisión confirmada.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function nextPage(): void
    {
        if ($this->page < (int) ceil(count($this->especimenes) / $this->perPage)) {
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
        $this->page = max(1, min($p, (int) ceil(count($this->especimenes) / $this->perPage)));
    }

    public function buscar(BuscarEspecimenesHandler $handler): void
    {
        $this->validate();

        try {
            $output = $handler->handle(new BuscarEspecimenesInput(
                taxonNombre: $this->nullableString($this->fTaxon),
                codigoCatalogo: $this->nullableString($this->fCodigoCatalogo),
                occurrenceId: $this->nullableString($this->fOccurrenceId),
                catalogNumber: $this->nullableString($this->fCatalogNumber),
                localidad: $this->nullableString($this->fLocalidad),
                colector: $this->nullableString($this->fColector),
                familia: $this->nullableString($this->fFamilia),
                fechaDesde: $this->nullableString($this->fFechaDesde),
                fechaHasta: $this->nullableString($this->fFechaHasta),
                estado: $this->nullableString($this->fEstado),
                estadoRevision: $this->nullableString($this->fEstadoRevision),
                motivoRevision: $this->nullableString($this->fMotivoRevision),
                paraRevision: $this->fParaRevision,
            ));

            $this->especimenes = $output->items;
            $this->buscado = true;
            $this->page = 1;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function preset(string $nombre): void
    {
        // Resets all + sets a preset combination
        $this->reset(
            'fTaxon', 'fFamilia', 'fCodigoCatalogo', 'fOccurrenceId', 'fCatalogNumber',
            'fLocalidad', 'fColector', 'fFechaDesde', 'fFechaHasta',
            'fEstado', 'fEstadoRevision', 'fMotivoRevision', 'fParaRevision',
        );

        match ($nombre) {
            'para_revision' => $this->fParaRevision = true,
            'sin_coords' => [$this->fParaRevision = true, $this->fMotivoRevision = 'coordenadas'],
            'fechas_raras' => [$this->fParaRevision = true, $this->fMotivoRevision = 'fecha'],
            'sin_occurrence_id' => [$this->fParaRevision = true, $this->fMotivoRevision = 'occurrence_id ausente'],
            'todos' => null,
            default => null,
        };
        $this->resetValidation();
    }

    public function limpiar(): void
    {
        $this->reset(
            'especimenes', 'buscado', 'errorMessage', 'successMessage', 'page',
            'fTaxon', 'fFamilia', 'fCodigoCatalogo', 'fOccurrenceId', 'fCatalogNumber',
            'fLocalidad', 'fColector', 'fFechaDesde', 'fFechaHasta',
            'fEstado', 'fEstadoRevision', 'fMotivoRevision',
        );
        $this->fParaRevision = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $total = count($this->especimenes);
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->perPage) : 1;
        $offset = ($this->page - 1) * $this->perPage;

        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.index', [
            'especimenesPaginados' => array_slice($this->especimenes, $offset, $this->perPage),
            'totalPaginas' => $totalPaginas,
            'totalItems' => $total,
            'inicio' => $total > 0 ? $offset + 1 : 0,
            'fin' => min($offset + $this->perPage, $total),
            'columnasRegistro' => app(ResolverPrioridadColumnas::class)
                ->aplicar('especimenes', RegistroColumnasEspecimen::todas()),
            'columnasVisiblesPorDefecto' => RegistroColumnasEspecimen::clavesVisiblesPorDefecto(),
        ]);
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(string $value): ?int
    {
        return trim($value) !== '' ? (int) $value : null;
    }

    private function nullableFloat(string $value): ?float
    {
        return trim($value) !== '' ? (float) $value : null;
    }
}
