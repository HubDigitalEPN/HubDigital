<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen\ActualizarEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes\AplicarEdicionMasivaEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AplicarEdicionMasivaEspecimenes\AplicarEdicionMasivaEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesOutput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva\DeshacerEdicionMasivaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DeshacerEdicionMasiva\DeshacerEdicionMasivaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas\ListarEdicionesMasivasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas\ListarEdicionesMasivasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes\ListarEntidadesDepositantesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones\ListarTaxonesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr\ObtenerCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr\ObtenerCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen\ObtenerFichaEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes\ReemplazarTextoEnEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReemplazarTextoEnEspecimenes\ReemplazarTextoEnEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen\RegistrarEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ClasificadorMotivoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClaseProblemaRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\GeneraSvgQr;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Hoja de inventario del catálogo de especímenes.
 *
 * Se comporta como una hoja de cálculo: al abrirla muestra el catálogo completo
 * paginado, y los filtros lo van recortando. Las filas NO se guardan como estado
 * del componente — se consultan en `render()` en cada ciclo — porque con 200
 * filas × ~85 columnas el payload de Livewire se volvía de megabytes. El precio
 * es una consulta por interacción, que a cambio deja la tabla siempre fresca
 * tras registrar, editar o confirmar sin recargas manuales.
 *
 * Matiz: marcar casillas NO va al servidor — la selección vive en el navegador —
 * así que mientras el curador solo marca filas la tabla no se refresca. Vuelve a
 * refrescarse en cuanto pagina, ordena, filtra o guarda algo.
 */
#[Layout('layouts.app', params: ['title' => 'Especímenes'])]
final class EspecimenIndex extends Component
{
    use GeneraSvgQr;
    use TraduceErroresPersistencia;

    private const CLAVE_SESION_COLUMNAS = 'inventario.especimenes.columnas';

    /** Tamaños de página ofrecidos en el selector. */
    private const TAMANOS_PAGINA = [25, 50, 100, 200];

    /**
     * Reglas SOLO de los filtros. `buscar()` valida contra esta lista y no con
     * `validate()` a secas: eso último corría también las reglas de los modales
     * de alta/edición (vacíos mientras están cerrados), la validación fallaba
     * siempre y el botón "Buscar" no hacía nada.
     *
     * @var array<string, string>
     */
    private const REGLAS_FILTROS = [
        'q' => 'nullable|string|max:200',
        'fTaxon' => 'nullable|string|max:120',
        'fFamilia' => 'nullable|string|max:120',
        'fCodigoCatalogo' => 'nullable|string|max:120',
        'fOccurrenceId' => 'nullable|string|max:120',
        'fCatalogNumber' => 'nullable|string|max:120',
        'fLocalidad' => 'nullable|string|max:200',
        'fColector' => 'nullable|string|max:200',
        'fFechaDesde' => 'nullable|date_format:Y-m-d',
        'fFechaHasta' => 'nullable|date_format:Y-m-d',
        'fEstado' => 'nullable|string|in:disponible,en_prestamo',
        'fEstadoRevision' => 'nullable|string|in:pendiente,confirmada,descartada',
        'fMotivoRevision' => 'nullable|string|max:200',
    ];

    /** Campos cuyo cambio invalida la página actual y obliga a volver a la 1. */
    private const CAMPOS_FILTRO = [
        'q', 'fTaxon', 'fFamilia', 'fCodigoCatalogo', 'fOccurrenceId', 'fCatalogNumber',
        'fLocalidad', 'fColector', 'fFechaDesde', 'fFechaHasta', 'fEstado',
        'fEstadoRevision', 'fMotivoRevision', 'fParaRevision', 'perPage',
    ];

    // ── Búsqueda y paginación ─────────────────────────────────────────────────

    /** Búsqueda rápida de una sola caja (códigos, taxón, colector, localidad). */
    public string $q = '';

    public int $page = 1;

    public int $perPage = 50;

    public string $ordenarPor = 'codigoCatalogo';

    public string $ordenDireccion = 'asc';

    public bool $filtrosAbiertos = false;

    // Filtros combinables (todos opcionales, AND lógico)
    public string $fTaxon = '';

    public string $fFamilia = '';

    public string $fCodigoCatalogo = '';

    public string $fOccurrenceId = '';

    public string $fCatalogNumber = '';

    public string $fLocalidad = '';

    public string $fColector = '';

    public string $fFechaDesde = '';

    public string $fFechaHasta = '';

    public string $fEstado = '';

    public string $fEstadoRevision = '';

    public string $fMotivoRevision = '';

    public bool $fParaRevision = false;

    // ── Configuración de columnas ─────────────────────────────────────────────
    // Vive en el servidor (no solo en Alpine) para poder render la tabla como un
    // <table> real con las celdas ya en orden. Se persiste en sesión y el cliente
    // la reconcilia con su localStorage al montar (ver `aplicarColumnas`).

    /** @var string[] */
    public array $ordenColumnas = [];

    /** @var string[] */
    public array $columnasVisibles = [];

    public bool $panelColumnasAbierto = false;

    // ── Selección y edición masiva ────────────────────────────────────────────

