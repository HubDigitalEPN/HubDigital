<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Validation\ValidationException;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorOpcionesFiltroPort;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoHandler;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoInput;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoOutput;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EnlaceSeleccionCatalogo;
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
    use ExploraCeldaMapa;

    public array $borradorFiltros = [];

    private array $filtrosAntes = [];

    public function hydrate(): void
    {
        $this->filtrosAntes = $this->valoresFiltros();
    }

    public function mount(): void
    {
        $paginaSolicitada = $this->pagina;
        try {
            $this->actualizarFiltros();
        } catch (ValidationException $error) {
            // Una URL inválida no debe convertirse en una selección vacía engañosa.
            foreach (array_keys($error->errors()) as $propiedad) $this->{$propiedad} = '';
            foreach (['filtroLat', 'filtroLon'] as $prefijo) {
                $this->{$prefijo.'Min'} = '';
                $this->{$prefijo.'Max'} = '';
            }
            $this->setErrorBag($error->errors());
        }
        $this->pagina = max(1, $paginaSolicitada);
        $this->borradorFiltros = $this->valoresFiltros();
        $this->filtrosAntes = $this->valoresFiltros();
    }

    private function valoresFiltros(): array
    {
        return array_filter(get_object_vars($this), static fn (string $clave): bool => preg_match('/^filtro[A-Z]/', $clave) === 1, ARRAY_FILTER_USE_KEY);
    }

    public function aplicarBorrador(): void
    {
        $anteriores = $this->valoresFiltros();
        foreach ($anteriores as $propiedad => $valor) $this->{$propiedad} = $this->borradorFiltros[$propiedad] ?? $valor;
        try {
            $this->actualizarFiltros();
        } catch (ValidationException $error) {
            foreach ($anteriores as $propiedad => $valor) $this->{$propiedad} = $valor;
            throw $error;
        }
        $this->cerrarCelda();
    }

    public function dehydrate(): void
    {
        if (! $this->getErrorBag()->any() && $this->valoresFiltros() !== $this->filtrosAntes) $this->borradorFiltros = $this->valoresFiltros();
    }

    public function quitarTaxon(): void
    {
        $this->navegar('', '');
    }

    public function seleccionarMes(int $mes): void
    {
        if ($mes < 1 || $mes > 12) return;
        $this->filtroMes = (string) $mes;
        $this->pagina = 1;
    }

    public function seleccionarAltitud(int $desde, int $hasta): void
    {
        if ($desde < -500 || $hasta > 9000 || $desde > $hasta) return;
        $this->filtroElevDesde = (string) $desde;
        $this->filtroElevHasta = (string) $hasta;
        $this->pagina = 1;
    }

    public function seleccionarMetodo(string $metodo): void
    {
        if (! in_array($metodo, $this->metodosRecoleccionDisponibles, true)) return;
        $this->filtroMetodos = [$metodo];
        $this->pagina = 1;
    }

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

    #[Url(as: 'nivel', history: true)]
    public string $nivel = '';

    #[Url(as: 'taxon', history: true)]
    public string $taxon = '';

    #[Url(as: 'explorar', history: true)]
    public string $explorar = '';

    #[Url(as: 'vista', history: true)]
    public string $vista = 'tarjetas';

    #[Url(as: 'pagina', history: true)]
    public int $pagina = 1;

    public int $paginaHermanos = 1;

    // ─── Filtros (URL-persistidos) ────────────────────────────────────────────

    #[Url(as: 'fc', history: true)]
    public string $filtroCatalogo = '';

    #[Url(as: 'fp', history: true)]
    public array $filtroPreparaciones = [];

    #[Url(as: 'ft', history: true)]
    public string $filtroTaxon = '';

    #[Url(as: 'fg', history: true)]
    public array $filtroGeografias = [];

    #[Url(as: 'fco', history: true)]
    public string $filtroColector = '';

    #[Url(as: 'ffd', history: true)]
    public string $filtroFechaDesde = '';

    #[Url(as: 'ffh', history: true)]
    public string $filtroFechaHasta = '';

    #[Url(as: 'fm', history: true)]
    public array $filtroMetodos = [];

    #[Url(as: 'flat', history: true)]
    public string $filtroLatMin = '';

    #[Url(as: 'flax', history: true)]
    public string $filtroLatMax = '';

    #[Url(as: 'flon', history: true)]
    public string $filtroLonMin = '';

    #[Url(as: 'flox', history: true)]
    public string $filtroLonMax = '';

    #[Url(as: 'fed', history: true)]
    public string $filtroElevDesde = '';

    #[Url(as: 'feh', history: true)]
    public string $filtroElevHasta = '';

    #[Url(as: 'fb', history: true)]
    public array $filtroBiomas = [];

    #[Url(as: 'fh', history: true)]
    public string $filtroHabitat = '';

    #[Url(as: 'fsti', history: true)]
    public string $filtroTipo = '';

    #[Url(as: 'fca', history: true)]
    public string $filtroCasta = '';

    #[Url(as: 'fes', history: true)]
    public string $filtroEstadio = '';

    #[Url(as: 'fpais', history: true)]
    public string $filtroPais = '';

    #[Url(as: 'fprov', history: true)]
    public string $filtroProvincia = '';

    #[Url(as: 'fph', history: true)]
    public string $filtroFiloId = '';

    #[Url(as: 'fmes', history: true)]
    public string $filtroMes = '';

    #[Url(as: 'fid', history: true)]
    public string $filtroIdentificacion = '';

    #[Url(as: 'fgeo', history: true)]
    public string $filtroSoloUbicacion = '';

    #[Url(as: 'fap', history: true)]
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
        $this->paginaHermanos = 1;
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
        $this->resetValidation();
        $this->pagina = 1;
        $this->paginaHermanos = 1;
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
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)->where('d.state_province_visible', true)
            ->whereRaw(CalidadDatoPublico::textoValido('e.state_province'))
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
            'pais' => $this->filtroPais,
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

    public function cambiarPaginaHermanos(int $pagina): void
    {
        $this->paginaHermanos = max(1, $pagina);
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
        $this->pagina = 1;
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
        $this->nivel = '';
        $this->taxon = '';
        $this->explorar = '';
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
        $this->filtroPais = '';
        $this->filtroFiloId = '';
        $this->filtroMes = '';
        $this->filtroIdentificacion = '';
        $this->filtroSoloUbicacion = '';
        $this->filtroDatosCompletos = '';
        $this->borradorFiltros = $this->valoresFiltros();
        $this->cerrarCelda();
    }

    // ─── Exportación ─────────────────────────────────────────────────────────

    public function descargarDatos(): StreamedResponse
    {
        if ($this->nivel !== 'species' || $this->taxon === '') abort(404);
        $ids = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon)
            ->orderBy('te.fila_origen_excel')->orderBy('te.id')->pluck('te.id')->all();
        $output = $this->exportarHandler->handle(
            new ExportarRegistrosEspecimenesInput($this->taxon, $ids)
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

        $nivel = $this->nivel;
        $taxon = $this->taxon;
        return response()->streamDownload(static function () use ($repositorio, $filtros, $nivel, $taxon): void {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['N.º catálogo', 'Taxón', 'Fecha', 'Localidad del Excel', 'Localidad INEC', 'Código INEC', 'Provincia', 'Latitud', 'Longitud', 'Precisión', 'Tipo'], ';', '"', '');
            $celda = static function (mixed $valor): string {
                $texto = (string) ($valor ?? '');

                return preg_match('/^[=+\-@\t\r]/u', $texto) ? "'".$texto : $texto;
            };
            foreach ($repositorio->cursorParaCsv($filtros, $nivel, $taxon) as $fila) {
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
        if (! in_array($tipo, ['mapa', 'filos', 'riqueza', 'decadas', 'calidad', 'raras', 'estacionalidad', 'altitud', 'metodos'], true)) {
            abort(404);
        }
        $datos = app(PortalEstadisticas::class)->datosParaVista($this->filtrosAnalisis($this->filtrosActuales()));
        [$nombre, $cabecera, $filas] = match ($tipo) {
            'estacionalidad' => ['colecta-por-mes.csv', ['Mes', 'Registros'], array_map(static fn ($fila) => [$fila['mes'], $fila['registros']], $datos['estacionalidad'])],
            'altitud' => ['cobertura-altitudinal.csv', ['Desde (m)', 'Hasta (m)', 'Registros'], array_map(static fn ($fila) => [$fila['desde'], $fila['hasta'], $fila['registros']], $datos['altitud'])],
            'metodos' => ['metodos-colecta.csv', ['Metodo', 'Registros'], array_map(static fn ($fila) => [$fila['metodo'], $fila['registros']], $datos['metodos'])],
            'mapa' => ['coordenadas-coleccion.csv', ['Latitud', 'Longitud', 'Filo', 'Registros'], array_merge([], ...array_map(
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
            'filtroPais' => $this->filtroPais,
            'filtroFiloId' => $this->filtroFiloId,
            'filtroMes' => $this->filtroMes,
            'filtroIdentificacion' => $this->filtroIdentificacion,
            'filtroSoloUbicacion' => $this->filtroSoloUbicacion,
            'filtroDatosCompletos' => $this->filtroDatosCompletos,
        ]);
    }

    #[Computed]
    public function seleccionPublicaChat(): array
    {
        return EnlaceSeleccionCatalogo::parametros($this->filtrosActuales(), $this->nivel, $this->taxon);
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
                'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
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
                'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
                'nivelActual' => $this->nivel, 'taxonActual' => $this->taxon,
                'registrosVista' => $this->cargarDetallesPorEspecimenIds($pagina['ids'], $proveedor, $repoDivulgable),
                'totalRegistrosVista' => $pagina['total'], 'paginaActual' => $pagina['pagina'], 'ultimaPagina' => $pagina['ultima'],
            ]);
        }

        if ($this->vista === 'tarjetas' && $this->nivel === '' && $this->explorar === '') {
            $resumenRaiz = app(EloquentProveedorEspecimenesParaArbol::class)->resumenRaiz($filtros);
            $totalTarjetas = count($resumenRaiz['hijos']);
            $ultimaPagina = max(1, (int) ceil($totalTarjetas / 12));
            $paginaActual = min(max(1, $this->pagina), $ultimaPagina);
            $resumenRaiz['hijos'] = array_slice($resumenRaiz['hijos'], ($paginaActual - 1) * 12, 12);
            return view('catalogopublico::livewire.portal-catalogo', $resumenRaiz + [
                'totalTarjetas' => $totalTarjetas, 'paginaActual' => $paginaActual, 'ultimaPagina' => $ultimaPagina, 'totalEspecimenes' => 0,
                'provinciasDisponibles' => $this->provinciasDisponibles, 'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles, 'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles, 'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
                'nivelActual' => '', 'taxonActual' => '', 'nivelExplorar' => '', 'ruta' => [],
                'nivelesNavegacion' => self::NIVEL_ETIQUETA, 'etiquetasDescendientes' => self::DESCENDANT_LABELS, 'etiquetas' => self::NIVEL_ETIQUETA,
            ]);
        }

        $repositorio = app(EloquentProveedorEspecimenesParaArbol::class);
        $resumen = $repositorio->resumenJerarquia($filtros, $this->nivel, $this->taxon);
        $output = ConstruirArbolTaxonomicoOutput::desdeResumen($resumen);
        $totalGlobal = $resumen['total'];
        $conteos = $resumen['conteos'];
        $ruta = array_map(static fn (array $nodo): array => $nodo + ['etiqueta' => self::NIVEL_ETIQUETA[$nodo['nivel']]], $resumen['rutas'][$this->nivel.':'.$this->taxon] ?? []);
        $padre = $this->taxon === '' ? 'root' : $this->taxon;
        $hijos = array_values(array_filter($resumen['nodos'], static fn (array $nodo): bool => $nodo['padre'] === $padre));
        $especiesActuales = array_values(array_filter($resumen['especies'], static fn (array $nodo): bool => $nodo['padre'] === $padre));
        if ($this->valoresFiltros() !== $this->filtrosAntes) $this->paginaHermanos = 1;
        $hermanos = $this->resolverHermanos($repositorio, $filtros, $ruta);
        $totalHermanos = count($hermanos);
        $ultimaPaginaHermanos = max(1, (int) ceil($totalHermanos / 12));
        $paginaHermanosActual = min(max(1, $this->paginaHermanos), $ultimaPaginaHermanos);
        $hermanos = array_slice($hermanos, ($paginaHermanosActual - 1) * 12, 12);
        $taxonesExplorados = $this->explorar !== '' ? $this->resolverTaxonesParaExplorar($output, $this->explorar, $resumen['rutas']) : [];
        $especimenes = $registrosVista = [];
        $totalRegistrosVista = $totalEspecimenes = $conteos[$this->nivel.':'.$this->taxon] ?? $totalGlobal;
        $totalTarjetas = $this->nivel === 'species' ? $totalEspecimenes : ($this->explorar !== '' ? count($taxonesExplorados) : count($hijos) + count($especiesActuales));
        $ultimaPagina = max(1, (int) ceil($totalTarjetas / 12));
        $paginaActual = min(max(1, $this->pagina), $ultimaPagina);
        if ($this->nivel === 'species' && $this->vista === 'tarjetas') {
            $paginaEspecie = $repositorio->paginaPublica($filtros, $this->pagina, $this->nivel, $this->taxon);
            $especimenes = $this->cargarDetallesPorEspecimenIds($paginaEspecie['ids'], $proveedor, $repoDivulgable);
            $totalRegistrosVista = $totalEspecimenes = $totalTarjetas = $paginaEspecie['total'];
            $paginaActual = $paginaEspecie['pagina'];
            $ultimaPagina = $paginaEspecie['ultima'];
        } elseif ($this->explorar !== '') {
            $taxonesExplorados = array_slice($taxonesExplorados, ($paginaActual - 1) * 12, 12);
        } else {
            $tarjetas = array_merge(array_map(static fn (array $n): array => ['tipo' => 'nodo', 'nodo' => $n], $hijos), array_map(static fn (array $n): array => ['tipo' => 'especie', 'nodo' => $n], $especiesActuales));
            $tarjetas = array_slice($tarjetas, ($paginaActual - 1) * 12, 12);
            $hijos = array_values(array_map(static fn (array $n): array => $n['nodo'], array_filter($tarjetas, static fn (array $n): bool => $n['tipo'] === 'nodo')));
            $especiesActuales = array_values(array_map(static fn (array $n): array => $n['nodo'], array_filter($tarjetas, static fn (array $n): bool => $n['tipo'] === 'especie')));
        }
        $puntosEspecie = $this->nivel === 'species'
            ? app(PortalEstadisticas::class)->puntosParaMapa($this->filtrosAnalisis($filtros)) : [];
        $idTaxonActual = $ruta === [] ? '' : ($ruta[array_key_last($ruta)]['id'] ?? '');
        $claveMapaEspecie = sha1(json_encode([$idTaxonActual, $filtros, $puntosEspecie]));
        $descendientes = $resumen['descendientes'];

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
            'filtroPais' => $this->filtroPais,
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
            'portadas' => $this->cargarPortadas(array_merge($hijos, $especiesActuales, $taxonesExplorados)),
            'galeriaEspecie' => $galeriaEspecie,
            'imagenesPorEspecimen' => $imagenesPorEspecimen,
            'hijos' => $hijos,
            'especiesActuales' => $especiesActuales,
            'hermanos' => $hermanos,
            'totalHermanos' => $totalHermanos,
            'paginaHermanosActual' => $paginaHermanosActual,
            'ultimaPaginaHermanos' => $ultimaPaginaHermanos,
            'especimenes' => $especimenes,
            'totalEspecimenes' => $totalEspecimenes,
            'totalTarjetas' => $totalTarjetas,
            'puntosEspecie' => $puntosEspecie,
            'idTaxonActual' => $idTaxonActual,
            'claveMapaEspecie' => $claveMapaEspecie,
            'registrosVista' => $registrosVista,
            'totalRegistrosVista' => $totalRegistrosVista,
            'paginaActual' => $paginaActual,
            'ultimaPagina' => $ultimaPagina,
            'conteos' => $conteos,
            'descendientes' => $descendientes,
            'taxonesExplorados' => $taxonesExplorados,
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
            'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
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
    private function cargarPortadas(array $tarjetas): array
    {
        $claves = [];
        foreach ($tarjetas as $tarjeta) {
            $nivel = $tarjeta['nivel'] ?? (isset($tarjeta['especie']) ? 'species' : '');
            if (! in_array($nivel, ['genus', 'species'], true)) continue;
            $taxon = $tarjeta['taxon'] ?? $tarjeta['especie'];
            $claves[$nivel.':'.$taxon] = [$nivel, $taxon];
        }
        if ($claves === []) return [];
        return DB::table('divulgacion.imagenes_por_defecto as d')
            ->join('divulgacion.imagenes_taxonomicas as i', 'i.id', '=', 'd.imagen_id')
            ->join('taxonomia.especimenes as e', 'e.occurrence_id', '=', 'i.occurrence_id')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'e.id')
            ->where('ed.publicado', true)->where('e.coordenadas_otras_regiones', false)->where('ed.scientific_name_visible', true)
            ->where(fn ($q) => $q->where('d.nivel', '<>', 'genus')->orWhere('ed.genus_visible', true))
            ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = i.occurrence_id) = 1')
            ->where(function ($q) use ($claves): void {
                foreach ($claves as [$nivel, $taxon]) $q->orWhere(fn ($par) => $par->where('d.nivel', $nivel)->where('d.valor_taxon', $taxon));
            })
            ->select('d.nivel', 'd.valor_taxon', 'i.ruta', 'i.disco')
            ->limit(12)
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

        $consulta = DB::table('divulgacion.imagenes_taxonomicas')
            ->whereIn('occurrence_id', $occurrenceIDs)
            ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = divulgacion.imagenes_taxonomicas.occurrence_id) = 1')
            ->selectRaw('occurrence_id, ruta, disco, nombre_original, ROW_NUMBER() OVER (PARTITION BY occurrence_id ORDER BY created_at, id) AS posicion');
        return DB::query()->fromSub($consulta, 'fotos_publicas')->where('posicion', '<=', 12)->orderBy('occurrence_id')->orderBy('posicion')
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

    /** @return list<array{nivel: string, taxon: string, esEspecie: bool, total: int}> */
    private function resolverHermanos(EloquentProveedorEspecimenesParaArbol $repositorio, FiltrosBusqueda $filtros, array $ruta): array
    {
        if ($this->nivel === '' || $ruta === []) return [];
        $padre = count($ruta) > 1 ? $ruta[count($ruta) - 2] : ['nivel' => '', 'taxon' => 'root'];
        // El contexto lateral conserva filtros y permisos del padre público;
        // nunca reemplaza la selección exacta de registros de la ficha principal.
        $contexto = $repositorio->resumenJerarquia($filtros, $padre['nivel'], $padre['nivel'] !== '' ? $padre['taxon'] : '');
        $esEspecie = $this->nivel === 'species';
        $candidatos = $esEspecie ? $contexto['especies'] : $contexto['nodos'];
        $hermanos = [];
        foreach ($candidatos as $nodo) {
            $nivel = $esEspecie ? 'species' : $nodo['nivel'];
            $taxon = $esEspecie ? $nodo['especie'] : $nodo['taxon'];
            if ($nivel !== $this->nivel || $nodo['padre'] !== $padre['taxon'] || $taxon === $this->taxon) continue;
            $hermanos[] = ['nivel' => $nivel, 'taxon' => $taxon, 'esEspecie' => $esEspecie, 'total' => (int) $nodo['total']];
        }
        return $hermanos;
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
        foreach ($repoDivulgable->buscarPublicadosPorEspecimenIds(array_map(fn (DatosEspecimenProveedor $dto): string => $dto->especimenId, $datos)) as $divulgable) {
            $configPorEspecimen[$divulgable->especimenId()] = $divulgable;
        }

        $datosPublicados = array_values(array_filter($datos, fn (DatosEspecimenProveedor $dto): bool => isset($configPorEspecimen[$dto->especimenId])));

        return array_map(
            function (DatosEspecimenProveedor $dto) use ($configPorEspecimen): object {
                $cfg = $configPorEspecimen[$dto->especimenId];
                $ver = fn (callable $flag): bool => $flag($cfg);
                $g = fn (bool $visible, mixed $valor): mixed => $visible ? $valor : null;

                return (object) [
                    'especimen_id' => $dto->especimenId,
                    'occurrence_id' => $g($ver(fn (EspecimenDivulgable $d) => $d->occurrenceIDVisible()), $dto->occurrenceId),
                    'scientific_name' => $g($ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()), $dto->scientificName),
                    'taxon_en_revision' => $ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()) && ! CalidadDatoPublico::esTextoValido($dto->scientificName),
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
    private function resolverTaxonesParaExplorar(ConstruirArbolTaxonomicoOutput $output, string $nivel, array $rutas = []): array
    {
        $jerarquia = static function (array $nodo) use ($rutas): array {
            // Explorer reúne el taxón completo; el total de una rama concreta
            // se sustituye por el conteo global que ya recibe la vista.
            unset($nodo['total']);
            $nodo['jerarquia'] = array_column($rutas[$nodo['nivel'].':'.$nodo['taxon']] ?? [], 'taxon', 'nivel');
            return $nodo;
        };
        if ($nivel === 'species') {
            return collect($output->especies)
                ->map(fn ($e) => ['nivel' => 'species', 'taxon' => $e['especie'], 'padre' => $e['padre']])
                ->unique('taxon')
                ->map($jerarquia)
                ->sortBy('taxon')
                ->values()
                ->all();
        }

        return collect($output->nodosJerarquicos)
            ->filter(fn ($n) => $n['nivel'] === $nivel)
            ->unique('taxon')
            ->map($jerarquia)
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
