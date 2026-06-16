<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoHandler;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoInput;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoOutput;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.portal', params: ['title' => 'Catálogo taxonómico · Departamento de Biología — EPN'])]
final class PortalCatalogo extends Component
{
    private const array NIVEL_SIGUIENTE = [
        '' => 'phylum',
        'phylum' => 'class',
        'class' => 'order',
        'order' => 'family',
        'family' => 'genus',
        'genus' => 'species',
    ];

    private const array NIVEL_PADRE = [
        'phylum' => '',
        'class' => 'phylum',
        'order' => 'class',
        'family' => 'order',
        'genus' => 'family',
        'species' => 'genus',
    ];

    private const array NIVEL_ETIQUETA = [
        '' => 'Catálogo',
        'phylum' => 'Filo',
        'class' => 'Clase',
        'order' => 'Orden',
        'family' => 'Familia',
        'genus' => 'Género',
        'species' => 'Especie',
    ];

    private const array DESCENDANT_LABELS = [
        'class' => 'clases',
        'order' => 'órdenes',
        'family' => 'familias',
        'genus' => 'géneros',
        'species' => 'especies',
    ];

    private const array NIVEL_PLURAL = [
        'phylum' => 'filos',
        'class' => 'clases',
        'order' => 'órdenes',
        'family' => 'familias',
        'genus' => 'géneros',
        'species' => 'especies',
    ];

    // ─── Navegación ──────────────────────────────────────────────────────────

    #[Url(as: 'nivel')]
    public string $nivel = '';

    #[Url(as: 'taxon')]
    public string $taxon = '';

    #[Url(as: 'explorar')]
    public string $explorar = '';

    // ─── Filtros (URL-persistidos) ────────────────────────────────────────────

    #[Url(as: 'fc')]
    public string $filtroCatalogo = '';

    #[Url(as: 'fp')]
    public array $filtroPreparaciones = [];

    #[Url(as: 'ft')]
    public string $filtroTaxon = '';

    #[Url(as: 'fg')]
    public array $filtroGeografias = [];

    #[Url(as: 'fco')]
    public string $filtroColector = '';

    #[Url(as: 'ffd')]
    public string $filtroFechaDesde = '';

    #[Url(as: 'ffh')]
    public string $filtroFechaHasta = '';

    #[Url(as: 'fm')]
    public array $filtroMetodos = [];

    #[Url(as: 'flat')]
    public string $filtroLatMin = '';

    #[Url(as: 'flax')]
    public string $filtroLatMax = '';

    #[Url(as: 'flon')]
    public string $filtroLonMin = '';

    #[Url(as: 'flox')]
    public string $filtroLonMax = '';

    #[Url(as: 'fed')]
    public string $filtroElevDesde = '';

    #[Url(as: 'feh')]
    public string $filtroElevHasta = '';

    #[Url(as: 'fb')]
    public array $filtroBiomas = [];

    // ─── Servicio de opciones (no serializado entre requests) ─────────────────

    private ProveedorOpcionesFiltroPort $opcionesFiltro;

    private ExportarRegistrosEspecimenesHandler $exportarHandler;

    public function boot(
        ProveedorOpcionesFiltroPort $opcionesFiltro,
        ExportarRegistrosEspecimenesHandler $exportarHandler,
    ): void {
        $this->opcionesFiltro = $opcionesFiltro;
        $this->exportarHandler = $exportarHandler;
    }

    // ─── Opciones dinámicas ───────────────────────────────────────────────────

    #[Computed]
    public function preparacionesDisponibles(): array
    {
        return $this->opcionesFiltro->obtenerPreparaciones();
    }

    #[Computed]
    public function biomasDisponibles(): array
    {
        return $this->opcionesFiltro->obtenerBiomas();
    }

    #[Computed]
    public function metodosRecoleccionDisponibles(): array
    {
        return $this->opcionesFiltro->obtenerMetodosRecoleccion();
    }

    #[Computed]
    public function colectoresDisponibles(): array
    {
        return $this->opcionesFiltro->obtenerColectores();
    }

    // ─── Acciones de navegación ───────────────────────────────────────────────

    public function navegar(string $nivel, string $taxon): void
    {
        $this->nivel = $nivel;
        $this->taxon = $taxon;
        $this->explorar = '';
    }

    public function explorarNivel(string $nivel): void
    {
        $this->explorar = $nivel;
        $this->nivel = '';
        $this->taxon = '';
    }

    public function volverAlArbol(): void
    {
        $this->explorar = '';
    }

    // ─── Acciones de filtrado ─────────────────────────────────────────────────