    /**
     * Ids marcados, como LISTA plana.
     *
     * Solo se rellena al abrir el panel de edición masiva: mientras el curador
     * navega, la selección vive en el navegador y no viaja. Es una lista y no un
     * mapa `id => bool` porque Livewire descompone los cambios de un mapa en
     * rutas por clave y trata como índice numérico cualquier UUID que empiece
     * por dígito, perdiendo esas entradas por el camino.
     *
     * @var string[]
     */
    public array $seleccionados = [];

    public bool $panelMasivoAbierto = false;

    /** 'fijar' | 'reemplazar' */
    public string $masivaModo = 'fijar';

    public string $masivaCampo = 'colector';

    public string $masivaValor = '';

    /**
     * Distingue "vaciar el campo" de "dejarlo como está". Un input vacío es
     * ambiguo, y sin este interruptor el curador borraría columnas creyendo que
     * las omite.
     */
    public bool $masivaVaciar = false;

    public string $masivaBuscar = '';

    public string $masivaReemplazo = '';

    public bool $masivaDistinguirMayusculas = true;

    public bool $masivaPalabraCompleta = false;

    /** @var list<array{codigoCatalogo: string, previo: ?string, nuevo: ?string}> */
    public array $masivaPreview = [];

    public string $masivaResumen = '';

    /** Solo tras previsualizar se habilita el botón de aplicar. */
    public bool $masivaPreviewListo = false;

    // ── Ficha completa ────────────────────────────────────────────────────────

    public bool $fichaAbierta = false;

    public string $fichaId = '';

    /** Todas las columnas del espécimen, tal como las aplana el mapeador. */
    public array $fichaDatos = [];

    /** @var array<string, string> Valores en edición de los campos editables. */
    public array $fichaBorrador = [];

    // ── Historial de ediciones ────────────────────────────────────────────────

    public bool $historialAbierto = false;

    public array $historial = [];

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

    // ── Código QR ───────────────────────────────────────────────────────────────

    /** URL de resolución codificada en el QR mostrado (null = panel cerrado). */
    public ?string $qrUrl = null;

    public ?string $qrEspecimenCodigo = null;

    /**
     * SVG del QR generado en el servidor (BaconQrCode). Se renderiza inline: no
     * depende de ninguna librería JS externa ni de un CDN, así el QR se dibuja
     * aunque el laboratorio esté sin internet.
     */
    public ?string $qrSvg = null;

    // ── Feedback ──────────────────────────────────────────────────────────────

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    // ── Geocodificación por coordenadas ───────────────────────────────────────

    /** Texto legible de la ubicación detectada por coordenadas (para el modal). */
    public ?string $ubicacionDetectada = null;

    /** Aviso de la geocodificación (sin coordenadas o sin resultado). */
    public ?string $geoMensaje = null;

    public function mount(): void
    {
        $guardado = session(self::CLAVE_SESION_COLUMNAS);
        if (is_array($guardado) && isset($guardado['orden'], $guardado['visibles'])) {
            $this->normalizarColumnas($guardado['orden'], $guardado['visibles']);
        } else {
            $this->restaurarColumnas();
        }

        $this->fechaColecta = date('Y-m-d');
    }

    // ── Columnas ──────────────────────────────────────────────────────────────

    /**
     * Reconciliación con el localStorage del navegador. El cliente llama a esto
     * al montar solo si su preferencia guardada difiere de la que ya vino
     * renderizada, así una vez sincronizada la sesión no hay viaje extra.
     *
     * @param  string[]  $orden
     * @param  string[]  $visibles
     */
    public function aplicarColumnas(array $orden, array $visibles): void
    {
        $this->normalizarColumnas($orden, $visibles);
        $this->persistirColumnas();
    }

    public function toggleColumna(string $clave): void
    {
        if (! in_array($clave, $this->ordenColumnas, true)) {
            return;
        }

        $this->columnasVisibles = in_array($clave, $this->columnasVisibles, true)
            ? array_values(array_diff($this->columnasVisibles, [$clave]))
            : [...$this->columnasVisibles, $clave];

        $this->persistirColumnas();
    }

    /** Mueve una columna una posición arriba (-1) o abajo (+1) en el orden. */
    public function moverColumna(string $clave, int $delta): void
    {
        $i = array_search($clave, $this->ordenColumnas, true);
        $destino = $i === false ? -1 : $i + $delta;

        if ($i === false || $destino < 0 || $destino >= count($this->ordenColumnas)) {
            return;
        }

        [$this->ordenColumnas[$i], $this->ordenColumnas[$destino]]
            = [$this->ordenColumnas[$destino], $this->ordenColumnas[$i]];

        $this->persistirColumnas();
    }

    public function restaurarColumnas(): void
    {
        $this->ordenColumnas = array_column(RegistroColumnasEspecimen::todas(), 'clave');
        $this->columnasVisibles = RegistroColumnasEspecimen::clavesVisiblesPorDefecto();
        $this->persistirColumnas();
    }

    public function mostrarTodasColumnas(): void
    {
        $this->columnasVisibles = $this->ordenColumnas;
        $this->persistirColumnas();
    }

