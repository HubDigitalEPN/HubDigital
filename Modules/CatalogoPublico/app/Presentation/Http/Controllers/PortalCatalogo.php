<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
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
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonInput;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes\ExportarRegistrosEspecimenesInput;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
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

    #[Url(as: 'vista')]
    public string $vista = 'tarjetas';

    #[Url(as: 'pagina')]
    public int $pagina = 1;

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

    #[Url(as: 'fh')]
    public string $filtroHabitat = '';

    #[Url(as: 'fsti')]
    public string $filtroTipo = '';

    #[Url(as: 'fca')]
    public string $filtroCasta = '';

    #[Url(as: 'fes')]
    public string $filtroEstadio = '';

    #[Url(as: 'fprov')]
    public string $filtroProvincia = '';

    #[Url(as: 'fph')]
    public string $filtroFiloId = '';

    #[Url(as: 'fmes')]
    public string $filtroMes = '';

    #[Url(as: 'fid')]
    public string $filtroIdentificacion = '';

    #[Url(as: 'fgeo')]
    public string $filtroSoloUbicacion = '';

    #[Url(as: 'fap')]
    public string $filtroDatosCompletos = '';

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
        $this->pagina = 1;
    }

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['tarjetas', 'registros', 'mapa'], true)) {
            $this->vista = $vista;
            $this->pagina = 1;
        }
    }

    public function actualizarFiltros(): void
    {
        $this->validate([
            'filtroFechaDesde' => ['nullable', 'date_format:Y-m-d'],
            'filtroFechaHasta' => array_filter(['nullable', 'date_format:Y-m-d', $this->filtroFechaDesde !== '' ? 'after_or_equal:filtroFechaDesde' : null]),
            'filtroLatMin' => ['nullable', 'required_with:filtroLatMax', 'numeric', 'between:-90,90'],
            'filtroLatMax' => ['nullable', 'required_with:filtroLatMin', 'numeric', 'between:-90,90', 'gte:filtroLatMin'],
            'filtroLonMin' => ['nullable', 'required_with:filtroLonMax', 'numeric', 'between:-180,180'],
            'filtroLonMax' => ['nullable', 'required_with:filtroLonMin', 'numeric', 'between:-180,180', 'gte:filtroLonMin'],
            'filtroElevDesde' => ['nullable', 'numeric'],
            'filtroElevHasta' => array_filter(['nullable', 'numeric', $this->filtroElevDesde !== '' ? 'gte:filtroElevDesde' : null]),
        ], [
            '*.required_with' => 'Completa ambos límites del rango espacial.',
            '*.between' => 'La coordenada está fuera del rango permitido.',
            '*.gte' => 'El límite superior debe ser mayor o igual al inferior.',
            '*.after_or_equal' => 'La fecha final debe ser posterior o igual a la inicial.',
            '*.date_format' => 'Usa una fecha válida con año, mes y día.',
        ]);
        $this->pagina = 1;
    }

    public function seleccionarProvincia(string $provincia): void
    {
        if (in_array($provincia, $this->provinciasDisponibles, true)) {
            $this->filtroProvincia = $provincia;
            $this->pagina = 1;
        }
    }

    public function seleccionarDecada(int $decada): void
    {
        if ($decada >= 0 && $decada <= 2090 && $decada % 10 === 0) {
            $this->filtroFechaDesde = sprintf('%04d-01-01', $decada);
            $this->filtroFechaHasta = sprintf('%04d-12-31', $decada + 9);
            $this->pagina = 1;
        }
    }

    public function seleccionarFilo(string $nombre): void
    {
        foreach ($this->filosDisponibles as $filo) {
            if ($filo['nombre_cientifico'] === $nombre) {
                $this->filtroFiloId = $this->filtroFiloId === $filo['id'] ? '' : $filo['id'];
                $this->pagina = 1;
                return;
            }
        }
    }

    public function seleccionarArea(float $latMin, float $latMax, float $lonMin, float $lonMax): void
    {
        if ($latMin < -90 || $latMax > 90 || $lonMin < -180 || $lonMax > 180 || $latMin >= $latMax || $lonMin >= $lonMax) {
            return;
        }
        $this->filtroLatMin = (string) $latMin;
        $this->filtroLatMax = (string) $latMax;
        $this->filtroLonMin = (string) $lonMin;
        $this->filtroLonMax = (string) $lonMax;
        $this->vista = 'registros';
        $this->pagina = 1;
    }

    public function filtrarCompletos(): void
    {
        $this->filtroDatosCompletos = '1';
        $this->pagina = 1;
        $this->vista = 'registros';
    }

    public function verGeorreferenciados(): void
    {
        $this->filtroSoloUbicacion = '1';
        $this->pagina = 1;
        $this->vista = 'registros';
    }

    public function explorarEspecie(string $nombre): void
    {
        if (mb_strlen($nombre) > 120 || $nombre === '') {
            return;
        }
        $this->filtroTaxon = $nombre;
        $this->nivel = '';
        $this->taxon = '';
        $this->pagina = 1;
        $this->vista = 'registros';
    }

    #[Computed]
    public function provinciasDisponibles(): array
    {
        return DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('d.state_province_visible', true)
            ->whereNotNull('e.state_province')->where('e.state_province', '<>', '')
            ->distinct()->orderBy('e.state_province')->pluck('e.state_province')->all();
    }

    #[Computed]
    public function filosDisponibles(): array
    {
        return DB::table('taxonomia.taxones')->where('rango', 'phylum')
            ->orderBy('nombre_cientifico')->get(['id', 'nombre_cientifico'])
            ->map(static fn (object $fila): array => (array) $fila)->all();
    }

    private function filtrosAnalisis(FiltrosBusqueda $filtros): array
    {
        return array_filter([
            'nivel' => $this->nivel,
            'taxon_navegado' => $this->taxon,
            'codigo' => $this->filtroCatalogo,
            'preparaciones' => $this->filtroPreparaciones,
            'taxon' => $this->filtroTaxon,
            'provincia' => $this->filtroProvincia,
            'geografias' => $this->filtroGeografias,
            'filo' => $this->filtroFiloId,
            'desde_fecha' => $filtros->fechaDesde?->format('Y-m-d'),
            'hasta_fecha' => $filtros->fechaHasta?->format('Y-m-d'),
            'mes' => $this->filtroMes,
            'identificacion' => $this->filtroIdentificacion,
            'ubicacion' => $this->filtroSoloUbicacion,
            'aptitud' => $this->filtroDatosCompletos === '1' ? 'completos' : '',
            'colector' => $this->filtroColector,
            'metodos' => $this->filtroMetodos,
            'lat_min' => $filtros->latMin,
            'lat_max' => $filtros->latMax,
            'lon_min' => $filtros->lonMin,
            'lon_max' => $filtros->lonMax,
            'elev_desde' => $filtros->elevDesde,
            'elev_hasta' => $filtros->elevHasta,
            'biomas' => $this->filtroBiomas,
            'habitat' => $this->filtroHabitat,
            'tipo' => $this->filtroTipo,
            'casta' => $this->filtroCasta,
            'estadio' => $this->filtroEstadio,
        ], static fn ($valor) => $valor !== null && $valor !== '' && $valor !== []);
    }

    public function cambiarPagina(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
    }

    public function explorarNivel(string $nivel): void
    {
        $this->explorar = $nivel;
        $this->nivel = '';
        $this->taxon = '';
        $this->pagina = 1;
    }

    public function volverAlArbol(): void
    {
        $this->explorar = '';
    }

    // ─── Acciones de filtrado ─────────────────────────────────────────────────

    public function aplicarFiltros(array $datos): void
    {
        $this->pagina = 1;
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
        $this->filtroHabitat = (string) ($datos['filtroHabitat'] ?? '');
        $this->filtroTipo = (string) ($datos['filtroTipo'] ?? '');
        $this->filtroCasta = (string) ($datos['filtroCasta'] ?? '');
        $this->filtroEstadio = (string) ($datos['filtroEstadio'] ?? '');
    }

    public function limpiarFiltros(): void
    {
        $this->resetValidation();
        $this->pagina = 1;
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
        $this->filtroHabitat = '';
        $this->filtroTipo = '';
        $this->filtroCasta = '';
        $this->filtroEstadio = '';
        $this->filtroProvincia = '';
        $this->filtroFiloId = '';
        $this->filtroMes = '';
        $this->filtroIdentificacion = '';
        $this->filtroSoloUbicacion = '';
        $this->filtroDatosCompletos = '';
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

    public function descargarResultados(EloquentProveedorEspecimenesParaArbol $repositorio): StreamedResponse
    {
        $filtros = $this->filtrosActuales();

        return response()->streamDownload(static function () use ($repositorio, $filtros): void {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['N.º catálogo', 'Taxón', 'Fecha', 'Localidad del Excel', 'Localidad INEC', 'Código INEC', 'Provincia', 'Latitud', 'Longitud', 'Precisión', 'Tipo'], ';', '"', '');
            $celda = static function (mixed $valor): string {
                $texto = (string) ($valor ?? '');

                return preg_match('/^[=+\-@\t\r]/u', $texto) ? "'".$texto : $texto;
            };
            foreach ($repositorio->cursorParaCsv($filtros) as $fila) {
                $localidad = (bool) $fila->locality_name_visible;
                $coordenadas = (bool) $fila->decimal_latitude_visible && (bool) $fila->decimal_longitude_visible;
                fputcsv($salida, array_map($celda, [
                    $fila->occurrence_id_visible ? ($fila->occurrence_id ?: $fila->codigo_catalogo) : null,
                    $fila->scientific_name_visible ? $fila->nombre_cientifico : null,
                    $fila->event_date_visible ? $fila->fecha_colecta : null,
                    $localidad ? $fila->localidad_verbatim : null,
                    $localidad ? $fila->localidad_inec : null,
                    $localidad ? $fila->codigo_inec : null,
                    $fila->state_province_visible ? $fila->state_province : null,
                    $coordenadas ? $fila->decimal_latitude : null,
                    $coordenadas ? $fila->decimal_longitude : null,
                    $coordenadas ? $fila->lat_lon_max_error : null,
                    $fila->type_status_visible ? $fila->type_status : null,
                ]), ';', '"', '');
            }
            fclose($salida);
        }, 'registros-catalogo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function descargarAnalisis(string $tipo): StreamedResponse
    {
        if (! in_array($tipo, ['mapa', 'filos', 'riqueza', 'decadas', 'calidad', 'raras'], true)) {
            abort(404);
        }
        $datos = app(PortalEstadisticas::class)->datosParaVista($this->filtrosAnalisis($this->filtrosActuales()));
        [$nombre, $cabecera, $filas] = match ($tipo) {
            'mapa' => ['cuadriculas-coleccion.csv', ['Latitud', 'Longitud', 'Filo', 'Registros'], array_merge([], ...array_map(
                static fn (array $celda): array => array_map(
                    static fn (string $filo, int $cantidad): array => [$celda['lat'], $celda['lon'], $filo, $cantidad],
                    array_keys($celda['filos']), array_values($celda['filos']),
                ), $datos['mapa'],
            ))],
            'filos' => ['filos-coleccion.csv', ['Filo', 'Registros'], array_map(
                static fn (string $filo, int $cantidad): array => [$filo, $cantidad],
                array_keys($datos['filos']), array_values($datos['filos']),
            )],
            'riqueza' => ['riqueza-por-provincia.csv', ['Provincia', 'Especies', 'Registros'], array_map(
                static fn (array $fila): array => [$fila['provincia'], $fila['especies'], $fila['registros']], $datos['riqueza'],
            )],
            'decadas' => ['cobertura-por-decada.csv', ['Década', 'Especies', 'Registros'], array_map(
                static fn (array $fila): array => [$fila['decada'], $fila['especies'], $fila['registros']], $datos['decadas'],
            )],
            'calidad' => ['completitud-coleccion.csv', ['Registros', 'Identificados a especie', 'Con fecha', 'Con coordenadas', 'Con los tres campos'], [[
                $datos['resumen']['registros'], $datos['resumen']['identificados'], $datos['resumen']['fechados'],
                $datos['resumen']['georreferenciados'], $datos['resumen']['aptos'],
            ]]],
            'raras' => ['especies-pocos-registros.csv', ['Especie', 'Registros'], array_map(
                static fn (array $fila): array => [$fila['nombre'], $fila['total']], $datos['raras'],
            )],
        };

        return response()->streamDownload(static function () use ($cabecera, $filas): void {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $cabecera, ';', '"', '');
            foreach ($filas as $fila) {
                fputcsv($salida, array_map(static function (mixed $valor): string {
                    $texto = (string) $valor;
                    return preg_match('/^\s*[=+@]/u', $texto) || preg_match('/^\s*-(?!\d+(?:[.,]\d+)?\s*$)/u', $texto)
                        ? "'".$texto : $texto;
                }, $fila), ';', '"', '');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function descargarListaEspecies(): StreamedResponse
    {
        return app(PortalEstadisticas::class)->descargarListaConFiltros(
            $this->filtrosAnalisis($this->filtrosActuales()),
        );
    }

    private function filtrosActuales(): FiltrosBusqueda
    {
        return FiltrosBusqueda::desde([
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
            'filtroHabitat' => $this->filtroHabitat,
            'filtroTipo' => $this->filtroTipo,
            'filtroCasta' => $this->filtroCasta,
            'filtroEstadio' => $this->filtroEstadio,
            'filtroProvincia' => $this->filtroProvincia,
            'filtroFiloId' => $this->filtroFiloId,
            'filtroMes' => $this->filtroMes,
            'filtroIdentificacion' => $this->filtroIdentificacion,
            'filtroSoloUbicacion' => $this->filtroSoloUbicacion,
            'filtroDatosCompletos' => $this->filtroDatosCompletos,
        ]);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(
        ConstruirArbolTaxonomicoHandler $handler,
        ProveedorEspecimenesPort $proveedor,
        EspecimenDivulgableRepositoryInterface $repoDivulgable,
        ConsultarGaleriaTaxonHandler $galeriaHandler,
    ): View {
        $filtros = $this->filtrosActuales();
        $datosMapa = $this->vista === 'mapa'
            ? app(PortalEstadisticas::class)->datosParaVista($this->filtrosAnalisis($filtros))
            : null;

        if ($this->vista === 'mapa') {
            return view('catalogopublico::livewire.portal-catalogo', [
                'datosMapa' => $datosMapa,
                'claveFiltrosMapa' => sha1(json_encode([$filtros, $datosMapa['mapa'], $datosMapa['filos']])),
                'provinciasDisponibles' => $this->provinciasDisponibles,
                'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles,
                'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles,
                'hayFiltrosActivos' => ! $filtros->estaVacio(),
            ]);
        }

        if ($this->vista === 'registros') {
            $pagina = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica($filtros, $this->pagina, $this->nivel, $this->taxon);
            return view('catalogopublico::livewire.portal-catalogo', [
                'datosMapa' => null,
                'provinciasDisponibles' => $this->provinciasDisponibles,
                'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles,
                'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles,
                'hayFiltrosActivos' => ! $filtros->estaVacio(),
                'nivelActual' => $this->nivel, 'taxonActual' => $this->taxon,
                'registrosVista' => $this->cargarDetallesPorEspecimenIds($pagina['ids'], $proveedor, $repoDivulgable),
                'totalRegistrosVista' => $pagina['total'], 'paginaActual' => $pagina['pagina'], 'ultimaPagina' => $pagina['ultima'],
            ]);
        }

        if ($this->vista === 'tarjetas' && $this->nivel === '' && $this->explorar === '') {
            $resumenRaiz = app(EloquentProveedorEspecimenesParaArbol::class)->resumenRaiz($filtros);
            return view('catalogopublico::livewire.portal-catalogo', $resumenRaiz + [
                'provinciasDisponibles' => $this->provinciasDisponibles, 'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles, 'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles, 'hayFiltrosActivos' => ! $filtros->estaVacio(),
                'nivelActual' => '', 'taxonActual' => '', 'nivelExplorar' => '', 'ruta' => [],
                'nivelesNavegacion' => self::NIVEL_ETIQUETA, 'etiquetasDescendientes' => self::DESCENDANT_LABELS, 'etiquetas' => self::NIVEL_ETIQUETA,
            ]);
        }

        $output = ($handler)(new ConstruirArbolTaxonomicoInput($filtros->estaVacio() ? null : $filtros));

        $totalGlobal = count($output->especimenIds);
        $conteos = $this->calcularConteos($output);
        $ruta = $this->resolverRuta($output);
        $hijos = $this->resolverHijos($output);
        $especiesActuales = $this->nivel === 'genus' ? $this->resolverEspecies($output) : [];
        $hermanos = $this->nivel !== '' ? $this->resolverHermanos($output) : [];
        $especimenes = $this->nivel === 'species' && $this->vista === 'tarjetas'
            ? $this->cargarDetallesEspecimenes($output->especimenesPorEspecie[$this->taxon] ?? [], $proveedor, $repoDivulgable)
            : [];

        $idsParaVista = $this->nivel === ''
            ? $output->especimenIds
            : ($output->especimenesPorNodo[$this->nivel.':'.$this->taxon] ?? []);
        $totalRegistrosVista = count($idsParaVista);
        $ultimaPagina = max(1, (int) ceil($totalRegistrosVista / 50));
        $paginaActual = min(max(1, $this->pagina), $ultimaPagina);
        $registrosVista = $this->vista === 'registros' && $this->explorar === ''
            ? $this->cargarDetallesPorEspecimenIds(array_slice($idsParaVista, ($paginaActual - 1) * 50, 50), $proveedor, $repoDivulgable)
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
            'filtroHabitat' => $this->filtroHabitat,
            'filtroTipo' => $this->filtroTipo,
            'filtroCasta' => $this->filtroCasta,
            'filtroEstadio' => $this->filtroEstadio,
            'filtroProvincia' => $this->filtroProvincia,
            'filtroFiloId' => $this->filtroFiloId,
            'filtroMes' => $this->filtroMes,
            'filtroIdentificacion' => $this->filtroIdentificacion,
            'filtroSoloUbicacion' => $this->filtroSoloUbicacion,
            'filtroDatosCompletos' => $this->filtroDatosCompletos,
        ];

        $galeriaEspecie = $this->nivel === 'species' && $this->vista === 'tarjetas'
            ? $galeriaHandler->handle(new ConsultarGaleriaTaxonInput('species', $this->taxon))->imagenes
            : [];

        $imagenesPorEspecimen = $this->nivel === 'species' && $this->vista === 'tarjetas'
            ? $this->cargarImagenesPorEspecimen(array_values(array_filter(array_map(
                fn (object $e): ?string => $e->occurrence_id,
                $especimenes,
            ))))
            : [];

        return view('catalogopublico::livewire.portal-catalogo', [
            'datosMapa' => $datosMapa,
            'provinciasDisponibles' => $this->provinciasDisponibles,
            'filosDisponibles' => $this->filosDisponibles,
            'ruta' => $ruta,
            'portadas' => $this->cargarPortadas(),
            'galeriaEspecie' => $galeriaEspecie,
            'imagenesPorEspecimen' => $imagenesPorEspecimen,
            'hijos' => $hijos,
            'especiesActuales' => $especiesActuales,
            'hermanos' => $hermanos,
            'especimenes' => $especimenes,
            'registrosVista' => $registrosVista,
            'totalRegistrosVista' => $totalRegistrosVista,
            'paginaActual' => $paginaActual,
            'ultimaPagina' => $ultimaPagina,
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

    /**
     * Portadas (imagen por defecto) de género y especie, indexadas por "nivel:valor".
     *
     * @return array<string, string>
     */
    private function cargarPortadas(): array
    {
        return DB::table('divulgacion.imagenes_por_defecto as d')
            ->join('divulgacion.imagenes_taxonomicas as i', 'i.id', '=', 'd.imagen_id')
            ->select('d.nivel', 'd.valor_taxon', 'i.ruta', 'i.disco')
            ->get()
            ->mapWithKeys(fn ($r): array => [
                $r->nivel.':'.$r->valor_taxon => StorageImagenesAdapter::urlPublica($r->ruta),
            ])
            ->all();
    }

    /**
     * Imágenes agrupadas por occurrence_id para los especímenes visibles de la especie.
     *
     * @param  list<string>  $occurrenceIDs
     * @return array<string, list<array{url: string, nombre: string}>>
     */
    private function cargarImagenesPorEspecimen(array $occurrenceIDs): array
    {
        if ($occurrenceIDs === []) {
            return [];
        }

        return DB::table('divulgacion.imagenes_taxonomicas')
            ->whereIn('occurrence_id', $occurrenceIDs)
            ->orderBy('created_at')
            ->get(['occurrence_id', 'ruta', 'disco', 'nombre_original'])
            ->groupBy('occurrence_id')
            ->map(fn ($grupo): array => $grupo->map(fn ($r): array => [
                'url' => StorageImagenesAdapter::urlPublica($r->ruta),
                'nombre' => $r->nombre_original,
            ])->values()->all())
            ->all();
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
     * Carga los detalles de los especímenes aplicando la configuración de visibilidad
     * de divulgación: cada campo se anula cuando su flag está desactivado, de modo que
     * la tarjeta del portal solo muestra lo que el curador habilitó.
     *
     * @param  list<string>  $occurrenceIDs
     * @return list<object>
     */
    private function cargarDetallesEspecimenes(
        array $occurrenceIDs,
        ProveedorEspecimenesPort $proveedor,
        EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ): array {
        if ($occurrenceIDs === []) {
            return [];
        }

        return $this->aplicarVisibilidad($proveedor->buscarPorOccurrenceIds($occurrenceIDs), $repoDivulgable);
    }

    /** @param list<string> $especimenIds @return list<object> */
    private function cargarDetallesPorEspecimenIds(
        array $especimenIds,
        ProveedorEspecimenesPort $proveedor,
        EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ): array {
        return $this->aplicarVisibilidad($proveedor->buscarPorEspecimenIds($especimenIds), $repoDivulgable);
    }

    /** @param list<DatosEspecimenProveedor> $datos @return list<object> */
    private function aplicarVisibilidad(array $datos, EspecimenDivulgableRepositoryInterface $repoDivulgable): array
    {
        if ($datos === []) {
            return [];
        }

        // Config de visibilidad indexada por especimenId (FK estable compartida con el DTO).
        $configPorEspecimen = [];
        foreach ($repoDivulgable->buscarPublicadosPorOccurrenceIDs(array_map(fn (DatosEspecimenProveedor $dto): string => $dto->occurrenceId, $datos)) as $divulgable) {
            $configPorEspecimen[$divulgable->especimenId()] = $divulgable;
        }

        $datosPublicados = array_values(array_filter($datos, fn (DatosEspecimenProveedor $dto): bool => isset($configPorEspecimen[$dto->especimenId])));

        return array_map(
            function (DatosEspecimenProveedor $dto) use ($configPorEspecimen): object {
                $cfg = $configPorEspecimen[$dto->especimenId];
                $ver = fn (callable $flag): bool => $flag($cfg);
                $g = fn (bool $visible, mixed $valor): mixed => $visible ? $valor : null;

                return (object) [
                    'occurrence_id' => $g($ver(fn (EspecimenDivulgable $d) => $d->occurrenceIDVisible()), $dto->occurrenceId),
                    'scientific_name' => $g($ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()), $dto->scientificName),
                    'individual_count' => $g($ver(fn (EspecimenDivulgable $d) => $d->individualCountVisible()), $dto->individualCount),
                    'type_status' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()), $dto->typeStatus),
                    'type_notes' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeNotesVisible()), $dto->typeNotes),
                    'specimen_notes' => $g($ver(fn (EspecimenDivulgable $d) => $d->specimenNotesVisible()), $dto->specimenNotes),
                    'sampling_protocol' => $g($ver(fn (EspecimenDivulgable $d) => $d->samplingProtocolVisible()), $dto->samplingProtocol),
                    'recorded_by' => $g($ver(fn (EspecimenDivulgable $d) => $d->recordedByVisible()), $dto->recordedBy),
                    'occurrence_status' => $g($ver(fn (EspecimenDivulgable $d) => $d->occurrenceStatusVisible()), $dto->occurrenceStatus),
                    'country' => $g($ver(fn (EspecimenDivulgable $d) => $d->countryVisible()), $dto->country),
                    'state_province' => $g($ver(fn (EspecimenDivulgable $d) => $d->stateProvinceVisible()), $dto->stateProvince),
                    'locality_name' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityName),
                    'locality_excel' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityExcel),
                    'locality_inec' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityInec),
                    'locality_inec_reference' => $g($ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()), $dto->localityInecReference),
                    'locality_visible' => $ver(fn (EspecimenDivulgable $d) => $d->localityNameVisible()),
                    'coordinate_reference' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLatitudeVisible()) && $ver(fn (EspecimenDivulgable $d) => $d->decimalLongitudeVisible()), $dto->coordinateReference),
                    'decimal_latitude' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLatitudeVisible()), $dto->decimalLatitude),
                    'decimal_longitude' => $g($ver(fn (EspecimenDivulgable $d) => $d->decimalLongitudeVisible()), $dto->decimalLongitude),
                    'elevation_min_m' => $g($ver(fn (EspecimenDivulgable $d) => $d->elevationVisible()), $dto->elevationMinM),
                    'elevation_max_m' => $g($ver(fn (EspecimenDivulgable $d) => $d->elevationVisible()), $dto->elevationMaxM),
                    'event_date' => $g($ver(fn (EspecimenDivulgable $d) => $d->eventDateVisible()), $dto->eventDate),
                    'caste' => $g($ver(fn (EspecimenDivulgable $d) => $d->casteVisible()), $dto->caste),
                    'life_stage' => $g($ver(fn (EspecimenDivulgable $d) => $d->lifeStageVisible()), $dto->lifeStage),
                ];
            },
            $datosPublicados
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
        if ($output->especimenesPorNodo !== []) {
            return array_map('count', $output->especimenesPorNodo);
        }
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
