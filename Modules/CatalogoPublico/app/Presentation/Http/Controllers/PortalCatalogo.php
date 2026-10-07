<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Validation\ValidationException;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;
use Modules\CatalogoPublico\Application\Services\ColumnasRegistroPublico;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;
use Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico;
use Modules\CatalogoPublico\Infrastructure\ConsultaMapaNoDisponible;
use Modules\CatalogoPublico\Domain\ValueObjects\PerfilExportacionPublica;
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
use Modules\CatalogoPublico\Domain\ValueObjects\LocalidadInecPublica;
use Modules\CatalogoPublico\Domain\ValueObjects\NumeroExportacion;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.portal', params: ['title' => 'Catálogo taxonómico · Departamento de Biología — EPN'])]
final class PortalCatalogo extends Component
{
    use ExploraCeldaMapa;
    use ExploraTaxonomia;

    public array $borradorFiltros = [];

    public string $avisoFiltrosDependientes = '';

    #[Locked]
    public bool $mostrarFotoComposicion = false;

    private array $filtrosAntes = [];

    private int $restauracionHistorial = 0;

    #[Locked]
    public int $versionNavegacion = 0;

    public string $avisoSeleccionUrl = '';

    #[Locked]
    public array $estadoInicialUrl = [];

    public function placeholder(): View
    {
        $this->estadoInicialUrl = array_intersect_key(request()->query(), self::PROPIEDADES_URL + ['fxprov' => '']);
        return view('catalogopublico::components.catalogo-cargando');
    }

    #[Locked]
    public ?string $registroFichaId = null;

    #[Computed]
    public function columnasPublicas(): array
    {
        return app(ColumnasRegistroPublico::class)->visibles();
    }

    #[Computed]
    public function filtrosAplicados(): array
    {
        return $this->valoresBorrador();
    }