    public function aplicarFiltros(array $datos): void
    {
        $this->filtroCatalogo = (string) ($datos['filtroCatalogo'] ?? '');
        $this->filtroPreparaciones = (array) ($datos['filtroPreparaciones'] ?? []);
        $this->filtroTaxon = (string) ($datos['filtroTaxon'] ?? '');
        $this->filtroGeografias = (array) ($datos['filtroGeografias'] ?? []);
        $this->filtroColector = (string) ($datos['filtroColector'] ?? '');
        $this->filtroFechaDesde = (string) ($datos['filtroFechaDesde'] ?? '');
        $this->filtroFechaHasta = (string) ($datos['filtroFechaHasta'] ?? '');
        $this->filtroMetodos = (array) ($datos['filtroMetodos'] ?? []);
        $this->filtroLatMin = (string) ($datos['filtroLatMin'] ?? '');
        $this->filtroLatMax = (string) ($datos['filtroLatMax'] ?? '');
        $this->filtroLonMin = (string) ($datos['filtroLonMin'] ?? '');
        $this->filtroLonMax = (string) ($datos['filtroLonMax'] ?? '');
        $this->filtroElevDesde = (string) ($datos['filtroElevDesde'] ?? '');
        $this->filtroElevHasta = (string) ($datos['filtroElevHasta'] ?? '');
        $this->filtroBiomas = (array) ($datos['filtroBiomas'] ?? []);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCatalogo = '';
        $this->filtroPreparaciones = [];
        $this->filtroTaxon = '';
        $this->filtroGeografias = [];
        $this->filtroColector = '';
        $this->filtroFechaDesde = '';
        $this->filtroFechaHasta = '';
        $this->filtroMetodos = [];
        $this->filtroLatMin = '';
        $this->filtroLatMax = '';
        $this->filtroLonMin = '';
        $this->filtroLonMax = '';
        $this->filtroElevDesde = '';
        $this->filtroElevHasta = '';
        $this->filtroBiomas = [];
    }

    // ─── Exportación ─────────────────────────────────────────────────────────