    public function soloColumnasCriticas(ResolverPrioridadColumnas $resolver): void
    {
        $prioridades = [];
        foreach ($resolver->aplicar('especimenes', RegistroColumnasEspecimen::todas()) as $col) {
            $prioridades[$col['clave']] = $col['prioridad'];
        }

        $this->columnasVisibles = array_values(array_filter(
            $this->ordenColumnas,
            fn (string $c) => ($prioridades[$c] ?? '') === RegistroColumnasEspecimen::PRIORIDAD_CRITICA,
        ));

        $this->persistirColumnas();
    }

    /**
     * Deja `ordenColumnas`/`columnasVisibles` en un estado siempre coherente con
     * el registro de columnas: descarta claves desconocidas (preferencias viejas
     * tras renombrar una columna) y añade al final las que hayan aparecido
     * después, para que una columna nueva nunca quede inalcanzable.
     *
     * @param  mixed[]  $orden
     * @param  mixed[]  $visibles
     */
    private function normalizarColumnas(array $orden, array $visibles): void
    {
        $conocidas = array_column(RegistroColumnasEspecimen::todas(), 'clave');

        $ordenado = array_values(array_intersect(
            array_map(strval(...), $orden),
            $conocidas,
        ));
        foreach ($conocidas as $clave) {
            if (! in_array($clave, $ordenado, true)) {
                $ordenado[] = $clave;
            }
        }

        $this->ordenColumnas = $ordenado;
        $this->columnasVisibles = array_values(array_intersect(
            array_map(strval(...), $visibles),
            $ordenado,
        ));
    }

    private function persistirColumnas(): void
    {
        session([self::CLAVE_SESION_COLUMNAS => [
            'orden' => $this->ordenColumnas,
            'visibles' => $this->columnasVisibles,
        ]]);

        // El navegador las recuerda entre sesiones (localStorage); la sesión de
        // servidor solo evita repetir la sincronización en cada carga.
        $this->dispatch(
            'columnas-actualizadas',
            orden: $this->ordenColumnas,
            visibles: $this->columnasVisibles,
        );
    }

    // ── Búsqueda / filtros ────────────────────────────────────────────────────