    /** Resumen del estado aplicado; nunca muestra el borrador como si ya estuviera activo. */
    #[Computed]
    public function criteriosActivos(): array
    {
        $etiquetas = ['filtroCatalogo' => 'Catálogo', 'filtroTaxon' => 'Taxón', 'filtroProvincia' => 'Provincia',
            'filtroPais' => 'País', 'filtroFiloId' => 'Filo', 'filtroFilos' => 'Filo', 'filtroProvincias' => 'Provincia',
            'filtroGeografias' => 'Localidad', 'filtroPreparaciones' => 'Preparación', 'filtroMetodos' => 'Método',
            'filtroColector' => 'Colector', 'filtroBiomas' => 'Bioma', 'filtroHabitat' => 'Hábitat',
            'filtroTipo' => 'Condición de tipo', 'filtroDisposicion' => 'Disposición', 'filtroCasta' => 'Casta',
            'filtroEstadio' => 'Estadio', 'filtroMes' => 'Mes', 'filtroIdentificacion' => 'Identificación',
            'filtroSoloUbicacion' => 'Coordenadas públicas', 'filtroDatosCompletos' => 'Datos completos'];
        $criterios = [];
        if ($this->taxon !== '') $criterios[] = ['clave' => 'jerarquia', 'etiqueta' => 'Selección taxonómica', 'valor' => $this->taxon, 'indice' => -1];
        if ($this->filtroTaxonId !== '') {
            $nodo = app(\Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico::class)->taxon($this->contextoExplorador(), $this->filtroTaxonId);
            $criterios[] = ['clave' => 'filtroTaxonId', 'etiqueta' => 'Taxón del explorador', 'valor' => $nodo['nombre'] ?? 'Taxón seleccionado', 'indice' => -1];
        }
        foreach ($etiquetas as $campo => $etiqueta) {
            $valores = is_array($this->{$campo}) ? $this->{$campo} : [$this->{$campo}];
            foreach ($valores as $indice => $valor) {
                if (trim((string) $valor) === '') continue;
                $visible = match ($campo) {
                    'filtroMes' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][(int) $valor - 1] ?? $valor,
                    'filtroEstadio' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::etapa(\Modules\CatalogoPublico\Domain\ValueObjects\EstadioVidaPublico::clave($valor)),
                    'filtroTipo' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::tipo(\Modules\CatalogoPublico\Domain\ValueObjects\CondicionMaterialPublica::tipoFiltro($valor)),
                    'filtroDisposicion' => \Modules\CatalogoPublico\Infrastructure\EtiquetaDatoPublico::disposicion(\Modules\CatalogoPublico\Domain\ValueObjects\CondicionMaterialPublica::disposicionFiltro($valor)),
                    'filtroFiloId', 'filtroFilos' => collect($this->filosDisponibles)->firstWhere('id', $valor)['nombre_cientifico'] ?? 'Filo seleccionado',
                    'filtroSoloUbicacion', 'filtroDatosCompletos' => 'Sí',
                    'filtroMetodos' => ProtocoloColectaPublico::etiqueta($valor),
                    default => $valor,
                };
                $criterios[] = ['clave' => $campo, 'etiqueta' => $etiqueta, 'valor' => $visible, 'indice' => is_array($this->{$campo}) ? $indice : -1];
            }
        }
        foreach (['periodo' => ['Periodo', $this->filtroFechaDesde, $this->filtroFechaHasta],
            'elevacion' => ['Elevación (m)', $this->filtroElevDesde, $this->filtroElevHasta],
            'latitud' => ['Latitud', $this->filtroLatMin, $this->filtroLatMax],
            'longitud' => ['Longitud', $this->filtroLonMin, $this->filtroLonMax]] as $clave => [$etiqueta, $desde, $hasta]) {
            if ($desde !== '' || $hasta !== '') $criterios[] = ['clave' => $clave, 'etiqueta' => $etiqueta,
                'valor' => ($desde === '' ? 'sin mínimo' : $desde).' — '.($hasta === '' ? 'sin máximo' : $hasta), 'indice' => -1];
        }
        return $criterios;
    }

    public function retirarCriterio(string $clave, int $indice = -1): void
    {
        // La lista se obtiene del estado público; no permite asignar propiedades arbitrarias.
        if (! array_any($this->criteriosActivos, static fn (array $c): bool => $c['clave'] === $clave && $c['indice'] === $indice)) return;
        $this->mostrarFotoComposicion = false;
        $grupos = ['periodo' => ['filtroFechaDesde', 'filtroFechaHasta'], 'elevacion' => ['filtroElevDesde', 'filtroElevHasta'],
            'latitud' => ['filtroLatMin', 'filtroLatMax', 'filtroLatitud'], 'longitud' => ['filtroLonMin', 'filtroLonMax', 'filtroLongitud']];
        if ($clave === 'jerarquia') {
            $this->nivel = $this->taxon = $this->explorar = '';
        } elseif (isset($grupos[$clave])) {
            foreach ($grupos[$clave] as $campo) $this->{$campo} = '';
        } elseif ($indice >= 0) {
            $valores = $this->{$clave};
            unset($valores[$indice]);
            $this->{$clave} = array_values($valores);
        } else {
            $this->{$clave} = '';
        }
        unset($this->criteriosActivos);
        $this->actualizarFiltros();
        $this->borradorFiltros = $this->valoresBorrador();
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
        $this->dispatch('criterio-retirado');
    }

    public function abrirFichaRegistro(string $id): void
    {
        if (! \Illuminate\Support\Str::isUuid($id)) abort(404);
        $consulta = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon);
        if (! $consulta->where('te.id', $id)->exists()) abort(404);
        $this->registroFichaId = $id;
        unset($this->fichaRegistro);
        $this->dispatch('abrir-ficha-registro');
    }

    public function cerrarFichaRegistro(): void
    {
        $this->registroFichaId = null;
        unset($this->fichaRegistro);
    }

    #[Computed]
    public function fichaRegistro(): ?array
    {
        if ($this->registroFichaId === null) return null;
        $consulta = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon);
        if (! $consulta->where('te.id', $this->registroFichaId)->exists()) return null;
        $registros = $this->cargarDetallesPorEspecimenIds([$this->registroFichaId], app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
        $registro = $registros[0] ?? null;
        if ($registro === null) return null;
        $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter([$registro->occurrence_id])));
        $familiaPublica = app(EloquentProveedorEspecimenesParaArbol::class)->familiaPublicaPorEspecimenId(
            $this->registroFichaId, $this->filtrosActuales(), $this->nivel, $this->taxon,
        );
        return ['registro' => $registro, 'fotos' => $imagenes[$registro->occurrence_id ?? ''] ?? [], 'familia_publica' => $familiaPublica];
    }

    // Un contrato plano compartido por montaje, enlaces copiados y Atrás/Adelante.
    // El borrador, los diálogos y el chat nunca forman parte del historial público.
    private const array PROPIEDADES_URL = [
        'nivel' => 'nivel', 'taxon' => 'taxon', 'explorar' => 'explorar', 'vista' => 'vista', 'pagina' => 'pagina',
        'fc' => 'filtroCatalogo', 'fp' => 'filtroPreparaciones', 'ft' => 'filtroTaxon', 'fti' => 'filtroTaxonId', 'fg' => 'filtroGeografias',
        'fco' => 'filtroColector', 'ffd' => 'filtroFechaDesde', 'ffh' => 'filtroFechaHasta', 'fm' => 'filtroMetodos',
        'flat' => 'filtroLatMin', 'flax' => 'filtroLatMax', 'flon' => 'filtroLonMin', 'flox' => 'filtroLonMax',
        'fed' => 'filtroElevDesde', 'feh' => 'filtroElevHasta', 'fb' => 'filtroBiomas', 'fh' => 'filtroHabitat',
        'fsti' => 'filtroTipo', 'fd' => 'filtroDisposicion', 'fca' => 'filtroCasta', 'fes' => 'filtroEstadio', 'fpais' => 'filtroPais',
        'fprov' => 'filtroProvincia', 'fph' => 'filtroFiloId', 'fmes' => 'filtroMes', 'fid' => 'filtroIdentificacion',
        'fprovs' => 'filtroProvincias', 'fphs' => 'filtroFilos',
        'fgeo' => 'filtroSoloUbicacion', 'fap' => 'filtroDatosCompletos',
    ];

    public function hydrate(): void
    {
        $this->filtrosAntes = $this->valoresFiltros();
    }

    public function mount(): void
    {
        $this->aplicarEstadoUrl($this->estadoInicialUrl !== [] ? $this->estadoInicialUrl : request()->query());
        $this->validarSeleccionUrl();
        $paginaSolicitada = $this->pagina;
        try {
            $this->actualizarFiltros();
        } catch (ValidationException $error) {
            // Una URL inválida no debe convertirse en una selección vacía engañosa.
            $this->limpiarEntradasUrlInvalidas($error->errors());
            $this->setErrorBag($error->errors());
        }
        $this->pagina = max(1, $paginaSolicitada);
        $this->borradorFiltros = $this->valoresBorrador();
        $this->filtrosAntes = $this->valoresFiltros();
    }

    private function valoresFiltros(): array
    {
        return array_filter(get_object_vars($this), static fn (string $clave): bool => preg_match('/^filtro[A-Z]/', $clave) === 1, ARRAY_FILTER_USE_KEY);
    }

    private function valoresBorrador(): array
    {
        return array_replace($this->valoresFiltros(), [
            'filtroFilos' => $this->filtrosActuales()->filosSeleccionados(),
            'filtroProvincias' => $this->filtrosActuales()->provinciasSeleccionadas(),
        ]);
    }

    private function limpiarEntradasUrlInvalidas(array $errores): void
    {
        foreach (array_keys($errores) as $campo) {
            $propiedad = explode('.', $campo)[0];
            $this->{$propiedad} = is_array($this->{$propiedad}) ? [] : '';
        }
        foreach (['filtroLat', 'filtroLon'] as $prefijo) {
            if (isset($errores[$prefijo.'Min']) || isset($errores[$prefijo.'Max'])) {
                $this->{$prefijo.'Min'} = '';
                $this->{$prefijo.'Max'} = '';
            }
        }
        $this->sincronizarCoordenadasSimples();
    }

    public function aplicarBorrador(): void
    {
        $anteriores = $this->valoresFiltros();
        // La identidad seleccionada sólo cambia mediante el explorador validado o los enlaces públicos.
        foreach ($anteriores as $propiedad => $valor) {
            if ($propiedad !== 'filtroTaxonId') $this->{$propiedad} = $this->borradorFiltros[$propiedad] ?? $valor;
        }
        if ($this->filtroFiloId !== '') $this->filtroFilos = array_values(array_diff($this->filtroFilos, [$this->filtroFiloId]));
        if ($this->filtroProvincia !== '') $this->filtroProvincias = array_values(array_diff($this->filtroProvincias, [$this->filtroProvincia]));
        if ($this->filtroLatitud !== $anteriores['filtroLatitud']) $this->filtroLatMin = $this->filtroLatMax = $this->filtroLatitud;
        if ($this->filtroLongitud !== $anteriores['filtroLongitud']) $this->filtroLonMin = $this->filtroLonMax = $this->filtroLongitud;
        try {
            $this->actualizarFiltros();
        } catch (ValidationException $error) {
            foreach ($anteriores as $propiedad => $valor) $this->{$propiedad} = $valor;
            throw $error;
        }
        $this->borradorFiltros = $this->valoresBorrador();
        unset($this->geografiaDisponible, $this->provinciasDisponibles, $this->localidadesDisponibles);
        $this->mostrarFotoComposicion = false;
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
    }

    public function updatedBorradorFiltros(mixed $valor, ?string $clave = null): void
    {
        $campo = explode('.', $clave ?? '')[0];
        if (! array_key_exists($campo, $this->valoresFiltros())) return;
        $this->avisoFiltrosDependientes = '';
        if ($campo === 'filtroFilos') $this->borradorFiltros['filtroFiloId'] = '';
        if ($campo === 'filtroProvincias') $this->borradorFiltros['filtroProvincia'] = '';
        if ($campo === 'filtroFiloId') $this->borradorFiltros['filtroFilos'] = [];
        if ($campo === 'filtroProvincia') $this->borradorFiltros['filtroProvincias'] = [];
        $this->aplicarBorrador();
        if (in_array($campo, ['filtroFiloId', 'filtroFilos', 'filtroTaxon', 'filtroProvincia', 'filtroProvincias'], true)) $this->ajustarGeografiaDependiente();
    }

    public function quitarLocalidad(int $indice): void
    {
        if ($indice < 0 || ! array_key_exists($indice, $this->borradorFiltros['filtroGeografias'])) return;
        unset($this->borradorFiltros['filtroGeografias'][$indice]);
        $this->borradorFiltros['filtroGeografias'] = array_values($this->borradorFiltros['filtroGeografias']);
        $this->aplicarBorrador();
    }

    private function ajustarGeografiaDependiente(): void
    {
        unset($this->geografiaDisponible, $this->provinciasDisponibles, $this->localidadesDisponibles);
        $retirados = [];
        $provincias = array_values(array_filter($this->filtroProvincias, fn (string $nombre): bool => NormalizacionGeografica::nombreDisponible($nombre, $this->provinciasDisponibles) !== null));
        if ($provincias !== $this->filtroProvincias) $retirados[] = 'Provincia';
        $this->filtroProvincias = $provincias;
        if ($this->filtroProvincia !== '' && NormalizacionGeografica::nombreDisponible($this->filtroProvincia, $this->provinciasDisponibles) === null) {
            $this->filtroProvincia = '';
            $retirados[] = 'Provincia';
            unset($this->localidadesDisponibles);
        }
        $localidades = array_values(array_filter($this->filtroGeografias, fn (string $nombre): bool => NormalizacionGeografica::nombreDisponible($nombre, $this->localidadesDisponibles) !== null));
        if ($localidades !== $this->filtroGeografias) $retirados[] = 'Localidad';
        $this->filtroGeografias = $localidades;
        $this->borradorFiltros = $this->valoresBorrador();
        if ($retirados !== []) $this->avisoFiltrosDependientes = 'Se retiró '.implode(' y ', $retirados).' porque no tiene registros en la nueva selección.';
    }

    public function dehydrate(): void
    {
        if (! $this->getErrorBag()->any() && $this->valoresFiltros() !== $this->filtrosAntes) $this->borradorFiltros = $this->valoresBorrador();
    }

    public function rendered(View $view, string $html): void
    {
        // Render ya resolvió la página; SupportEvents captura antes del hook dehydrate del componente.
        $this->dispatch('catalogo-estado-url', estado: $this->estadoParaUrl(), restauracion: $this->restauracionHistorial, version: $this->versionNavegacion);
    }

    private function estadoParaUrl(): array
    {
        $estado = [];
        foreach (self::PROPIEDADES_URL as $alias => $propiedad) $estado[$alias] = $this->{$propiedad};

        return $estado;
    }

    #[Computed]
    public function historialCatalogo(): array
    {
        $valoresIniciales = get_class_vars(self::class);
        $defectos = [];
        foreach (self::PROPIEDADES_URL as $alias => $propiedad) $defectos[$alias] = $valoresIniciales[$propiedad];

        return ['estado' => $this->estadoParaUrl(), 'defectos' => $defectos, 'propiedades' => self::PROPIEDADES_URL, 'version' => $this->versionNavegacion];
    }

    private function aplicarEstadoUrl(array $parametros): void
    {
        $this->avisoSeleccionUrl = ! empty($parametros['fxprov']) ? 'El filtro Excluir provincia fue retirado. Se conservan los criterios incluidos; revisa la selección antes de descargar.' : '';
        $defectos = get_class_vars(self::class);
        foreach (self::PROPIEDADES_URL as $alias => $propiedad) {
            $valor = $parametros[$alias] ?? $defectos[$propiedad];
            if (is_array($defectos[$propiedad])) {
                $this->{$propiedad} = is_array($valor) ? array_values(array_filter(array_map(
                    static fn (mixed $item): string => is_scalar($item) ? mb_substr((string) $item, 0, 2000) : '',
                    array_slice($valor, 0, 100),
                ), static fn (string $item): bool => $item !== '')) : [];
            } elseif ($propiedad === 'pagina') {
                $this->pagina = is_scalar($valor) && filter_var($valor, FILTER_VALIDATE_INT) !== false ? max(1, (int) $valor) : 1;
            } else {
                $this->{$propiedad} = is_scalar($valor) ? mb_substr((string) $valor, 0, 2000) : $defectos[$propiedad];
            }
        }
        $this->sincronizarCoordenadasSimples();
    }

    private function sincronizarCoordenadasSimples(): void
    {
        $this->filtroLatitud = $this->filtroLatMin !== '' && $this->filtroLatMin === $this->filtroLatMax ? $this->filtroLatMin : '';
        $this->filtroLongitud = $this->filtroLonMin !== '' && $this->filtroLonMin === $this->filtroLonMax ? $this->filtroLonMin : '';
    }

    private function validarSeleccionUrl(): void
    {
        if ($this->filtroTaxonId !== '') {
            $nodo = app(\Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico::class)->taxon(FiltrosBusqueda::vacio(), $this->filtroTaxonId);
            $this->filtroTaxonId = $nodo['id'] ?? '';
            if ($nodo === null) $this->avisoSeleccionUrl = 'El taxón del enlace ya no está disponible públicamente. Se conservaron los demás filtros.';
        }
        if (! in_array($this->vista, ['tarjetas', 'registros', 'mapa'], true)) $this->vista = 'tarjetas';
        if ($this->explorar !== '' && ! isset(self::NIVEL_PLURAL[$this->explorar])) $this->explorar = '';
        if ($this->explorar !== '') {
            $this->nivel = $this->taxon = '';
        } elseif ($this->nivel !== '' || $this->taxon !== '') {
            $rangos = ['phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'family' => 'familia', 'genus' => 'genero', 'species' => 'especie'];
            $niveles = [];
            if (CalidadDatoPublico::esTextoValido($this->taxon)) {
                $rangosPresentes = DB::table('taxonomia.taxones')->where('nombre_cientifico', $this->taxon)
                    ->whereIn('rango', array_values($rangos))->distinct()->pluck('rango')->all();
                $repositorio = app(EloquentProveedorEspecimenesParaArbol::class);
                foreach ($rangos as $nivel => $rango) {
                    if (in_array($rango, $rangosPresentes, true)
                        && $repositorio->consultaPublica(FiltrosBusqueda::desde([]), $nivel, $this->taxon)->exists()) $niveles[] = $nivel;
                }
            }
            if (! in_array($this->nivel, $niveles, true)) {
                if (count($niveles) === 1) {
                    $this->nivel = $niveles[0];
                    $this->avisoSeleccionUrl = 'El rango del enlace se ajustó al taxón publicado en la colección. Se conservaron los filtros.';
                } else {
                    $this->nivel = $this->taxon = '';
                    $this->avisoSeleccionUrl = 'El enlace no identifica un taxón público inequívoco. Se conservan los filtros para consultar los registros.';
                }
                $this->pagina = 1;
            }
        }
        if ($this->filtroFiloId !== '') {
            $id = $this->identificadorFilo($this->filtroFiloId);
            if ($id === null) $this->avisoSeleccionUrl = 'El filo del enlace no corresponde a una opción disponible. Se conservaron los demás filtros.';
            $this->filtroFiloId = $id ?? '';
        }
        $filos = array_values(array_unique(array_filter(array_map($this->identificadorFilo(...), $this->filtroFilos))));
        if (count($filos) !== count($this->filtroFilos)) $this->avisoSeleccionUrl = 'Se retiraron filos no disponibles del enlace. Se conservaron los demás filtros.';
        $this->filtroFilos = $filos;
    }

    /** Restaura la selección completa en un único request, nunca propiedad a propiedad. */
    public function restaurarSeleccionUrl(array $parametros, int $restauracion = 0): void
    {
        $this->mostrarFotoComposicion = false;
        $this->restauracionHistorial = max(0, $restauracion);
        $this->versionNavegacion = max($this->versionNavegacion, $this->restauracionHistorial);
        $this->aplicarEstadoUrl($parametros);
        $this->validarSeleccionUrl();
        $pagina = $this->pagina;
        try {
            $this->actualizarFiltros();
        } catch (ValidationException $error) {
            $this->limpiarEntradasUrlInvalidas($error->errors());
            $this->setErrorBag($error->errors());
        }
        $this->pagina = $pagina;
        $this->paginaHermanos = 1;
        $this->borradorFiltros = $this->valoresBorrador();
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
    }

    public function quitarTaxon(): void
    {
        $this->navegar('', '');
    }

    public function seleccionarMes(int $mes): void
    {
        if ($mes < 1 || $mes > 12) return;
        $this->mostrarFotoComposicion = false;
        $this->filtroMes = (string) $mes;
        $this->pagina = 1;
    }

    public function seleccionarAltitud(int $desde, int $hasta): void
    {
        if ($desde < -500 || $hasta > 9000 || $desde > $hasta) return;
        $this->mostrarFotoComposicion = false;
        $this->filtroElevDesde = (string) $desde;
        $this->filtroElevHasta = (string) $hasta;
        $this->pagina = 1;
    }

    public function seleccionarMetodo(string $metodo): void
    {
        $metodo = ProtocoloColectaPublico::clave($metodo);
        if (! in_array($metodo, $this->metodosRecoleccionDisponibles, true)) return;
        $this->mostrarFotoComposicion = false;
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

    public string $nivel = '';

    public string $taxon = '';

    public string $explorar = '';

    public string $vista = 'tarjetas';

    public int $pagina = 1;

    public int $paginaHermanos = 1;

    // ─── Filtros (URL-persistidos) ────────────────────────────────────────────

    public string $filtroCatalogo = '';

    public array $filtroPreparaciones = [];

    public string $filtroTaxon = '';

    public array $filtroGeografias = [];

    public string $filtroColector = '';

    public string $filtroFechaDesde = '';

    public string $filtroFechaHasta = '';

    public array $filtroMetodos = [];

    public string $filtroLatMin = '';

    public string $filtroLatMax = '';

    public string $filtroLonMin = '';

    public string $filtroLonMax = '';

    public string $filtroLatitud = '';

    public string $filtroLongitud = '';

    public string $filtroElevDesde = '';

    public string $filtroElevHasta = '';

    public array $filtroBiomas = [];

    public string $filtroHabitat = '';

    public string $filtroTipo = '';

    public string $filtroDisposicion = '';

    public string $filtroCasta = '';

    public string $filtroEstadio = '';

    public string $filtroPais = '';

    public string $filtroProvincia = '';

    public string $filtroFiloId = '';

    public array $filtroFilos = [];

    public array $filtroProvincias = [];

    #[Locked]
    public int $registrosPorPagina = 6;

    public function ajustarRegistrosPorPagina(int $cantidad, bool $ubicacion = false): void
    {
        $cantidad = max(1, min(100, $cantidad));
        if ($ubicacion) {
            if ($this->registrosPorPaginaCelda === $cantidad) return;
            $inicio = ($this->paginaCelda - 1) * $this->registrosPorPaginaCelda;
            $this->registrosPorPaginaCelda = $cantidad;
            $this->paginaCelda = intdiv($inicio, $cantidad) + 1;
            unset($this->detalleCelda);
        } else {
            if ($this->registrosPorPagina === $cantidad) return;
            $inicio = ($this->pagina - 1) * $this->registrosPorPagina;
            $this->registrosPorPagina = $cantidad;
            $this->pagina = intdiv($inicio, $cantidad) + 1;
        }
    }

    public string $filtroMes = '';

    public string $filtroIdentificacion = '';

    public string $filtroSoloUbicacion = '';

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
        $this->filtroTaxonId = '';
        $this->mostrarFotoComposicion = false;
        $this->nivel = $nivel;
        $this->taxon = $taxon;
        $this->explorar = '';
        $this->pagina = 1;
        $this->paginaHermanos = 1;
        $this->validarSeleccionUrl();
        $this->cerrarFichaRegistro();
    }

    public function cambiarVista(string $vista): void
    {
        if (in_array($vista, ['tarjetas', 'registros', 'mapa'], true)) {
            $this->vista = $vista;
            $this->pagina = 1;
            $this->cerrarFichaRegistro();
        }
    }

    public function actualizarFiltros(): void
    {
        $this->filtroMetodos = array_values(array_unique(array_map(ProtocoloColectaPublico::clave(...), $this->filtroMetodos)));
        $this->validate([
            'filtroTaxonId' => ['nullable', 'uuid'],
            'filtroFilos' => ['array', 'max:100'],
            'filtroFilos.*' => ['uuid'],
            'filtroProvincias' => ['array', 'max:100'],
            'filtroProvincias.*' => ['string', 'max:2000'],
            'filtroFechaDesde' => ['nullable', 'date_format:Y-m-d'],
            'filtroFechaHasta' => array_filter(['nullable', 'date_format:Y-m-d', $this->filtroFechaDesde !== '' ? 'after_or_equal:filtroFechaDesde' : null]),
            'filtroLatitud' => ['nullable', 'numeric', 'between:-90,90'],
            'filtroLongitud' => ['nullable', 'numeric', 'between:-180,180'],
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
        unset($this->geografiaDisponible, $this->provinciasDisponibles, $this->localidadesDisponibles);
        if ($this->filtroProvincia !== '') $this->filtroProvincia = NormalizacionGeografica::nombreDisponible($this->filtroProvincia, $this->provinciasDisponibles) ?? $this->filtroProvincia;
        $this->pagina = 1;
        $this->paginaHermanos = 1;
        $this->sincronizarCoordenadasSimples();
    }

    public function seleccionarProvincia(string $provincia): void
    {
        $opcion = NormalizacionGeografica::nombreDisponible($provincia, $this->provinciasDisponibles);
        if ($opcion !== null) {
            $this->mostrarFotoComposicion = false;
            $provincias = $this->filtrosActuales()->provinciasSeleccionadas();
            $provincias = in_array($opcion, $provincias, true) ? array_values(array_diff($provincias, [$opcion])) : [...$provincias, $opcion];
            $this->filtroProvincia = count($provincias) === 1 ? $provincias[0] : '';
            $this->filtroProvincias = count($provincias) > 1 ? $provincias : [];
            $this->ajustarGeografiaDependiente();
            $this->borradorFiltros = $this->valoresBorrador();
            $this->pagina = 1;
        }
    }

    public function seleccionarDecada(int $decada): void
    {
        if ($decada >= 0 && $decada <= 2090 && $decada % 10 === 0) {
            $this->mostrarFotoComposicion = false;
            $this->filtroFechaDesde = sprintf('%04d-01-01', $decada);
            $this->filtroFechaHasta = sprintf('%04d-12-31', $decada + 9);
            $this->pagina = 1;
        }
    }

    public function identificadorFilo(string $identificador): ?string
    {
        $identificador = trim($identificador);
        // Un enlace UUID de un filo conocido conserva su selección vacía aunque
        // ya no aparezca entre las opciones con material público elegible.
        if (\Illuminate\Support\Str::isUuid($identificador)) {
            $filo = DB::table('taxonomia.taxones')->where('id', $identificador)->where('rango', 'phylum')->first(['id', 'nombre_cientifico']);
            return $filo && CalidadDatoPublico::esTextoValido($filo->nombre_cientifico) ? (string) $filo->id : null;
        }
        $coincidencias = [];
        foreach ($this->filosDisponibles as $filo) {
            if (strcasecmp($filo['id'], $identificador) === 0) return $filo['id'];
            if (mb_strtolower(trim($filo['nombre_cientifico'])) === mb_strtolower($identificador)) $coincidencias[] = $filo['id'];
        }

        return count($coincidencias) === 1 ? $coincidencias[0] : null;
    }

    public function seleccionarFilo(string $identificador): void
    {
        $id = $this->identificadorFilo($identificador);
        if ($id === null) return;
        $filos = $this->filtrosActuales()->filosSeleccionados();
        $filos = in_array($id, $filos, true) ? array_values(array_diff($filos, [$id])) : [...$filos, $id];
        $this->filtroFiloId = count($filos) === 1 ? $filos[0] : '';
        $this->filtroFilos = count($filos) > 1 ? $filos : [];
        $this->mostrarFotoComposicion = $filos !== [];
        $this->ajustarGeografiaDependiente();
        $this->borradorFiltros = $this->valoresBorrador();
        $this->pagina = 1;
        $this->paginaHermanos = 1;
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
    }

    public function seleccionarArea(float $latMin, float $latMax, float $lonMin, float $lonMax): void
    {
        if ($latMin < -90 || $latMax > 90 || $lonMin < -180 || $lonMax > 180 || $latMin >= $latMax || $lonMin >= $lonMax) {
            return;
        }
        $this->filtroLatMin = (string) $latMin;
        $this->mostrarFotoComposicion = false;
        $this->filtroLatMax = (string) $latMax;
        $this->filtroLonMin = (string) $lonMin;
        $this->filtroLonMax = (string) $lonMax;
        $this->sincronizarCoordenadasSimples();
        $this->pagina = 1;
    }

    public function filtrarCompletos(): void
    {
        $this->mostrarFotoComposicion = false;
        $this->filtroDatosCompletos = '1';
        $this->pagina = 1;
        $this->vista = 'registros';
    }

    public function verGeorreferenciados(): void
    {
        $this->mostrarFotoComposicion = false;
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
        return NormalizacionGeografica::nombresDisponibles(array_values(array_filter(array_column($this->geografiaDisponible, 'provincia'),
            static fn (?string $nombre): bool => CalidadDatoPublico::esTextoValido($nombre) && NormalizacionGeografica::contieneNombre($nombre))));
    }

    #[Computed]
    public function geografiaDisponible(): array
    {
        // Una sola consulta para ambas facetas; el propio criterio geográfico no oculta sus alternativas.
        $datos = array_replace($this->valoresFiltros(), ['filtroProvincia' => '', 'filtroProvincias' => [], 'filtroGeografias' => []]);
        return app(PortalEstadisticas::class)->geografiaParaFiltros(FiltrosBusqueda::desde($datos), $this->nivel, $this->taxon);
    }

    #[Computed]
    public function localidadesDisponibles(): array
    {
        $nombres = [];
        $provincias = array_map(NormalizacionGeografica::normalizar(...), $this->filtrosActuales()->provinciasSeleccionadas());
        foreach ($this->geografiaDisponible as $fila) {
            if ($provincias !== [] && !in_array(NormalizacionGeografica::normalizar($fila['provincia'] ?? ''), $provincias, true)) continue;
            foreach ($fila as $campo => $nombre) {
                if ($campo !== 'provincia' && CalidadDatoPublico::esTextoValido($nombre) && NormalizacionGeografica::contieneNombre($nombre)) $nombres[] = $nombre;
            }
        }
        return NormalizacionGeografica::nombresDisponibles($nombres);
    }

    #[Computed]
    public function filosDisponibles(): array
    {
        return app(EloquentProveedorEspecimenesParaArbol::class)->filosPublicosDisponibles();
    }

    private function filtrosAnalisis(FiltrosBusqueda $filtros): array
    {
        return array_filter([
            'nivel' => $this->nivel,
            'taxon_navegado' => $this->taxon,
            'codigo' => $this->filtroCatalogo,
            'preparaciones' => $this->filtroPreparaciones,
            'taxon' => $this->filtroTaxon,
            'taxon_id' => $filtros->taxonId,
            'provincia' => $this->filtroProvincia,
            'pais' => $this->filtroPais,
            'geografias' => $this->filtroGeografias,
            'filo' => $this->filtroFiloId,
            'filos' => $this->filtroFilos,
            'provincias' => $this->filtroProvincias,
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
            'disposicion' => $this->filtroDisposicion,
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
        $this->filtroTaxonId = '';
        $this->mostrarFotoComposicion = false;
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
        $this->mostrarFotoComposicion = false;
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
        if (array_key_exists('filtroLatitud', $datos)) $this->filtroLatMin = $this->filtroLatMax = (string) $datos['filtroLatitud'];
        if (array_key_exists('filtroLongitud', $datos)) $this->filtroLonMin = $this->filtroLonMax = (string) $datos['filtroLongitud'];
        $this->sincronizarCoordenadasSimples();
        $this->filtroElevDesde = (string) ($datos['filtroElevDesde'] ?? '');
        $this->filtroElevHasta = (string) ($datos['filtroElevHasta'] ?? '');
        $this->filtroBiomas = (array) ($datos['filtroBiomas'] ?? []);
        $this->filtroHabitat = (string) ($datos['filtroHabitat'] ?? '');
        $this->filtroTipo = (string) ($datos['filtroTipo'] ?? '');
        $this->filtroDisposicion = (string) ($datos['filtroDisposicion'] ?? '');
        $this->filtroCasta = (string) ($datos['filtroCasta'] ?? '');
        $this->filtroEstadio = (string) ($datos['filtroEstadio'] ?? '');
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
    }

    public function limpiarFiltros(): void
    {
        $this->mostrarFotoComposicion = false;
        $this->resetValidation();
        $this->avisoFiltrosDependientes = $this->avisoSeleccionUrl = '';
        $this->nivel = '';
        $this->taxon = '';
        $this->explorar = '';
        $this->pagina = 1;
        $this->filtroCatalogo = '';
        $this->filtroPreparaciones = [];
        $this->filtroTaxon = '';
        $this->filtroTaxonId = '';
        $this->filtroGeografias = [];
        $this->filtroColector = '';
        $this->filtroFechaDesde = '';
        $this->filtroFechaHasta = '';
        $this->filtroMetodos = [];
        $this->filtroLatMin = '';
        $this->filtroLatMax = '';
        $this->filtroLonMin = '';
        $this->filtroLonMax = '';
        $this->filtroLatitud = '';
        $this->filtroLongitud = '';
        $this->filtroElevDesde = '';
        $this->filtroElevHasta = '';
        $this->filtroBiomas = [];
        $this->filtroHabitat = '';
        $this->filtroTipo = '';
        $this->filtroDisposicion = '';
        $this->filtroCasta = '';
        $this->filtroEstadio = '';
        $this->filtroProvincia = '';
        $this->filtroPais = '';
        $this->filtroFiloId = '';
        $this->filtroFilos = [];
        $this->filtroProvincias = [];
        $this->filtroMes = '';
        $this->filtroIdentificacion = '';
        $this->filtroSoloUbicacion = '';
        $this->filtroDatosCompletos = '';
        unset($this->geografiaDisponible, $this->provinciasDisponibles, $this->localidadesDisponibles);
        $this->borradorFiltros = $this->valoresBorrador();
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
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
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-HubDigital-Export-Profile' => PerfilExportacionPublica::IDENTIFICADOR],
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
            fputcsv($salida, PerfilExportacionPublica::ENCABEZADOS_CSV, ';', '"', '');
            $celda = static function (mixed $valor): string {
                $texto = (string) ($valor ?? '');

                return preg_match('/^[=+\-@\t\r]/u', $texto) ? "'".$texto : $texto;
            };
            foreach ($repositorio->cursorParaCsv($filtros, $nivel, $taxon) as $fila) {
                $localidad = (bool) $fila->locality_name_visible;
                $coordenadas = (bool) $fila->decimal_latitude_visible && (bool) $fila->decimal_longitude_visible;
                $localidadInec = LocalidadInecPublica::desde($fila->localidad_inec, $fila->referencia_inec);
                $valores = array_map($celda, [
                    $fila->occurrence_id_visible ? ($fila->occurrence_id ?: $fila->codigo_catalogo) : null,
                    $fila->scientific_name_visible ? $fila->nombre_cientifico : null,
                    $fila->event_date_visible ? $fila->fecha_colecta : null,
                    $localidad ? $fila->localidad_verbatim : null,
                    $localidad ? $localidadInec->nombre : null,
                    $localidad ? $fila->codigo_inec : null,
                    $fila->state_province_visible ? $fila->state_province : null,
                    null,
                    null,
                    $coordenadas ? $fila->lat_lon_max_error : null,
                    $fila->type_status_visible ? $fila->type_status : null,
                    $localidad ? $localidadInec->referencia : null,
                    $fila->type_status_visible ? $fila->disposition : null,
                    PerfilExportacionPublica::IDENTIFICADOR,
                    $localidad ? \Modules\CatalogoPublico\Infrastructure\LocalidadPublica::desdeFila($fila) : null,
                ]);
                $latitud = $coordenadas ? NumeroExportacion::decimal($fila->decimal_latitude, -90, 90) : null;
                $longitud = $coordenadas ? NumeroExportacion::decimal($fila->decimal_longitude, -180, 180) : null;
                // Un par incompleto o inválido no se presenta como coordenada pública.
                if ($latitud !== null && $longitud !== null) {
                    $valores[7] = $latitud;
                    $valores[8] = $longitud;
                }
                fputcsv($salida, $valores, ';', '"', '');
            }
            fclose($salida);
        }, 'registros-catalogo.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-HubDigital-Export-Profile' => PerfilExportacionPublica::IDENTIFICADOR]);
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
            'filtroTaxonId' => $this->filtroTaxonId,
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
            'filtroDisposicion' => $this->filtroDisposicion,
            'filtroCasta' => $this->filtroCasta,
            'filtroEstadio' => $this->filtroEstadio,
            'filtroProvincia' => $this->filtroProvincia,
            'filtroPais' => $this->filtroPais,
            'filtroFiloId' => $this->filtroFiloId,
            'filtroFilos' => $this->filtroFilos,
            'filtroProvincias' => $this->filtroProvincias,
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
        $datosMapa = null;
        $errorMapa = false;
        if ($this->vista === 'mapa') {
            try {
                $datosMapa = app(PortalEstadisticas::class)->datosParaVista($this->filtrosAnalisis($filtros), ! $filtros->estaVacio() || $this->taxon !== '');
            } catch (ConsultaMapaNoDisponible $error) {
                report($error);
                $errorMapa = true;
            }
        }

        if ($this->vista === 'mapa') {
            return view('catalogopublico::livewire.portal-catalogo', [
                'datosMapa' => $datosMapa,
                'errorMapa' => $errorMapa,
                'claveFiltrosMapa' => sha1(json_encode([$filtros, $datosMapa['mapa'] ?? [], $datosMapa['filos'] ?? []])),
                'provinciasDisponibles' => $this->provinciasDisponibles,
                'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles,
                'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles,
                'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
            ]);
        }

        if ($this->vista === 'registros') {
            $pagina = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica($filtros, $this->pagina, $this->nivel, $this->taxon, $this->registrosPorPagina);
            $this->pagina = $pagina['pagina'];
            $registrosVista = $this->cargarDetallesPorEspecimenIds($pagina['ids'], $proveedor, $repoDivulgable);
            $imagenesRegistrosVista = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registrosVista, 'occurrence_id'))));
            return view('catalogopublico::livewire.portal-catalogo', [
                'datosMapa' => null,
                'provinciasDisponibles' => $this->provinciasDisponibles,
                'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles,
                'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles,
                'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
                'nivelActual' => $this->nivel, 'taxonActual' => $this->taxon,
                'registrosVista' => $registrosVista, 'imagenesRegistrosVista' => $imagenesRegistrosVista,
                'totalRegistrosVista' => $pagina['total'], 'paginaActual' => $pagina['pagina'], 'ultimaPagina' => $pagina['ultima'],
            ]);
        }

        if ($this->vista === 'tarjetas' && $this->nivel === '' && $this->explorar === '') {
            $resumenRaiz = app(EloquentProveedorEspecimenesParaArbol::class)->resumenRaiz($filtros);
            $totalTarjetas = count($resumenRaiz['hijos']);
            $ultimaPagina = max(1, (int) ceil($totalTarjetas / EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA));
            $paginaActual = min(max(1, $this->pagina), $ultimaPagina);
            $this->pagina = $paginaActual;
            $resumenRaiz['hijos'] = array_slice($resumenRaiz['hijos'], ($paginaActual - 1) * EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA, EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA);
            return view('catalogopublico::livewire.portal-catalogo', $resumenRaiz + [
                'totalTarjetas' => $totalTarjetas, 'paginaActual' => $paginaActual, 'ultimaPagina' => $ultimaPagina, 'totalEspecimenes' => 0,
                'provinciasDisponibles' => $this->provinciasDisponibles, 'filosDisponibles' => $this->filosDisponibles,
                'preparacionesDisponibles' => $this->preparacionesDisponibles, 'metodosRecoleccionDisponibles' => $this->metodosRecoleccionDisponibles,
                'biomasDisponibles' => $this->biomasDisponibles, 'hayFiltrosActivos' => ! $filtros->estaVacio() || $this->taxon !== '',
                'nivelActual' => '', 'taxonActual' => '', 'nivelExplorar' => '', 'ruta' => [],
                'nivelesNavegacion' => self::NIVEL_ETIQUETA, 'etiquetasDescendientes' => self::DESCENDANT_LABELS, 'etiquetas' => self::NIVEL_ETIQUETA,
                'nivelesPluralNavegacion' => self::NIVEL_PLURAL,
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
        $ultimaPaginaHermanos = max(1, (int) ceil($totalHermanos / EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA));
        $paginaHermanosActual = min(max(1, $this->paginaHermanos), $ultimaPaginaHermanos);
        $hermanos = array_slice($hermanos, ($paginaHermanosActual - 1) * EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA, EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA);
        $taxonesExplorados = $this->explorar !== '' ? $this->resolverTaxonesParaExplorar($output, $this->explorar, $resumen['rutas']) : [];
        $especimenes = $registrosVista = [];
        $totalRegistrosVista = $totalEspecimenes = $conteos[$this->nivel.':'.$this->taxon] ?? $totalGlobal;
        $totalTarjetas = $this->nivel === 'species' ? $totalEspecimenes : ($this->explorar !== '' ? count($taxonesExplorados) : count($hijos) + count($especiesActuales));
        $ultimaPagina = max(1, (int) ceil($totalTarjetas / EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA));
        $paginaActual = min(max(1, $this->pagina), $ultimaPagina);
        if ($this->nivel === 'species' && $this->vista === 'tarjetas') {
            $paginaEspecie = $repositorio->paginaPublica($filtros, $this->pagina, $this->nivel, $this->taxon);
            $especimenes = $this->cargarDetallesPorEspecimenIds($paginaEspecie['ids'], $proveedor, $repoDivulgable);
            $totalRegistrosVista = $totalEspecimenes = $totalTarjetas = $paginaEspecie['total'];
            $paginaActual = $paginaEspecie['pagina'];
            $ultimaPagina = $paginaEspecie['ultima'];
        } elseif ($this->explorar !== '') {
            $taxonesExplorados = array_slice($taxonesExplorados, ($paginaActual - 1) * EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA, EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA);
        } else {
            $tarjetas = array_merge(array_map(static fn (array $n): array => ['tipo' => 'nodo', 'nodo' => $n], $hijos), array_map(static fn (array $n): array => ['tipo' => 'especie', 'nodo' => $n], $especiesActuales));
            $tarjetas = array_slice($tarjetas, ($paginaActual - 1) * EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA, EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA);
            $hijos = array_values(array_map(static fn (array $n): array => $n['nodo'], array_filter($tarjetas, static fn (array $n): bool => $n['tipo'] === 'nodo')));
            $especiesActuales = array_values(array_map(static fn (array $n): array => $n['nodo'], array_filter($tarjetas, static fn (array $n): bool => $n['tipo'] === 'especie')));
        }
        $puntosEspecie = $this->nivel === 'species'
            ? app(PortalEstadisticas::class)->puntosParaMapa($this->filtrosAnalisis($filtros)) : [];
        $idTaxonActual = $ruta === [] ? '' : ($ruta[array_key_last($ruta)]['id'] ?? '');
        $claveMapaEspecie = sha1(json_encode([$idTaxonActual, $filtros, $puntosEspecie]));
        $descendientes = $resumen['descendientes'];
        $this->pagina = $paginaActual;

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
            'filtroDisposicion' => $this->filtroDisposicion,
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
            'curatoriales_total' => $resumen['curatoriales_total'] ?? 0,
            'curatoriales' => $resumen['curatoriales'] ?? [],
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
            ->where('i.disco', 'r2')
            ->join('taxonomia.especimenes as e', 'e.occurrence_id', '=', 'i.occurrence_id')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'e.id')
            ->where('ed.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'ed'))->where('ed.scientific_name_visible', true)
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
            ->where('disco', 'r2')
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
                    'taxon_en_revision' => $ver(fn (EspecimenDivulgable $d) => $d->scientificNameVisible()) && ($dto->taxonomiaEnRevision || ! CalidadDatoPublico::esTextoValido($dto->scientificName)),
                    'individual_count' => $g($ver(fn (EspecimenDivulgable $d) => $d->individualCountVisible()), $dto->individualCount),
                    'type_status' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()), $dto->typeStatus),
                    'type_status_visible' => $ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()),
                    'disposition' => $g($ver(fn (EspecimenDivulgable $d) => $d->typeStatusVisible()), $dto->disposition),
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