    public function descargarDatos(): StreamedResponse
    {
        $output = $this->exportarHandler->handle(
            new ExportarRegistrosEspecimenesInput($this->taxon)
        );

        return response()->streamDownload(
            fn () => print ($output->contenidoXlsx),
            $output->nombreArchivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(ConstruirArbolTaxonomicoHandler $handler, ProveedorEspecimenesPort $proveedor): View
    {
        $filtros = FiltrosBusqueda::desde([
            'filtroCatalogo' => $this->filtroCatalogo,
            'filtroPreparaciones' => $this->filtroPreparaciones,
            'filtroTaxon' => $this->filtroTaxon,
            'filtroGeografias' => $this->filtroGeografias,
            'filtroColector' => $this->filtroColector,
            'filtroFechaDesde' => $this->filtroFechaDesde,
            'filtroFechaHasta' => $this->filtroFechaHasta,
            'filtroMetodos' => $this->filtroMetodos,
            'filtroLatMin' => $this->filtroLatMin,
            'filtroLatMax' => $this->filtroLatMax,
            'filtroLonMin' => $this->filtroLonMin,
            'filtroLonMax' => $this->filtroLonMax,
            'filtroElevDesde' => $this->filtroElevDesde,
            'filtroElevHasta' => $this->filtroElevHasta,
            'filtroBiomas' => $this->filtroBiomas,
        ]);

        $output = ($handler)(new ConstruirArbolTaxonomicoInput($filtros->estaVacio() ? null : $filtros));

        $totalGlobal = (int) array_sum(array_map('count', $output->especimenesPorEspecie));
        $conteos = $this->calcularConteos($output);
        $ruta = $this->resolverRuta($output);
        $hijos = $this->resolverHijos($output);
        $especiesActuales = $this->nivel === 'genus' ? $this->resolverEspecies($output) : [];
        $hermanos = $this->nivel !== '' ? $this->resolverHermanos($output) : [];
        $especimenes = $this->nivel === 'species'
            ? $this->cargarDetallesEspecimenes($output->especimenesPorEspecie[$this->taxon] ?? [], $proveedor)
            : [];

        $descendientes = $this->calcularDescendientes($output);

        $filtrosActivos = [
            'filtroCatalogo' => $this->filtroCatalogo,
            'filtroPreparaciones' => $this->filtroPreparaciones,
            'filtroTaxon' => $this->filtroTaxon,
            'filtroGeografias' => $this->filtroGeografias,
            'filtroColector' => $this->filtroColector,
            'filtroFechaDesde' => $this->filtroFechaDesde,
            'filtroFechaHasta' => $this->filtroFechaHasta,
            'filtroMetodos' => $this->filtroMetodos,
            'filtroLatMin' => $this->filtroLatMin,
            'filtroLatMax' => $this->filtroLatMax,
            'filtroLonMin' => $this->filtroLonMin,
            'filtroLonMax' => $this->filtroLonMax,
            'filtroElevDesde' => $this->filtroElevDesde,
            'filtroElevHasta' => $this->filtroElevHasta,
            'filtroBiomas' => $this->filtroBiomas,
        ];

        return view('catalogopublico::livewire.portal-catalogo', [
            'ruta' => $ruta,
            'hijos' => $hijos,
            'especiesActuales' => $especiesActuales,
            'hermanos' => $hermanos,
            'especimenes' => $especimenes,
            'conteos' => $conteos,
            'descendientes' => $descendientes,
            'taxonesExplorados' => $this->explorar !== ''
                ? $this->resolverTaxonesParaExplorar($output, $this->explorar)
                : [],
            'totalGlobal' => $totalGlobal,
            'nivelActual' => $this->nivel,
            'taxonActual' => $this->taxon,
            'nivelExplorar' => $this->explorar,
            'nivelHijo' => self::NIVEL_SIGUIENTE[$this->nivel] ?? '',
            'etiquetas' => self::NIVEL_ETIQUETA,
            'etiquetasDescendientes' => self::DESCENDANT_LABELS,
            'nivelesNavegacion' => self::NIVEL_ETIQUETA,
            'nivelesPluralNavegacion' => self::NIVEL_PLURAL,
            'filtrosActivos' => $filtrosActivos,
            'hayFiltrosActivos' => ! $filtros->estaVacio(),
            'preparacionesDisponibles' => $this->preparacionesDisponibles,
            'biomasDisponibles' => $this->biomasDisponibles,
            'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
            'colectoresDisponibles' => $this->colectoresDisponibles,
        ]);
    }

    /** @return list<array{nivel: string, taxon: string, etiqueta: string}> */
    private function resolverRuta(ConstruirArbolTaxonomicoOutput $output): array
    {
        if ($this->nivel === '' || $this->taxon === '') {
            return [];
        }

        $ruta = [];
        $nivelCursor = $this->nivel;
        $taxonCursor = $this->taxon;

        while ($nivelCursor !== '' && $taxonCursor !== '') {
            $ruta[] = [
                'nivel' => $nivelCursor,
                'taxon' => $taxonCursor,
                'etiqueta' => self::NIVEL_ETIQUETA[$nivelCursor] ?? $nivelCursor,
            ];

            if ($nivelCursor === 'species') {
                $nodoEspecie = collect($output->especies)
                    ->first(fn ($e) => $e['especie'] === $taxonCursor);

                if ($nodoEspecie) {
                    $nivelCursor = 'genus';
                    $taxonCursor = $nodoEspecie['padre'];
                } else {
                    break;
                }

                continue;
            }

            $nodo = collect($output->nodosJerarquicos)
                ->first(fn ($n) => $n['nivel'] === $nivelCursor && $n['taxon'] === $taxonCursor);

            if (! $nodo || $nodo['padre'] === 'root') {
                break;
            }

            $nivelCursor = self::NIVEL_PADRE[$nivelCursor] ?? '';
            $taxonCursor = $nodo['padre'];
        }

        return array_reverse($ruta);
    }

    /** @return list<array{nivel: string, taxon: string, padre: string}> */
    private function resolverHijos(ConstruirArbolTaxonomicoOutput $output): array
    {
        $nivelHijo = self::NIVEL_SIGUIENTE[$this->nivel] ?? null;

        if ($nivelHijo === null || $nivelHijo === 'species') {
            return [];
        }

        $padre = $this->taxon === '' ? 'root' : $this->taxon;

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $nivelHijo && $n['padre'] === $padre)
            ->values()
            ->all();
    }

    /** @return list<array{especie: string, genus: string, specificEpithet: string, padre: string}> */
    private function resolverEspecies(ConstruirArbolTaxonomicoOutput $output): array
    {
        return collect($output->especies)
            ->filter(fn ($e) => $e['padre'] === $this->taxon)
            ->values()
            ->all();
    }

    /** @return list<array{nivel: string, taxon: string, esEspecie: bool}> */
    private function resolverHermanos(ConstruirArbolTaxonomicoOutput $output): array
    {
        if ($this->nivel === 'species') {
            $nodoEspecie = collect($output->especies)
                ->first(fn ($e) => $e['especie'] === $this->taxon);

            if (! $nodoEspecie) {
                return [];
            }

            return collect($output->especies)
                ->filter(fn ($e) => $e['padre'] === $nodoEspecie['padre'] && $e['especie'] !== $this->taxon)
                ->map(fn ($e) => ['nivel' => 'species', 'taxon' => $e['especie'], 'esEspecie' => true])
                ->values()
                ->all();
        }

        $nodo = collect($output->nodosJerarquicos)
            ->first(fn ($n) => $n['nivel'] === $this->nivel && $n['taxon'] === $this->taxon);

        if (! $nodo) {
            return [];
        }

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $this->nivel && $n['padre'] === $nodo['padre'] && $n['taxon'] !== $this->taxon)
            ->map(fn ($n) => ['nivel' => $n['nivel'], 'taxon' => $n['taxon'], 'esEspecie' => false])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $occurrenceIDs
     * @return list<object>
     */
    private function cargarDetallesEspecimenes(array $occurrenceIDs, ProveedorEspecimenesPort $proveedor): array
    {
        if ($occurrenceIDs === []) {
            return [];
        }

        return array_map(
            fn (DatosEspecimenProveedor $dto): object => (object) [
                'occurrence_id' => $dto->occurrenceId,
                'scientific_name' => $dto->scientificName,
                'individual_count' => $dto->individualCount,
                'type_status' => $dto->typeStatus,
                'type_notes' => $dto->typeNotes,
                'specimen_notes' => $dto->specimenNotes,
                'sampling_protocol' => $dto->samplingProtocol,
                'recorded_by' => $dto->recordedBy,
                'occurrence_status' => $dto->occurrenceStatus,
                'country' => $dto->country,
                'locality_name' => $dto->localityName,
                'decimal_latitude' => $dto->decimalLatitude,
                'decimal_longitude' => $dto->decimalLongitude,
            ],
            $proveedor->buscarPorOccurrenceIds($occurrenceIDs)
        );
    }

    /** @return list<array{nivel: string, taxon: string, padre: string}> */
    private function resolverTaxonesParaExplorar(ConstruirArbolTaxonomicoOutput $output, string $nivel): array
    {
        if ($nivel === 'species') {
            return collect($output->especies)
                ->map(fn ($e) => ['nivel' => 'species', 'taxon' => $e['especie'], 'padre' => $e['padre']])
                ->sortBy('taxon')
                ->values()
                ->all();
        }

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $nivel)
            ->sortBy('taxon')
            ->values()
            ->all();
    }