    /**
     * Cualquier cambio de filtro invalida la página en curso: si el curador está
     * en la página 40 y acota la búsqueda, esa página puede ya no existir.
     */
    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS_FILTRO, true)) {
            $this->page = 1;
        }

        if ($campo === 'perPage' && ! in_array($this->perPage, self::TAMANOS_PAGINA, true)) {
            $this->perPage = 50;
        }
    }

    public function buscar(): void
    {
        $this->validate(self::REGLAS_FILTROS);
        $this->page = 1;
    }

    /** Alterna el orden por una columna (asc → desc → asc). */
    public function ordenarPorColumna(string $clave): void
    {
        if (! in_array($clave, RegistroColumnasEspecimen::clavesOrdenables(), true)) {
            return;
        }

        if ($this->ordenarPor === $clave) {
            $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $clave;
            $this->ordenDireccion = 'asc';
        }

        $this->page = 1;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function prevPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function goToPage(int $p): void
    {
        $this->page = max(1, $p);
    }

    public function preset(string $nombre): void
    {
        $this->reset(
            'q', 'fTaxon', 'fFamilia', 'fCodigoCatalogo', 'fOccurrenceId', 'fCatalogNumber',
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

        $this->page = 1;
        $this->resetValidation();
    }

    /** Quita un filtro concreto desde su chip en la barra de filtros activos. */
    public function quitarFiltro(string $campo): void
    {
        if (! in_array($campo, self::CAMPOS_FILTRO, true) || $campo === 'perPage') {
            return;
        }

        $this->reset($campo);
        $this->page = 1;
        $this->resetValidation();
    }

    public function limpiar(): void
    {
        $this->reset(
            'errorMessage', 'successMessage', 'page',
            'q', 'fTaxon', 'fFamilia', 'fCodigoCatalogo', 'fOccurrenceId', 'fCatalogNumber',
            'fLocalidad', 'fColector', 'fFechaDesde', 'fFechaHasta',
            'fEstado', 'fEstadoRevision', 'fMotivoRevision', 'fParaRevision',
        );
        $this->resetValidation();
    }

    // ── Catálogos de los formularios ──────────────────────────────────────────

    /**
     * Los desplegables de taxón y entidad se cargan al abrir un modal, no al
     * montar. El de taxones ronda las 4.000 opciones: tenerlo siempre montado
     * añadía ~1 MB de HTML a cada render de la hoja y viajaba además en el
     * estado del componente en cada interacción (búsqueda, orden, paginación).
     */
    private function cargarCatalogosFormulario(bool $conTaxones): void
    {
        $this->cargarProtegido(function () use ($conTaxones) {
            if ($conTaxones && $this->taxones === []) {
                $this->taxones = array_map(
                    fn ($t) => ['id' => $t->id, 'label' => "{$t->nombreCientifico} ({$t->rango})"],
                    app(ListarTaxonesHandler::class)->handle()->items,
                );
            }

            if ($this->entidades === []) {
                $this->entidades = array_map(
                    fn ($e) => ['id' => $e->id, 'label' => $e->nombre],
                    app(ListarEntidadesDepositantesHandler::class)->handle()->items,
                );
            }
        });
    }

    /** Al cerrar el modal se sueltan los catálogos para que no sigan en el payload. */
    public function updatedShowModal(mixed $valor): void
    {
        if (! $valor) {
            $this->reset('taxones', 'entidades');
        }
    }

    public function updatedShowEditModal(mixed $valor): void
    {
        if (! $valor) {
            $this->reset('entidades');
        }
    }

    // ── Registro ──────────────────────────────────────────────────────────────

    public function abrirModal(): void
    {
        $this->cargarCatalogosFormulario(conTaxones: true);

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
            'ubicacionDetectada',
            'geoMensaje',
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
            // El cierre programático no dispara `updatedShowModal`, así que aquí
            // se sueltan los catálogos a mano.
            $this->reset('taxones', 'entidades');
            $this->successMessage = "Especímen '{$this->codigoCatalogo}' registrado correctamente.";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // ── Edición ───────────────────────────────────────────────────────────────

    /**
     * Relee la ficha del espécimen en vez de tirar de la fila renderizada: las
     * filas ya no viven en el estado del componente, y así el formulario siempre
     * parte del dato guardado y no de una copia que pudo quedar obsoleta.
     */
    public function abrirEditModal(ObtenerFichaEspecimenHandler $handler, string $id): void
    {
        $ficha = $handler->handle(new ObtenerFichaEspecimenInput(especimenId: $id));

        if (! $ficha->encontrado) {
            $this->errorMessage = 'No se encontró el espécimen solicitado.';

            return;
        }

        $this->cargarCatalogosFormulario(conTaxones: false);

        $e = $ficha->ficha;

        $this->editandoId = $id;
        $this->editLocalidad = (string) ($e['localidad'] ?? '');
        $this->editFechaColecta = (string) ($e['fechaColecta'] ?? '');
        $this->editColector = (string) ($e['colector'] ?? '');
        $this->editEntidadDepositanteId = (string) ($e['entidadDepositanteId'] ?? '');
        $this->editPreparations = (string) ($e['preparations'] ?? '');
        $this->editDisposition = (string) ($e['disposition'] ?? '');
        $this->editOccurrenceStatus = (string) ($e['occurrenceStatus'] ?? '');
        $this->editSpecimenNotes = (string) ($e['specimenNotes'] ?? '');
        $this->editCountry = (string) ($e['country'] ?? '');
        $this->editStateProvince = (string) ($e['stateProvince'] ?? '');
        $this->editMunicipality = (string) ($e['municipality'] ?? '');
        $this->editLocalityName = (string) ($e['localityName'] ?? '');
        $this->editDecimalLatitude = isset($e['decimalLatitude']) ? (string) $e['decimalLatitude'] : '';
        $this->editDecimalLongitude = isset($e['decimalLongitude']) ? (string) $e['decimalLongitude'] : '';
        $this->editGeodeticDatum = (string) ($e['geodeticDatum'] ?? '');
        $this->editElevationMinM = isset($e['elevationMinM']) ? (string) $e['elevationMinM'] : '';
        $this->editBiome = (string) ($e['biome'] ?? '');
        $this->editHabitat = (string) ($e['habitat'] ?? '');
        $this->ubicacionDetectada = null;
        $this->geoMensaje = null;
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
            $this->reset('entidades');
            $this->successMessage = 'Especímen actualizado correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // ── Selección y edición masiva ────────────────────────────────────────────

    public function contarSeleccionados(): int
    {
        return count($this->seleccionados);
    }

    /** @return string[] */
    private function idsSeleccionados(): array
    {
        return $this->seleccionados;
    }

    public function limpiarSeleccion(): void
    {
        $this->reset('seleccionados');
    }

    /**
     * Recibe del navegador los ids marcados y abre el panel.
     *
     * Los ids llegan como argumento y no como propiedad sincronizada: es la
     * única vía en la que Livewire los serializa como un array plano, sin
     * interpretar los UUID que empiezan por dígito como índices numéricos.
     *
     * @param  string[]  $ids
     */
    public function abrirPanelMasivo(array $ids = []): void
    {
        $this->seleccionados = array_values(array_unique(array_filter(
            $ids,
            fn ($id) => is_string($id) && EspecimenId::esValido($id),
        )));

        if ($this->contarSeleccionados() === 0) {
            $this->errorMessage = 'Marca al menos un espécimen para editar en bloque.';

            return;
        }

        $this->resetPreviewMasiva();
        $this->errorMessage = null;
        $this->panelMasivoAbierto = true;
    }

    /** Al cambiar de campo o de modo, la vista previa anterior deja de describir lo que va a pasar. */
    public function updatedMasivaCampo(): void
    {
        $this->resetPreviewMasiva();
    }

    public function updatedMasivaModo(): void
    {
        $this->resetPreviewMasiva();
    }

    public function updatedMasivaValor(): void
    {
        $this->resetPreviewMasiva();
    }

    public function updatedMasivaVaciar(): void
    {
        $this->resetPreviewMasiva();
    }

    public function updatedMasivaBuscar(): void
    {
        $this->resetPreviewMasiva();
    }

    public function updatedMasivaReemplazo(): void
    {
        $this->resetPreviewMasiva();
    }

    private function resetPreviewMasiva(): void
    {
        $this->reset('masivaPreview', 'masivaResumen', 'masivaPreviewListo');
    }

    /**
     * Calcula el efecto exacto sin escribir. Es un paso obligatorio antes de
     * aplicar: sobre datos de producción, ver qué filas cambian y de qué valor a
     * cuál es la única forma de detectar a tiempo un campo o un patrón mal
     * elegidos.
     */
    public function previsualizarMasiva(
        AplicarEdicionMasivaEspecimenesHandler $fijar,
        ReemplazarTextoEnEspecimenesHandler $reemplazar,
    ): void {
        $this->ejecutarMasiva($fijar, $reemplazar, simular: true);
    }

    public function aplicarMasiva(
        AplicarEdicionMasivaEspecimenesHandler $fijar,
        ReemplazarTextoEnEspecimenesHandler $reemplazar,
    ): void {
        $this->ejecutarMasiva($fijar, $reemplazar, simular: false);
    }

    private function ejecutarMasiva(
        AplicarEdicionMasivaEspecimenesHandler $fijar,
        ReemplazarTextoEnEspecimenesHandler $reemplazar,
        bool $simular,
    ): void {
        $ids = $this->idsSeleccionados();
        if ($ids === []) {
            $this->errorMessage = 'No hay especímenes seleccionados.';

            return;
        }

        try {
            if ($this->masivaModo === 'reemplazar') {
                $salida = $reemplazar->handle(new ReemplazarTextoEnEspecimenesInput(
                    especimenIds: $ids,
                    campo: $this->masivaCampo,
                    buscar: $this->masivaBuscar,
                    reemplazo: $this->masivaReemplazo,
                    distinguirMayusculas: $this->masivaDistinguirMayusculas,
                    palabraCompleta: $this->masivaPalabraCompleta,
                    simular: $simular,
                    actorId: $this->actorId(),
                    actorNombre: $this->actorNombre(),
                ));
                $cambiados = $salida->cambiados;
                $intactos = $salida->sinCoincidencia;
                $muestra = $salida->muestra;
            } else {
                $salida = $fijar->handle(new AplicarEdicionMasivaEspecimenesInput(
                    especimenIds: $ids,
                    campo: $this->masivaCampo,
                    valor: $this->masivaValor,
                    vaciar: $this->masivaVaciar,
                    simular: $simular,
                    actorId: $this->actorId(),
                    actorNombre: $this->actorNombre(),
                ));
                $cambiados = $salida->cambiados;
                $intactos = $salida->sinCambio;
                $muestra = $salida->muestra;
            }

            $this->errorMessage = null;

            if ($simular) {
                $this->masivaPreview = $muestra;
                $this->masivaPreviewListo = true;
                $this->masivaResumen = $cambiados === 0
                    ? 'Ninguna de las filas seleccionadas cambiaría.'
                    : "Cambiarán {$cambiados} espécimen(es); {$intactos} quedarán igual.";

                return;
            }

            $this->panelMasivoAbierto = false;
            $this->resetPreviewMasiva();
            $this->limpiarSeleccion();
            // El navegador es dueño de la selección: se le avisa para que vacíe
            // las casillas, porque el servidor no puede desmarcarlas por su cuenta.
            $this->dispatch('seleccion-aplicada');
            $this->successMessage = $cambiados === 0
                ? 'No hubo nada que cambiar.'
                : "Actualizados {$cambiados} espécimen(es). Puedes deshacerlo desde el historial.";
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // ── Edición de una celda ──────────────────────────────────────────────────

    /**
     * Guarda una celda editada en la tabla.
     *
     * Solo el GUARDADO viaja al servidor. Abrir el editor y escribir ocurre
     * entero en el navegador: cuando abrirlo era una acción de Livewire, el
     * curador hacía doble clic y esperaba ~2 s a que apareciera el campo, con
     * la sensación de que la tabla se trababa o de que había pinchado en otra
     * celda.
     *
     * El valor PREVIO que va a la bitácora no es el que manda el navegador: lo
     * relee el caso de uso dentro de la transacción, para que una fila que haya
     * cambiado entretanto quede registrada con su valor real y el deshacer siga
     * siendo correcto.
     */
    public function guardarCelda(
        AplicarEdicionMasivaEspecimenesHandler $handler,
        string $id,
        string $clave,
        string $valor,
    ): void {
        $campo = RegistroColumnasEspecimen::campoEditable($clave);
        if ($campo === null) {
            return;
        }

        try {
            $handler->handle(new AplicarEdicionMasivaEspecimenesInput(
                especimenIds: [$id],
                campo: $clave,
                valor: $valor,
                vaciar: trim($valor) === '' && $campo['admiteVacio'],
                tipo: TipoEdicionMasiva::EdicionCelda,
                actorId: $this->actorId(),
                actorNombre: $this->actorNombre(),
            ));

            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // ── Ficha completa ────────────────────────────────────────────────────────

    /**
     * Abre la ficha de un espécimen con TODAS sus columnas.
     *
     * Es la vista de revisión: la tabla enseña las columnas que el curador dejó
     * visibles, pero para decidir sobre un ejemplar concreto hace falta ver todo
     * lo que se sabe de él. Los campos editables se pueden corregir desde aquí
     * mismo, y cada cambio queda en la bitácora igual que una edición masiva.
     */
    public function abrirFicha(ObtenerFichaEspecimenHandler $handler, string $id): void
    {
        $ficha = $handler->handle(new ObtenerFichaEspecimenInput(especimenId: $id));

        if (! $ficha->encontrado) {
            $this->errorMessage = 'No se encontró el espécimen solicitado.';

            return;
        }

        $this->fichaId = $id;
        $this->fichaDatos = $ficha->ficha;
        $this->fichaBorrador = [];
        foreach (RegistroColumnasEspecimen::clavesEditablesEnMasa() as $clave) {
            $valor = $ficha->ficha[$clave] ?? null;
            $this->fichaBorrador[$clave] = match (true) {
                is_bool($valor) => $valor ? 'si' : 'no',
                $valor === null => '',
                default => (string) $valor,
            };
        }

        $this->fichaAbierta = true;
        $this->errorMessage = null;
    }

    public function cerrarFicha(): void
    {
        $this->reset('fichaAbierta', 'fichaId', 'fichaDatos', 'fichaBorrador');
    }

    /**
     * Persiste solo los campos que el curador tocó.
     *
     * Cada campo se guarda como su propia entrada de bitácora, de modo que se
     * pueden deshacer por separado: si se corrigen tres columnas y solo una
     * estaba mal, no hay que revertir las otras dos.
     */
    public function guardarFicha(AplicarEdicionMasivaEspecimenesHandler $handler): void
    {
        if ($this->fichaId === '') {
            return;
        }

        $cambiados = 0;

        try {
            foreach ($this->fichaBorrador as $clave => $valor) {
                $campo = RegistroColumnasEspecimen::campoEditable($clave);
                if ($campo === null) {
                    continue;
                }

                $original = $this->fichaDatos[$clave] ?? null;
                $originalTexto = match (true) {
                    is_bool($original) => $original ? 'si' : 'no',
                    $original === null => '',
                    default => (string) $original,
                };

                if (trim($valor) === trim($originalTexto)) {
                    continue;
                }

                $salida = $handler->handle(new AplicarEdicionMasivaEspecimenesInput(
                    especimenIds: [$this->fichaId],
                    campo: $clave,
                    valor: $valor,
                    vaciar: trim($valor) === '' && $campo['admiteVacio'],
                    tipo: TipoEdicionMasiva::EdicionCelda,
                    actorId: $this->actorId(),
                    actorNombre: $this->actorNombre(),
                ));

                $cambiados += $salida->cambiados;
            }

            $this->errorMessage = null;
            $this->successMessage = $cambiados === 0
                ? 'No hubo cambios que guardar.'
                : "Guardados {$cambiados} campo(s). Puedes deshacerlo desde el historial.";
            $this->cerrarFicha();
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // ── Historial y deshacer ──────────────────────────────────────────────────

    public function abrirHistorial(ListarEdicionesMasivasHandler $handler): void
    {
        $this->historial = $handler->handle(new ListarEdicionesMasivasInput)->items;
        $this->historialAbierto = true;
    }

    public function deshacer(
        DeshacerEdicionMasivaHandler $handler,
        ListarEdicionesMasivasHandler $listar,
        string $edicionId,
    ): void {
        try {
            $salida = $handler->handle(new DeshacerEdicionMasivaInput($edicionId));

            $this->successMessage = $salida->conflictos > 0
                ? "Revertidos {$salida->revertidos} espécimen(es). {$salida->conflictos} no se tocaron porque cambiaron después."
                : "Revertidos {$salida->revertidos} espécimen(es).";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }

        $this->historial = $listar->handle(new ListarEdicionesMasivasInput)->items;
    }

    /**
     * Campos editables con su etiqueta legible, para los desplegables.
     *
     * @return array<string, array{etiqueta: string, tipo: string, admiteVacio: bool}>
     */
    public function camposEditables(): array
    {
        $etiquetas = [];
        foreach (RegistroColumnasEspecimen::todas() as $col) {
            $etiquetas[$col['clave']] = $col['etiqueta'];
        }

        $salida = [];
        foreach (RegistroColumnasEspecimen::camposEditablesEnMasa() as $clave => $campo) {
            $salida[$clave] = [
                'etiqueta' => $etiquetas[$clave] ?? $clave,
                'tipo' => $campo['tipo'],
                'admiteVacio' => $campo['admiteVacio'],
            ];
        }
        asort($salida);

        return $salida;
    }

    private function actorId(): ?string
    {
        return auth()->id() !== null ? (string) auth()->id() : null;
    }

    private function actorNombre(): ?string
    {
        return auth()->user()->name ?? null;
    }

    // ── Problemas de la fila ──────────────────────────────────────────────────

    /**
     * Traduce el `motivo_revision` de una fila en los avisos accionables que se
     * pintan como triángulos.
     *
     * Cada aviso lleva a donde el problema SE ARREGLA, que no siempre es otra
     * pantalla: las coordenadas solo son editables en el modal de esta misma
     * hoja (`ActualizarEspecimenInput` expone decimalLatitude/decimalLongitude,
     * y la bandeja de localidades únicamente las lista), así que ese aviso abre
     * el modal en vez de navegar.
     *
     * Las clases sin pantalla donde corregirlas (`occurrence_id`,
     * `individual_count`, texto libre del curador) van al centro de revisión,
     * que es donde se triangula lo que no tiene arreglo directo. No se las
     * manda a una bandeja que no las resuelve.
     *
     * @return list<array{clase: string, etiqueta: string, abreModal: bool, url: ?string}>
     */
    private function problemasDeFila(ClasificadorMotivoRevision $clasificador, ?string $motivo): array
    {
        return array_map(
            fn (ClaseProblemaRevision $clase) => [
                'clase' => $clase->value,
                'etiqueta' => $clase->etiqueta(),
                'abreModal' => $clase === ClaseProblemaRevision::Coordenadas,
                'url' => match ($clase) {
                    ClaseProblemaRevision::Coordenadas => null,
                    ClaseProblemaRevision::Fecha => route('inventario.taxonomia.fechas.revision'),
                    ClaseProblemaRevision::Taxonomia => route('inventario.taxonomia.taxones.revision'),
                    default => route('inventario.taxonomia.revision'),
                },
            ],
            $clasificador->clasificar($motivo),
        );
    }

    // ── Código QR ─────────────────────────────────────────────────────────────

    /**
     * Muestra el QR del espécimen: reutiliza el existente o lo genera la primera vez.
     * El QR codifica la URL de resolución (token opaco), que abre la ficha al escanear.
     */
    public function mostrarQr(
        string $id,
        ObtenerCodigoQrHandler $obtenerHandler,
        GenerarCodigoQrHandler $generarHandler,
        ObtenerFichaEspecimenHandler $fichaHandler,
    ): void {
        try {
            $existente = $obtenerHandler->handle(new ObtenerCodigoQrInput(especimenId: $id));

            $payload = $existente->existe
                ? $existente->payload
                : $generarHandler->handle(new GenerarCodigoQrInput(especimenId: $id))->payload;

            $ficha = $fichaHandler->handle(new ObtenerFichaEspecimenInput(especimenId: $id));

            $this->qrUrl = route('inventario.qr.resolver', ['payload' => $payload]);
            $this->qrSvg = $this->generarSvgQr($this->qrUrl);
            $this->qrEspecimenCodigo = $ficha->encontrado ? (string) ($ficha->ficha['codigoCatalogo'] ?? $id) : $id;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function cerrarQr(): void
    {
        $this->qrUrl = null;
        $this->qrSvg = null;
        $this->qrEspecimenCodigo = null;
    }

    // ── Geocodificación por coordenadas ───────────────────────────────────────

    /**
     * Geocodificación inversa para el ALTA: con las coordenadas del espécimen
     * rellena país/provincia/cantón y la localidad (poblado más cercano). Solo
     * sobrescribe lo que el servicio resuelve; si no hay resultado, avisa.
     */
    public function detectarUbicacion(GeocodificadorInversoPort $geo): void
    {
        $this->geoMensaje = null;
        $this->ubicacionDetectada = null;

        $lat = is_numeric(trim($this->decimalLatitude)) ? (float) $this->decimalLatitude : null;
        $lon = is_numeric(trim($this->decimalLongitude)) ? (float) $this->decimalLongitude : null;
        if ($lat === null || $lon === null) {
            $this->geoMensaje = 'Ingresa latitud y longitud válidas para detectar la ubicación.';

            return;
        }

        $u = $geo->geocodificar($lat, $lon);
        if ($u === null) {
            $this->geoMensaje = 'No se pudo determinar la ubicación para esas coordenadas.';

            return;
        }

        $this->country = $u->country ?? $this->country;
        $this->stateProvince = $u->stateProvince ?? $this->stateProvince;
        $this->municipality = $u->municipality ?? $this->municipality;
        $this->localityName = $u->poblado ?? $this->localityName;
        $this->ubicacionDetectada = $u->displayName;
    }

    /** Igual que detectarUbicacion pero para el modal de EDICIÓN. */
    public function detectarUbicacionEdicion(GeocodificadorInversoPort $geo): void
    {
        $this->geoMensaje = null;
        $this->ubicacionDetectada = null;

        $lat = is_numeric(trim($this->editDecimalLatitude)) ? (float) $this->editDecimalLatitude : null;
        $lon = is_numeric(trim($this->editDecimalLongitude)) ? (float) $this->editDecimalLongitude : null;
        if ($lat === null || $lon === null) {
            $this->geoMensaje = 'Ingresa latitud y longitud válidas para detectar la ubicación.';

            return;
        }

        $u = $geo->geocodificar($lat, $lon);
        if ($u === null) {
            $this->geoMensaje = 'No se pudo determinar la ubicación para esas coordenadas.';

            return;
        }

        $this->editCountry = $u->country ?? $this->editCountry;
        $this->editStateProvince = $u->stateProvince ?? $this->editStateProvince;
        $this->editMunicipality = $u->municipality ?? $this->editMunicipality;
        $this->editLocalityName = $u->poblado ?? $this->editLocalityName;
        $this->ubicacionDetectada = $u->displayName;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(ResolverPrioridadColumnas $resolver, ClasificadorMotivoRevision $clasificador): View
    {
        $columnas = $resolver->aplicar('especimenes', RegistroColumnasEspecimen::todas());
        $porClave = [];
        foreach ($columnas as $col) {
            $porClave[$col['clave']] = $col;
        }

        // Columnas efectivas de la tabla: en el orden elegido y solo las visibles.
        $columnasTabla = [];
        foreach ($this->ordenColumnas as $clave) {
            if (in_array($clave, $this->columnasVisibles, true) && isset($porClave[$clave])) {
                $columnasTabla[] = $porClave[$clave];
            }
        }

        $output = $this->consultarPagina();
        $offset = ($this->page - 1) * $this->perPage;

        // Cada fila marcada llega ya con sus avisos resueltos: la vista solo los
        // pinta. Un espécimen puede arrastrar varios a la vez (el importador
        // acumula fecha y coordenadas en el mismo motivo), de ahí la lista.
        $filas = array_map(
            fn (array $fila) => $fila + [
                'problemas' => ($fila['estadoRevision'] ?? '') === 'pendiente'
                    ? $this->problemasDeFila($clasificador, $fila['motivoRevision'] ?? null)
                    : [],
            ],
            $output->items,
        );

        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.index', [
            'filas' => $filas,
            'totalPaginas' => max(1, $output->totalPaginas),
            'totalItems' => $output->total,
            'inicio' => $output->total > 0 ? $offset + 1 : 0,
            'fin' => min($offset + count($output->items), $output->total),
            'offsetFila' => $offset,
            'columnasRegistro' => $columnas,
            'columnasTabla' => $columnasTabla,
            'clavesOrdenables' => RegistroColumnasEspecimen::clavesOrdenables(),
            'clavesEditables' => RegistroColumnasEspecimen::clavesEditablesEnMasa(),
            'tamanosPagina' => self::TAMANOS_PAGINA,
            'filtrosActivos' => $this->filtrosActivos(),
        ]);
    }

    /**
     * Consulta la página actual y, si quedó fuera de rango (el conjunto encogió
     * al filtrar), reencaja la página y vuelve a consultar: solo reencajar
     * dejaría la tabla vacía aunque el total diga que hay filas.
     */
    private function consultarPagina(): BuscarEspecimenesOutput
    {
        $handler = app(BuscarEspecimenesHandler::class);

        try {
            $output = $handler->handle($this->construirInput($this->page));

            if ($this->page > $output->totalPaginas) {
                $this->page = max(1, $output->totalPaginas);
                $output = $handler->handle($this->construirInput($this->page));
            }

            return $output;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);

            return new BuscarEspecimenesOutput(items: [], total: 0, page: 1, perPage: $this->perPage);
        }
    }

    private function construirInput(int $page): BuscarEspecimenesInput
    {
        return new BuscarEspecimenesInput(
            busquedaGlobal: $this->nullableString($this->q),
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
            page: $page,
            perPage: in_array($this->perPage, self::TAMANOS_PAGINA, true) ? $this->perPage : 50,
            // Sin filtros = catálogo completo paginado: esta pantalla es la hoja
            // de inventario, no un buscador que arranca en blanco.
            permitirSinFiltros: true,
            ordenarPor: $this->ordenarPor,
            ordenDireccion: $this->ordenDireccion,
        );
    }

    /**
     * Filtros con valor, para pintarlos como chips descartables.
     *
     * @return list<array{campo: string, etiqueta: string, valor: string}>
     */
    private function filtrosActivos(): array
    {
        $etiquetas = [
            'q' => 'Búsqueda',
            'fTaxon' => 'Taxón',
            'fFamilia' => 'Familia',
            'fCodigoCatalogo' => 'Código catálogo',
            'fOccurrenceId' => 'occurrenceID',
            'fCatalogNumber' => 'catalogNumber',
            'fLocalidad' => 'Localidad',
            'fColector' => 'Colector',
            'fFechaDesde' => 'Desde',
            'fFechaHasta' => 'Hasta',
            'fEstado' => 'Estado físico',
            'fEstadoRevision' => 'Estado revisión',
            'fMotivoRevision' => 'Motivo revisión',
        ];

        $activos = [];
        foreach ($etiquetas as $campo => $etiqueta) {
            $valor = trim((string) $this->{$campo});
            if ($valor !== '') {
                $activos[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'valor' => $valor];
            }
        }

        if ($this->fParaRevision) {
            $activos[] = ['campo' => 'fParaRevision', 'etiqueta' => 'Solo', 'valor' => 'marcados para revisión'];
        }

        return $activos;
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