    /** @return array<string, array<string, int>> */
    private function calcularDescendientes(ConstruirArbolTaxonomicoOutput $output): array
    {
        $niveles = ['phylum', 'class', 'order', 'family', 'genus', 'species'];
        $ordenNivel = array_flip($niveles);

        $porNivelYPadre = [];
        foreach ($output->nodosJerarquicos as $nodo) {
            $porNivelYPadre[$nodo['nivel']][$nodo['padre']][] = $nodo['taxon'];
        }

        $especiesPorGenus = [];
        foreach ($output->especies as $especie) {
            $especiesPorGenus[$especie['padre']][] = $especie['especie'];
        }

        $resultado = [];

        foreach ($output->nodosJerarquicos as $nodo) {
            $clave = $nodo['nivel'].':'.$nodo['taxon'];
            $nivelIdx = $ordenNivel[$nodo['nivel']];
            $descendantesPorNivel = [];
            $taxonsActuales = [$nodo['taxon']];

            for ($i = $nivelIdx + 1; $i < count($niveles); $i++) {
                $nivelDesc = $niveles[$i];
                $siguientes = [];

                foreach ($taxonsActuales as $padre) {
                    $hijos = $nivelDesc === 'species'
                        ? ($especiesPorGenus[$padre] ?? [])
                        : ($porNivelYPadre[$nivelDesc][$padre] ?? []);
                    $siguientes = array_merge($siguientes, $hijos);
                }

                if ($siguientes !== []) {
                    $descendantesPorNivel[$nivelDesc] = count($siguientes);
                }

                $taxonsActuales = $siguientes;
            }

            $resultado[$clave] = $descendantesPorNivel;
        }

        return $resultado;
    }

    /** @return array<string, int> */
    private function calcularConteos(ConstruirArbolTaxonomicoOutput $output): array
    {
        $conteos = [];

        foreach ($output->especimenesPorEspecie as $especie => $ids) {
            $conteos['species:'.$especie] = count($ids);
        }

        foreach ($output->especies as $nodo) {
            $genero = $nodo['padre'];
            $conteos['genus:'.$genero] = ($conteos['genus:'.$genero] ?? 0)
                + ($conteos['species:'.$nodo['especie']] ?? 0);
        }

        $ascenso = ['genus' => 'family', 'family' => 'order', 'order' => 'class', 'class' => 'phylum'];

        foreach ($ascenso as $nivelHijo => $nivelPadre) {
            foreach ($output->nodosJerarquicos as $nodo) {
                if ($nodo['nivel'] !== $nivelHijo) {
                    continue;
                }

                $conteos[$nivelPadre.':'.$nodo['padre']] = ($conteos[$nivelPadre.':'.$nodo['padre']] ?? 0)
                    + ($conteos[$nivelHijo.':'.$nodo['taxon']] ?? 0);
            }
        }

        return $conteos;
    }
}
