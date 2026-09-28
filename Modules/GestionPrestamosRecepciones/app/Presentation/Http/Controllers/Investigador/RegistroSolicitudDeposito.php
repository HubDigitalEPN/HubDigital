<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use App\Support\CatalogoTerritorialEcuador;
use App\Support\CatalogoLocalidadesEcuador;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidacionPreviaDocumentoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DocumentoDepositoRechazado;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionTaxonomicaPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica\AceptarSugerenciaTaxonomicaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica\AceptarSugerenciaTaxonomicaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CambiarJustificacionTaxonomica\CambiarJustificacionTaxonomicaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CambiarJustificacionTaxonomica\CambiarJustificacionTaxonomicaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies\CargarMatrizEspeciesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies\CargarMatrizEspeciesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\MantenerNombreTaxonomicoOriginal\MantenerNombreTaxonomicoOriginalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\MantenerNombreTaxonomicoOriginal\MantenerNombreTaxonomicoOriginalInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica\RevertirSugerenciaTaxonomicaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica\RevertirSugerenciaTaxonomicaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarRevisionDocumental\SolicitarRevisionDocumentalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarRevisionDocumental\SolicitarRevisionDocumentalInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposDwCFaltantesException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposObligatoriosVaciosException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudDepositoYaProcesada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ExtraccionDatosDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ClasificarDocumentoCargadoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\VerificarFirmaDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\InvalidarFirmaSolicitud;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\AsistenteDocumentalDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Componente Livewire para el registro de una solicitud de depósito o donación.
 */
#[Layout('layouts.app', params: ['title' => 'Nueva Solicitud de Depósito'])]
final class RegistroSolicitudDeposito extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    // ── Wizard ────────────────────────────────────────────────────────────────────

    public int $paso = 1;

    /** @var int[] */
    public array $pasosCompletados = [];

    public bool $borradorRestaurado = false;

    /** El wizard se abrió para corregir una solicitud rechazada (subsanable). */
    public bool $modoCorreccion = false;

    /** Comentario del curador a corregir (visible mientras se edita). */
    public string $comentarioCurador = '';

    // ── Paso 1 – Trámite ──────────────────────────────────────────────────────────

    public string $tipoTramite = '';

    #[Locked]
    public ?string $solicitudId = null;

    public string $numeroSolicitud = '';


    // ── Paso 2 – Origen ───────────────────────────────────────────────────────────

    public string $origenRecoleccion = 'Nacional (Ecuador)';

    public string $situacionRegulatoria = '';

    public string $provincia = '';

    public string $localidadOrigenCodigo = '';

    public string $busquedaLocalidadOrigen = '';

    public array $rechazosDocumentos = [];

    public string $localidad = '';

    // ── Paso 3 – Documentos (file uploads) ───────────────────────────────────────

    public $archivoFormatoDeposito = null;

    public $archivoFormatoDonacion = null;

    public $archivoAutorizacionMae = null;

    public $archivoPermisoMovilizacion = null;

    public $archivoCartaJustificacion = null;

    public $archivoCartaProcedencia = null;

    public $archivoCartaCesion = null;

    public $archivoCartaDelegacion = null;

    /** @var string[] */
    public array $documentosRequeridos = [];

    /** @var array<string, string> [nombre => ruta_storage] */
    #[Locked]
    public array $documentosCargados = [];

    /** @var array<string, string> [nombre => nombre_original_archivo] */
    #[Locked]
    public array $nombresArchivosOriginales = [];

    public bool $intervencionCuratoriaActiva = false;

    public string $preguntaAsistencia = '';

    /** @var list<array{rol:string,texto:string}> */
    public array $mensajesAsistencia = [];

    public bool $extraccionProcesando = false;

    public bool $analisisDocumentalCompletado = false;

    /** Timestamp Unix del momento en que se despachó el job de extracción. */
    public int $extraccionIniciadaEn = 0;

    /**
     * Tipo de advertencia cuando la extracción no puede completarse.
     * Valores posibles: '' | 'error_modelo' | 'error_cola' | 'error_procesamiento'
     */
    public string $advertenciaExtraccion = '';

    /** @var string[] */
    #[Locked]
    public array $documentosProcesados = [];

    public string $estadoValidacionContenido = '';

    /** @var list<string> */
    public array $erroresDocumentales = [];

    /** @var list<string> */
    public array $advertenciasDocumentales = [];

    // ── Paso 4 – Datos ────────────────────────────────────────────────────────────

    /** @var array<string, string|null> */
    public array $datosExtraidos = [];

    /** @var array<string, mixed> Trazabilidad y confianza del OCR/autocompletado. */
    #[Locked]
    public array $metadatosExtraccion = [];

    /** @var string[] */
    public array $datosFaltantes = [];

    /** @var string[] */
    public array $datosIngresadosManualmente = [];

    /** @var array<string, string> */
    public array $datosEnEdicion = [];

    public string $resultadoIdentidad = '';

    public string $nombreEnDocumento = '';

    public string $cargoConsultor = '';

    public string $institucionConsultor = '';

    public string $campoEditorManual = '';

    public string $valorEditorManual = '';

    public string $localidadEspecifica = '';

    public bool $cartaDelegacionRequerida = false;

    public string $estadoDocumental = '';

    /** @var array<string, string> [nombre_documento => estado_firma] */
    #[Locked]
    public array $firmasElectronicas = [];

    /** @var array<string, string> [nombre_documento => estado_validacion] */
    #[Locked]
    public array $validacionArchivos = [];

    // ── Paso 5 – Matriz de especies ─────────────────────────────────────────────

    public $archivoMatriz = null;

    #[Locked]
    public ?string $matrizId = null;

    public string $estadoMatriz = '';

    public bool $validacionTipograficaAplicada = false;

    public int $totalRegistros = 0;

    /** @var string[] */
    public array $camposDwCPresentes = [];

    /** @var string[] */
    public array $camposDwCCriticos = [];

    /** @var string[] */
    public array $camposDwCRecomendados = [];

    /** @var string[] Recomendados ausentes en el Excel — advertencia, no bloqueo */
    public array $camposDwCRecomendadosFaltantes = [];

    /** @var array<int, array<string, mixed>> */
    public array $registrosMatriz = [];

    /**
     * Estado de cada registro para la tabla visual.
     *
     * @var array<string, array{catalogoId: string|null, especieIngresada: string, estado: string, especieSugerida: string|null, especiesSugeridas: list<string>, especieCorregida: string|null, noCatalogado: bool, motivoJustificacion: string|null, comentarioJustificacion: string|null}>
     */
    public array $estadosRegistros = [];

    /**
     * Motivo de justificación por registro (bind del select de la matriz).
     *
     * @var array<string, string>
     */
    public array $motivosJustificacion = [];

    /**
     * Comentario libre de justificación por registro (bind del textarea de la matriz).
     *
     * @var array<string, string>
     */
    public array $comentariosJustificacion = [];

    /**
     * Motivo asignado cuando el depositante justifica solo con un comentario, sin
     * elegir un motivo explícito: describe el hecho neutro (el nombre no está en GBIF)
     * sin afirmar novedad taxonómica.
     */
    private const MOTIVO_JUSTIFICACION_NEUTRO = 'Nombre no presente en el catálogo GBIF';

    public string $archivoMatrizNombre = '';

    public bool $matrizCargada = false;

    /** Matriz nativa: elimina la dependencia obligatoria de una hoja de cálculo. */
    public string $busquedaTaxon = '';

    /** @var list<array<string, mixed>> */
    public array $opcionesTaxones = [];

    /** @var array<string, mixed> */
    public array $taxonSeleccionado = [];

    /** @var list<array{codigo: string, nombre: string, rango: string}> */
    public array $catalogoGrupos = [];

    /** @var list<array{codigo: string, nombre: string, continente: string}> */
    public array $catalogoPaises = [];

    /** @var list<array<string, mixed>> */
    public array $muestrasDetectadas = [];

    /** @var array<string, string|int> */
    public array $registroNativo = [
        'scientificName' => '',
        'recordNumber' => '',
        'origin' => 'research',
        'identifiedBy' => '',
        'dateIdentified' => '',
        'researchPermit' => '',
        'transportPermit' => '',
        'decimalLatitude' => '',
        'decimalLongitude' => '',
        'verbatimLocality' => '',
        'country' => 'Ecuador',
        'countryCode' => 'EC',
        'continent' => 'South America',
        'stateProvince' => '',
        'municipality' => '',
        'eventDate' => '',
        'recordedBy' => '',
        'individualCount' => 1,
        'basisOfRecord' => 'PreservedSpecimen',
        'samplingProtocol' => '',
        'preparations' => 'ethanol',
        'occurrenceRemarks' => '',
        'datasetName' => 'Colección de Invertebrados MEPN',
    ];

    /** @var list<array<string, mixed>> */
    public array $registrosNativos = [];

    public string $errorMatriz = '';

    /** @var list<array{fila: string, campo: string}> Campos obligatorios vacíos que bloquean la carga. */
    public array $camposObligatoriosVacios = [];

    public string $filtroTabla = 'todos';

    // ── Paso 6 – Envío ────────────────────────────────────────────────────────────

    public bool $declaracionAceptada = false;

    public string $estadoFinal = '';

    public string $mensajeEstadoSincronizado = '';

    public bool $solicitudFirmada = false;

    /** @var array<string, mixed> */
    #[Locked]
    public array $solicitudFirmaMetadata = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function mount(?string $id = null): void
    {
        $this->cargarCatalogosControlados();
        $this->cargoConsultor = (string) (auth()->user()->cargo ?? '');
        $this->institucionConsultor = (string) (auth()->user()->institucion ?? '');
        if ($this->institucionConsultor !== '' && ! DB::table('usuarios.instituciones_catalogo')
            ->where('nombre', $this->institucionConsultor)->where('activo', true)->exists()) {
            $this->institucionConsultor = '';
        }

        // Flujo de corrección: se abrió /deposito/{id}/corregir para subsanar un rechazo.
        if ($id !== null) {
            $this->iniciarCorreccion($id);

            return;
        }

        $borrador = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->first();

        if ($borrador) {
            try {
                $this->restaurarDesdeBorrador($borrador);
            } catch (\Throwable $e) {
                \Log::error('Error al restaurar borrador de depósito', [
                    'borrador_id' => $borrador->id,
                    'error' => $e->getMessage(),
                ]);
                // Reset total: evitar que el estado parcial (solicitudId asignado, paso=1)
                // permita que avanzarPaso1() sobreescriba el paso_actual real del borrador.
                $this->solicitudId = null;
                $this->borradorRestaurado = false;
                $this->dispatch('domain-error', message: 'No se pudo restaurar el borrador. Por favor, intenta nuevamente.');
            }

            return;
        }
    }

    /**
     * Defensa en profundidad para cada petición Livewire posterior al montaje.
     * Los identificadores también están bloqueados, pero esta verificación evita
     * que cualquier operación confíe únicamente en el estado hidratado del cliente.
     */
    public function hydrate(): void
    {
        if ($this->solicitudId === null) {
            return;
        }

        $esPropia = SolicitudDepositoEloquentModel::query()
            ->whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->exists();
        abort_unless($esPropia, 404);

        if ($this->matrizId !== null) {
            $matrizPertenece = MatrizEspeciesEloquentModel::query()
                ->whereKey($this->matrizId)
                ->where('solicitud_id', $this->solicitudId)
                ->exists();
            abort_unless($matrizPertenece, 404);
        }
    }

    /**
     * Abre una solicitud devuelta para corrección (subsanable) y rehidrata el wizard.
     * La solicitud se edita EN SU SITIO, conservando su estado "Requiere Corrección"
     * (no se convierte en borrador), para no perder trazabilidad en "Mis depósitos".
     * Solo al reenviar pasa a "Pendiente de Revisión".
     */
    private function iniciarCorreccion(string $id): void
    {
        $model = SolicitudDepositoEloquentModel::where('id', $id)
            ->where('investigador_id', (string) auth()->id())
            ->firstOrFail();

        if (! in_array($model->estado, [
            EstadoSolicitudDeposito::RequiereCorreccion->value,
            EstadoSolicitudDeposito::EnBorrador->value,
        ], true)) {
            $this->presentarEstadoPersistido($model);

            return;
        }

        $this->modoCorreccion = true;
        $this->comentarioCurador = (string) ($model->comentario_curador ?? '');

        $this->restaurarDesdeBorrador($model);
    }


    public function updatedProvincia(): void
    {
        $this->localidadOrigenCodigo = '';
        $this->busquedaLocalidadOrigen = '';
    }

    public function seleccionarProvincia(string $provincia): void
    {
        $permitidas = array_column(CatalogoTerritorialEcuador::provincias(), 'nombre');
        $this->provincia = in_array($provincia, $permitidas, true) ? $provincia : '';
        $this->localidadOrigenCodigo = '';
        $this->busquedaLocalidadOrigen = '';
    }

    public function seleccionarLocalidadOrigen(string $codigo): void
    {
        $this->localidadOrigenCodigo = CatalogoLocalidadesEcuador::buscar($this->provincia, $codigo) !== null ? $codigo : '';
    }

    public function guardarOrigenDesdeFormulario(string $provincia, string $localidadCodigo, string $situacion): void
    {
        $this->origenRecoleccion = 'Nacional (Ecuador)';
        $this->provincia = $provincia;
        $this->localidadOrigenCodigo = $localidadCodigo;
        $this->situacionRegulatoria = $situacion;
        $this->guardarPasoDos(
            app(DeterminarDocumentacionRequeridaHandler::class),
            app(ActualizarOrigenSolicitudDepositoHandler::class),
        );
    }

    // ── Restauración de borrador ──────────────────────────────────────────────────

    private function restaurarDesdeBorrador(SolicitudDepositoEloquentModel $model): void
    {
        $this->borradorRestaurado = true;
        $this->solicitudId = $model->id;
        $this->numeroSolicitud = $model->numero;
        $this->tipoTramite = $model->tipo_tramite;
        $this->solicitudFirmada = $model->solicitud_firmada_en !== null;
        $this->solicitudFirmaMetadata = $model->solicitud_firma_metadata ?? [];

        $pasoGuardado = $model->paso_actual ?? 1;

        // Paso 2 data
        $this->origenRecoleccion = $model->origen_recoleccion ?? '';
        $this->situacionRegulatoria = $model->situacion_regulatoria ?? '';
        $this->provincia = $model->provincia_origen ?? '';
        $this->localidadOrigenCodigo = $model->localidad_origen_codigo ?? '';

        // Paso 3 data
        $this->documentosRequeridos = $model->documentos_requeridos ?? [];
        $this->documentosCargados = $model->documentos_cargados ?? [];
        $this->nombresArchivosOriginales = $model->nombres_archivos_originales ?? [];
        $this->firmasElectronicas = $model->firmas_electronicas ?? [];
        $this->validacionArchivos = $model->validacion_archivos ?? [];

        // Si hay archivos cargados, verificar que aún existen en storage
        $this->documentosCargados = array_filter(
            $this->documentosCargados,
            fn (string $ruta) => app(AlmacenamientoDepositos::class)->existe($ruta)
        );

        // Limpiar originales de documentos cuyo archivo ya no existe
        $this->nombresArchivosOriginales = array_intersect_key(
            $this->nombresArchivosOriginales,
            $this->documentosCargados
        );

        if ($model->sin_documentacion) {
            $this->intervencionCuratoriaActiva = true;
        }

        // Solo reactivar polling si estaba en paso 3 (extracción en curso).
        // En paso 4+ la extracción ya fue procesada, no necesita polling.
        if ($pasoGuardado === 3 && in_array($model->extraccion_estado, ['en_cola', 'procesando'], true)) {
            $this->extraccionProcesando = true;
            $this->extraccionIniciadaEn = $model->updated_at->timestamp;
        }

        // Paso 4+ data
        if ($pasoGuardado >= 4 || $model->extraccion_estado === 'completada') {
            $this->datosExtraidos = $this->construirDatosExtraidos($model);
            $this->metadatosExtraccion = $model->extraccion_metadatos ?? [];
            $this->aplicarResultadosDocumentales($this->metadatosExtraccion);
            $this->datosFaltantes = $model->datos_faltantes ?? [];
            $this->datosIngresadosManualmente = $model->datos_ingresados_manualmente ?? [];

            // Eliminar faltantes obsoletos que ya no pertenecen al flujo actual
            // (ej. borradores extranjeros con campos nacionales en datos_faltantes).
            $this->datosFaltantes = array_values(
                array_filter($this->datosFaltantes, fn ($campo) => array_key_exists($campo, $this->datosExtraidos))
            );

            // Compatibilidad: borradores creados antes del commit 5265671 no incluyen
            // los campos cuantitativos en datos_faltantes. Agregarlos solo si pertenecen
            // al flujo actual y tienen valor null en BD.
            foreach (['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'] as $campo) {
                if (array_key_exists($campo, $this->datosExtraidos)
                    && ($this->datosExtraidos[$campo] ?? null) === null
                    && ! in_array($campo, $this->datosFaltantes, true)) {
                    $this->datosFaltantes[] = $campo;
                }
            }

            $this->analisisDocumentalCompletado = $model->extraccion_estado === 'completada';
            $this->nombreEnDocumento = $model->nombre_investigador_documento ?? '';
            $this->documentosProcesados = $model->documentos_procesados ?? [];

            if ($pasoGuardado >= 7) {
                $this->prepararRegistroNativoDesdeExpediente();
            }

            // Re-derivar validación de identidad si ya fue realizada
            if (! empty($this->nombreEnDocumento)) {
                $handler = app(ValidarIdentidadSolicitudHandler::class);
                $output = ($handler)(new ValidarIdentidadSolicitudInput(
                    solicitudId: $this->solicitudId,
                    nombrePerfil: auth()->user()->name,
                    nombreEnDocumento: $this->nombreEnDocumento,
                ));
                $this->resultadoIdentidad = $output->resultado->value;
                $this->cartaDelegacionRequerida = $output->resultado === ResultadoValidacionIdentidad::DiscrepanciaTercero;
            }

            // Re-derivar estado documental
            $handlerDoc = app(ValidarDocumentacionInicialHandler::class);
            $outputDoc = ($handlerDoc)(new ValidarDocumentacionInicialInput(
                solicitudId: $this->solicitudId,
                provinciaOrigen: $this->provincia ?: null,
                documentosAdjuntos: $this->documentosCargados,
            ));
            $this->estadoDocumental = $outputDoc->estadoDocumental->value;
        }

        // Matriz de especies. Puede existir aunque el usuario haya retrocedido y
        // persistido un paso anterior; al reanudar debemos conservarla disponible.
        if ($model->matriz_id !== null) {
            $this->matrizId = $model->matriz_id;
            $this->matrizCargada = true;

            $matrizRepo = app(MatrizEspeciesRepositoryInterface::class);
            $matriz = $matrizRepo->buscarPorId(MatrizEspeciesId::from($model->matriz_id));

            if ($matriz !== null) {
                $this->estadoMatriz = $matriz->estado()->value;
                $this->totalRegistros = count($matriz->registros());
                $this->camposDwCPresentes = array_keys($matriz->camposDwCPresentes());
                $this->archivoMatrizNombre = 'Matriz cargada';

                $catalogo = app(CatalogoCuraduriaPort::class);
                $this->camposDwCCriticos = $catalogo->camposCriticos($this->solicitudId ?? '');
                $this->camposDwCRecomendados = $catalogo->camposRecomendados($this->solicitudId ?? '');
                $this->camposDwCRecomendadosFaltantes = array_values(array_filter(
                    $this->camposDwCRecomendados,
                    fn (string $campo) => ! in_array($campo, $this->camposDwCPresentes, true)
                ));

                $this->poblarEstadosRegistros($matriz);
            }
        }

        // Restaurar paso y pasos completados
        $this->paso = $pasoGuardado === 5 ? 3 : $pasoGuardado;
        $this->pasosCompletados = $this->calcularPasosCompletados($pasoGuardado);
        if ($this->paso === 3) {
            $this->actualizarFirmas();
        }
    }

    /** @return int[] */
    private function calcularPasosCompletados(int $pasoActual): array
    {
        $completados = [];
        for ($i = 1; $i < $pasoActual; $i++) {
            $completados[] = $i;
        }

        // Donación siempre tiene paso 2 como completado (se salta)
        if ($this->tipoTramite === TipoTramite::Donacion->value && ! in_array(2, $completados, true) && $pasoActual > 2) {
            $completados[] = 2;
            sort($completados);
        }

        return $completados;
    }

    /** @return array<string, string|null> */
    private function construirDatosExtraidos(SolicitudDepositoEloquentModel $model): array
    {
        if ($this->tipoTramite === TipoTramite::Deposito->value) {
            if ($this->origenRecoleccion === 'Exterior (Extranjero)') {
                return [
                    'N.º Investigación' => $model->nro_permiso_recoleccion,
                    'Grupo Animal' => $model->grupo_animal,
                    'Administración Política' => $model->provincia_origen,
                    'Localidad' => $model->localidad,
                    'N.º Individuos' => $model->nro_individuos !== null ? (string) $model->nro_individuos : null,
                ];
            }

            return [
                'N.º Permiso Recolección' => $model->nro_permiso_recoleccion,
                'N.º Permiso Movilización' => $model->nro_permiso_movilizacion,
                'Grupo Animal' => $model->grupo_animal,
                'Provincia' => ($model->provincia_origen === 'Fuera de Pichincha') ? null : $model->provincia_origen,
                'Localidad' => $model->localidad,
                'N.º Individuos' => $model->nro_individuos !== null ? (string) $model->nro_individuos : null,
                'N.º Morfoespecies' => $model->nro_morfoespecies !== null ? (string) $model->nro_morfoespecies : null,
                'N.º Lotes' => $model->nro_lotes !== null ? (string) $model->nro_lotes : null,
            ];
        }

        return [
            'Grupo Animal' => $model->grupo_animal,
            'Origen Donación' => $model->origen_donacion,
            'N.º Individuos' => $model->nro_individuos !== null ? (string) $model->nro_individuos : null,
            'N.º Morfoespecies' => $model->nro_morfoespecies !== null ? (string) $model->nro_morfoespecies : null,
            'N.º Lotes' => $model->nro_lotes !== null ? (string) $model->nro_lotes : null,
        ];
    }

    private function persistirEstadoWizard(): void
    {
        if ($this->solicitudId === null) {
            return;
        }

        SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->update([
                'paso_actual' => $this->paso,
                'documentos_requeridos' => $this->documentosRequeridos,
                'matriz_id' => $this->matrizId,
            ]);
    }

    public function descartarBorrador(): void
    {
        if ($this->solicitudId !== null) {
            $borrador = SolicitudDepositoEloquentModel::query()
                ->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())
                ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
                ->firstOrFail();

            // Las rutas se obtienen de la base, nunca del estado enviado por el navegador.
            foreach (($borrador->documentos_cargados ?? []) as $ruta) {
                if (! is_string($ruta) || $ruta === '') {
                    continue;
                }
                app(AlmacenamientoDepositos::class)->eliminar($ruta);
            }

            $borrador->delete();
        }

        $this->reset();
        $this->borradorRestaurado = false;
    }

    // ── Paso 1 ────────────────────────────────────────────────────────────────────

    public function avanzarPaso1(RegistrarSolicitudDepositoHandler $registrar): void
    {
        if (empty($this->tipoTramite)) {
            $this->addError('tipoTramite', 'Selecciona un tipo de trámite para continuar.');

            return;
        }


        // Crear registro en BD si aún no existe
        if ($this->solicitudId === null) {
            $output = ($registrar)(new RegistrarSolicitudDepositoInput(
                    investigadorId: (string) auth()->id(),
                    tipoTramite: $this->tipoTramite,
                ));
                $this->solicitudId = $output->id;
                $this->numeroSolicitud = $output->numero;
        } else {
            // El usuario regresó al paso 1 y puede haber cambiado el tipo: sincronizar en BD.
            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update(['tipo_tramite' => $this->tipoTramite]);

            // Al cambiar a Depósito, deshacer el salto automático de Donación:
            // el paso 2 ya no puede darse por completado y los docs deben redeterminarse.
            if ($this->tipoTramite === TipoTramite::Deposito->value) {
                $this->pasosCompletados = array_values(
                    array_filter($this->pasosCompletados, fn ($p) => $p !== 2)
                );
                $this->documentosRequeridos = [];
            }
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 1]));

        if ($this->tipoTramite === TipoTramite::Donacion->value) {
            // La solicitud y la declaración de cesión se generan dentro de HubDigital;
            // no se obliga al ciudadano a editar plantillas fuera del sistema.
            $this->documentosRequeridos = [];
            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
            $this->paso = 3;
            $this->persistirEstadoWizard();

            return;
        }

        $this->paso = 2;
        $this->persistirEstadoWizard();
    }

    // ── Paso 2 ────────────────────────────────────────────────────────────────────

    /**
     * Valida y guarda la información del paso 2.
     */
    public function guardarPasoDos(
        DeterminarDocumentacionRequeridaHandler $determinar,
        ActualizarOrigenSolicitudDepositoHandler $actualizar,
    ): void {
        $this->origenRecoleccion = 'Nacional (Ecuador)';
        if ($this->situacionRegulatoria !== ''
            && ! in_array($this->situacionRegulatoria, ['Posee permisos del MAE', 'Sin permisos del MAE'], true)) {
            throw ValidationException::withMessages(['situacionRegulatoria' => 'Selecciona una situación regulatoria válida.']);
        }
        $rules = [
            'origenRecoleccion' => 'required|string',
            'situacionRegulatoria' => 'required|string',
        ];

        $messages = [
            'origenRecoleccion.required' => 'Selecciona la procedencia de los especímenes.',
            'situacionRegulatoria.required' => 'Selecciona la situación regulatoria.',
        ];

        if ($this->origenRecoleccion === 'Nacional (Ecuador)') {
            $rules['provincia'] = 'required|string';
            $rules['localidadOrigenCodigo'] = 'required|string|max:64';
            $messages['provincia.required'] = 'Selecciona la provincia de recolección.';
            $messages['localidadOrigenCodigo.required'] = 'Selecciona la localidad de recolección.';
        }

        $this->validate($rules, $messages);
        if ($this->origenRecoleccion === 'Nacional (Ecuador)'
            && CatalogoLocalidadesEcuador::buscar($this->provincia, $this->localidadOrigenCodigo) === null) {
            throw ValidationException::withMessages(['localidadOrigenCodigo' => 'Selecciona una localidad activa de la provincia indicada.']);
        }

        $output = ($determinar)(new DeterminarDocumentacionRequeridaInput(
            tipoTramite: $this->tipoTramite,
            origenRecoleccion: $this->origenRecoleccion,
            situacionRegulatoria: $this->situacionRegulatoria,
            provinciaOrigen: $this->provincia ?: null,
        ));

        $this->documentosRequeridos = $output->documentosRequeridos;

        // Persistir datos de origen en BD
        ($actualizar)(new ActualizarOrigenSolicitudDepositoInput(
            solicitudId: $this->solicitudId,
            origenRecoleccion: $this->origenRecoleccion,
            situacionRegulatoria: $this->situacionRegulatoria,
            provinciaOrigen: $this->provincia ?: null,
            localidadOrigenCodigo: $this->localidadOrigenCodigo ?: null,
        ));

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
        $this->paso = 3;
        $this->persistirEstadoWizard();
        $this->actualizarFirmas();
        $actual = SolicitudDepositoEloquentModel::query()->findOrFail($this->solicitudId);
        $this->localidad = (string) ($actual->localidad ?? '');
        $this->solicitudFirmada = filled($actual->solicitud_firmada_ruta);
        $this->solicitudFirmaMetadata = $actual->solicitud_firma_metadata ?? [];
    }

    // ── Paso 3 – File upload lifecycle hooks ──────────────────────────────────────

    public function updatedArchivoFormatoDeposito(): void
    {
        $this->registrarDocumentoCargado('archivoFormatoDeposito', 'Formato solicitud depósito', $this->archivoFormatoDeposito);
    }

    public function updatedArchivoFormatoDonacion(): void
    {
        $this->registrarDocumentoCargado('archivoFormatoDonacion', 'Formato solicitud donación', $this->archivoFormatoDonacion);
    }

    /**
     * Hook que se ejecuta al cargar la autorización del MAE.
     */
    public function updatedArchivoAutorizacionMae(): void
    {
        $this->registrarDocumentoCargado('archivoAutorizacionMae', 'Copia de la autorización de recolección (MAE)', $this->archivoAutorizacionMae);
    }

    public function updatedArchivoPermisoMovilizacion(): void
    {
        $this->registrarDocumentoCargado('archivoPermisoMovilizacion', 'Copia del permiso de movilización', $this->archivoPermisoMovilizacion);
    }

    public function updatedArchivoCartaJustificacion(): void
    {
        $this->registrarDocumentoCargado(
            'archivoCartaJustificacion',
            'Documento de explicación de motivos y/o carta de justificación (institucional o personal)',
            $this->archivoCartaJustificacion
        );
    }

    public function updatedArchivoCartaProcedencia(): void
    {
        $this->registrarDocumentoCargado(
            'archivoCartaProcedencia',
            'Documento de procedencia de los especimenes',
            $this->archivoCartaProcedencia
        );
    }

    public function updatedArchivoCartaCesion(): void
    {
        $this->registrarDocumentoCargado('archivoCartaCesion', 'Carta de cesión de derechos / origen lícito', $this->archivoCartaCesion);
    }

    /**
     * Hook que se ejecuta al cargar la carta de delegación.
     */
    public function updatedArchivoCartaDelegacion(): void
    {
        $this->registrarDocumentoCargado('archivoCartaDelegacion', 'Carta de delegación / justificación de tercero', $this->archivoCartaDelegacion);
    }

    private function registrarDocumentoCargado(string $propiedad, string $nombre, mixed $archivo): void
    {
        if ($archivo === null) {
            return;
        }

        $this->validate(
            [$propiedad => 'file|mimes:pdf|extensions:pdf|max:20480'],
            [
                "{$propiedad}.mimes" => "Solo se aceptan archivos PDF para \"{$nombre}\".",
                "{$propiedad}.extensions" => 'El archivo debe tener extensión .pdf.',
                "{$propiedad}.max" => 'El archivo no debe superar los 20 MB.',
            ]
        );

        try {
            $previo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())->firstOrFail();
            abort_unless(in_array($previo->estado, [EstadoSolicitudDeposito::EnBorrador->value, EstadoSolicitudDeposito::RequiereCorreccion->value], true), 409, 'La solicitud ya no admite cambios de documentos.');
            abort_unless(in_array($nombre, $previo->documentos_requeridos ?? [], true), 422);
            $provinciaAlIniciar = $previo->provincia_origen;
            $comprobacion = app(ValidacionPreviaDocumentoDeposito::class)->validar(
                $archivo->getRealPath(), $nombre,
            );
            $guardado = app(AlmacenamientoDepositos::class)->guardarArchivoConHuella($archivo, 'depositos/'.$this->solicitudId);
            $ruta = $guardado['ruta'];
            if (! hash_equals($comprobacion['sha256'], $guardado['sha256'])) {
                app(AlmacenamientoDepositos::class)->eliminar($ruta);
                throw new DocumentoDepositoRechazado('pdf_inseguro', 'El archivo cambió después de comprobarlo.');
            }
        } catch (\InvalidArgumentException $error) {
            $this->reset($propiedad);
            $this->rechazosDocumentos[$nombre] = [
                'estado' => $error instanceof DocumentoDepositoRechazado ? $error->estado : 'pdf_inseguro',
                'mensaje' => $error->getMessage(),
            ];
            $this->addError($propiedad, $error->getMessage());
            $this->dispatch('documento-rechazado', propiedad: $propiedad,
                estado: $this->rechazosDocumentos[$nombre]['estado'], mensaje: $error->getMessage());

            return;
        }
        $this->extraccionProcesando = false;
        $this->documentosProcesados = [];
        $this->analisisDocumentalCompletado = false;
        $this->estadoValidacionContenido = '';
        $this->erroresDocumentales = [];
        $this->advertenciasDocumentales = [];
        try {
            [$documentosActualizados, $nombresActualizados, $rutaAnterior, $firmas, $validaciones, $verificacionId] = DB::transaction(function () use ($nombre, $ruta, $archivo, $comprobacion, $provinciaAlIniciar): array {
                $modelo = SolicitudDepositoEloquentModel::query()
                    ->whereKey($this->solicitudId)
                    ->where('investigador_id', (string) auth()->id())
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless(in_array($modelo->estado, [EstadoSolicitudDeposito::EnBorrador->value, EstadoSolicitudDeposito::RequiereCorreccion->value], true), 409, 'La solicitud cambió de estado mientras se revisaba el PDF.');
                abort_unless($modelo->provincia_origen === $provinciaAlIniciar, 409, 'La provincia cambió mientras se revisaba el PDF. Vuelve a seleccionarlo.');
                abort_unless(in_array($nombre, $modelo->documentos_requeridos ?? [], true), 409);
                $documentosActualizados = $modelo->documentos_cargados ?? [];
                $nombresActualizados = $modelo->nombres_archivos_originales ?? [];
                $rutaAnterior = $documentosActualizados[$nombre] ?? null;
                $documentosActualizados[$nombre] = $ruta;
                $nombresActualizados[$nombre] = $archivo->getClientOriginalName();
                $firmas = $modelo->firmas_electronicas ?? [];
                $validaciones = $modelo->validacion_archivos ?? [];
                $firmas[$nombre] = 'validando';
                $validaciones[$nombre] = 'valido';
                $verificacionId = (string) Str::uuid();
                $previas = $modelo->validacion_previa_documentos ?? [];
                $previas[$nombre] = [...$comprobacion, 'ruta' => $ruta,
                    'verificacion_id' => $verificacionId, 'firma_verificada' => false,
                    'comprobado_en' => now()->toIso8601String()];
                $metadatos = $modelo->extraccion_metadatos ?? [];
                $revision = $metadatos['revision_documental'] ?? null;
                $historial = $metadatos['revision_documental_historial'] ?? [];
                if (is_array($revision)) {
                    $historial[] = [...$revision, 'estado' => 'invalidada', 'invalidada_en' => now()->toIso8601String(), 'motivo_invalidacion' => 'El consultor sustituyó documentación del expediente.'];
                }
                $modelo->forceFill([
                    'documentos_cargados' => $documentosActualizados,
                    'nombres_archivos_originales' => $nombresActualizados,
                    'extraccion_estado' => null,
                    'extraccion_metadatos' => [...$metadatos,
                        'revision_documental' => is_array($revision) ? [...$revision, 'estado' => 'invalidada'] : null,
                        'revision_documental_historial' => $historial],
                    'documentos_procesados' => [],
                    'firmas_electronicas' => $firmas,
                    'validacion_archivos' => $validaciones,
                    'validacion_previa_documentos' => $previas,
                ])->save();
                return [$documentosActualizados, $nombresActualizados, $rutaAnterior, $firmas, $validaciones, $verificacionId];
            });
        } catch (\Throwable $error) {
            app(AlmacenamientoDepositos::class)->eliminar($ruta);
            throw $error;
        }
        $this->documentosCargados = $documentosActualizados;
        $this->nombresArchivosOriginales = $nombresActualizados;
        $this->firmasElectronicas = $firmas;
        $this->validacionArchivos = $validaciones;
        unset($this->rechazosDocumentos[$nombre]);
        $this->resetErrorBag($propiedad);
        if (is_string($rutaAnterior) && $rutaAnterior !== '') {
            try { app(AlmacenamientoDepositos::class)->eliminar($rutaAnterior); }
            catch (\Throwable $error) { report($error); }
        }
        $this->invalidarFirmaSolicitud();
        $this->dispatch('documento-aceptado', propiedad: $propiedad);
        try {
            VerificarFirmaDocumentoJob::dispatch($this->solicitudId, $nombre, $ruta, $verificacionId);
        } catch (\Throwable $error) {
            report($error);
            (new VerificarFirmaDocumentoJob($this->solicitudId, $nombre, $ruta, $verificacionId))->failed($error);
            $this->actualizarFirmas();
        }

    }

    /**
     * Elimina un documento previamente cargado.
     */
    public function eliminarDocumento(string $nombre): void
    {
        if (isset($this->documentosCargados[$nombre])) {
            $ruta = $this->persistirRetiroDocumento($nombre);

            // Primero se hace durable la nueva versión del expediente y se invalida
            // cualquier decisión humana asociada. Solo después se retira el objeto;
            // así una caída de almacenamiento nunca deja a la base apuntando a una
            // revisión de archivos que ya no representa el expediente.
            if (is_string($ruta) && $ruta !== '') {
                app(AlmacenamientoDepositos::class)->eliminar($ruta);
            }
        }

        $this->estadoValidacionContenido = '';
        $this->erroresDocumentales = [];
        $this->advertenciasDocumentales = [];
        $this->invalidarFirmaSolicitud();

        $this->analisisDocumentalCompletado = false;

        $propiedad = $this->propiedadParaDocumento($nombre);
        $this->reset($propiedad);

        $this->persistirEstadoWizard();
    }

    public function sugerirPreguntaAsistencia(string $tema): void
    {
        $preguntas = [
            'autorizacion' => '¿Qué autorización debo subir?',
            'movilizacion' => '¿Qué guía de movilización debo subir?',
            'expediente' => '¿Cómo deben coincidir los documentos del mismo expediente?',
            'ausencia' => 'No tengo documentos',
        ];

        if (! isset($preguntas[$tema])) {
            return;
        }

        $this->preguntaAsistencia = $preguntas[$tema];
        $this->enviarPreguntaAsistencia();
    }

    public function enviarPreguntaAsistencia(): void
    {
        if ($this->paso !== 3) {
            return;
        }

        $pregunta = trim($this->preguntaAsistencia);
        if ($pregunta === '' || mb_strlen($pregunta) > 240) {
            $this->addError('preguntaAsistencia', 'Escribe una pregunta de hasta 240 caracteres.');

            return;
        }

        $this->resetErrorBag('preguntaAsistencia');
        $this->preguntaAsistencia = '';
        $historial = array_slice($this->mensajesAsistencia, -6);
        $this->mensajesAsistencia[] = ['rol' => 'usuario', 'texto' => $pregunta];

        $limite = 'asistencia-documental:'.auth()->id();
        if (RateLimiter::tooManyAttempts($limite, 12)) {
            $respuesta = 'Has enviado varias preguntas seguidas. Espera un minuto para continuar.';
        } else {
            RateLimiter::hit($limite, 60);
            $respuesta = app(AsistenteDocumentalDepositos::class)->responder($pregunta, $this->documentosRequeridos, $historial);
        }

        $this->mensajesAsistencia[] = ['rol' => 'bot', 'texto' => $respuesta];
        $this->mensajesAsistencia = array_slice($this->mensajesAsistencia, -20);
        $this->dispatch('asistencia-mensaje-enviado');
    }

    /** Solicita que curaduría resuelva incertidumbres, sin declarar documentos ausentes. */
    public function solicitarRevisionDocumental(SolicitarRevisionDocumentalHandler $handler): void
    {
        if ($this->solicitudId === null || $this->documentosCargados === []) {
            return;
        }

        ($handler)(new SolicitarRevisionDocumentalInput(
            $this->solicitudId,
            $this->datosRevisionDocumental('incertidumbre documental detectada por el analizador'),
        ));
        $this->intervencionCuratoriaActiva = true;
    }

    /**
     * Verifica y avanza en el paso 3.
     */
    public function guardarPasoTres(): void
    {
        $this->actualizarFirmas();
        if ($this->rechazosDocumentos !== []) {
            $this->addError('documentos', 'Reemplaza los documentos rechazados antes de continuar.');
            return;
        }
        $actual = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())->firstOrFail();
        foreach ($this->documentosRequeridos as $doc) {
            if (! isset($this->documentosCargados[$doc])) {
                $this->addError('documentos', "El documento \"{$doc}\" es requerido.");

                return;
            }
            $previa = $actual->validacion_previa_documentos[$doc] ?? [];
            if (($previa['ruta'] ?? null) !== ($actual->documentos_cargados[$doc] ?? null)
                || ($previa['firma_verificada'] ?? false) !== true) {
                $this->addError('documentos', 'Cada PDF debe completar su propia comprobación de contenido y firma.');
                return;
            }
        }

        foreach ($this->documentosRequeridos as $doc) {
            if (($this->validacionArchivos[$doc] ?? null) !== 'valido') {
                $this->mostrarToast('Espera la revisión del contenido y corrige los archivos señalados.', 'error');

                return;
            }
            if (! in_array($this->firmasElectronicas[$doc] ?? '', ['firmado', 'firmado_sin_revocacion'], true)) {
                $this->mostrarToast('Espera la comprobación de firmas y corrige los documentos indicados antes de continuar.', 'error');

                return;
            }
        }

        if ($this->analisisDocumentalCompletado) {
            $sinVerificar = array_filter(array_intersect_key($this->firmasElectronicas, array_flip($this->documentosRequeridos)), fn ($estado) => ! in_array($estado, ['firmado', 'firmado_sin_revocacion'], true));
            if ($sinVerificar !== []) {
                $this->mostrarToast('Adjunta documentos firmados y corrige las firmas inválidas antes de continuar.', 'error');

                return;
            }
            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 3]));
            $this->paso = 4;
            $this->persistirEstadoWizard();
            $this->avisarDatosPendientes();

            return;
        }

        if (! $this->extraccionProcesando && $this->solicitudId !== null) {
            $this->resetErrorBag('documentos');
            $this->estadoValidacionContenido = '';
            $this->erroresDocumentales = [];
            $this->advertenciasDocumentales = [];
            $this->advertenciaExtraccion = '';

            $versionDocumental = ExtraccionDatosDocumentoJob::huellaDocumental($this->documentosCargados);
            $ejecucionId = (string) Str::uuid();
            $modelo = SolicitudDepositoEloquentModel::query()
                ->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())
                ->firstOrFail();
            $metadatos = $modelo->extraccion_metadatos ?? [];
            unset($metadatos['fallo_extraccion']);
            $metadatos['ejecucion_id'] = $ejecucionId;
            $modelo->forceFill([
                'extraccion_estado' => 'en_cola',
                'documentos_procesados' => [],
                'extraccion_metadatos' => $metadatos,
            ])->save();

            try {
                ExtraccionDatosDocumentoJob::dispatch(
                    $this->solicitudId,
                    array_intersect_key($this->documentosCargados, array_flip($this->documentosRequeridos)),
                    $versionDocumental,
                    $ejecucionId,
                );
            } catch (\Throwable $error) {
                Log::error('No fue posible encolar la extracción documental', [
                    'solicitudId' => $this->solicitudId,
                    'error' => $error->getMessage(),
                ]);
                $mensaje = 'El análisis no pudo ponerse en cola. Conservamos tus archivos; vuelve a intentarlo en unos minutos.';
                $metadatos['fallo_extraccion'] = [
                    'codigo' => 'cola_no_disponible',
                    'documento' => null,
                    'mensaje_usuario' => $mensaje,
                    'reintento_permitido' => true,
                    'ocurrido_en' => now()->toIso8601String(),
                    'version_documental' => $versionDocumental,
                ];
                $modelo->forceFill([
                    'extraccion_estado' => 'fallida',
                    'extraccion_metadatos' => $metadatos,
                ])->save();
                $this->advertenciaExtraccion = 'error_cola';
                $this->metadatosExtraccion = $metadatos;
                $this->avanzarDesdeFalloExtraccion($mensaje, 'error_cola');

                return;
            }
            $this->extraccionProcesando = true;
            $this->extraccionIniciadaEn = now()->timestamp;
            // Persiste el estado para que updated_at refleje el momento del dispatch,
            // no el de la última subida de documento. Así el timeout de verificarExtraccion()
            // se mide desde el instante correcto tras una recarga de página.
            $this->persistirEstadoWizard();
        }
    }

    public function repetirVerificacionFirmas(): void
    {
        if ($this->paso !== 3 || $this->extraccionProcesando || $this->documentosCargados === []) {
            return;
        }
        $pendientes = DB::transaction(function (): array {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())->lockForUpdate()->firstOrFail();
            $firmas = $modelo->firmas_electronicas ?? [];
            $pendientes = [];
            foreach (($modelo->documentos_cargados ?? []) as $nombre => $ruta) {
                if (($modelo->validacion_archivos[$nombre] ?? null) !== 'valido'
                    || ($firmas[$nombre] ?? null) === 'firmado') {
                    continue;
                }
                $previas = $modelo->validacion_previa_documentos ?? [];
                if (($previas[$nombre]['ruta'] ?? null) !== $ruta) {
                    continue;
                }
                $fecha = $previas[$nombre]['comprobado_en'] ?? null;
                if (($firmas[$nombre] ?? null) === 'validando' && is_string($fecha)
                    && \Illuminate\Support\Carbon::parse($fecha)->diffInSeconds(now(), true) < 180) {
                    continue;
                }
                $token = (string) Str::uuid();
                $previas[$nombre]['verificacion_id'] = $token;
                $previas[$nombre]['firma_verificada'] = false;
                $previas[$nombre]['comprobado_en'] = now()->toIso8601String();
                $modelo->validacion_previa_documentos = $previas;
                $firmas[$nombre] = 'validando';
                $pendientes[$nombre] = ['ruta' => $ruta, 'token' => $token];
            }
            $modelo->forceFill(['firmas_electronicas' => $firmas])->save();
            $this->firmasElectronicas = $firmas;
            return $pendientes;
        });
        foreach ($pendientes as $nombre => $pendiente) {
            $this->dispatch('firma-actualizada', nombre: $nombre, estado: 'validando');
            try {
                VerificarFirmaDocumentoJob::dispatch($this->solicitudId, $nombre, $pendiente['ruta'], $pendiente['token']);
            } catch (\Throwable $error) {
                report($error);
                (new VerificarFirmaDocumentoJob($this->solicitudId, $nombre, $pendiente['ruta'], $pendiente['token']))->failed($error);
            }
        }
    }

    public function actualizarFirmas(): void
    {
        if ($this->paso !== 3 || $this->solicitudId === null) {
            return;
        }
        $this->prepararRevisionesHistoricas();
        $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->where('investigador_id', (string) auth()->id())->first();
        if ($modelo === null) {
            return;
        }
        $this->documentosCargados = $modelo->documentos_cargados ?? [];
        $this->nombresArchivosOriginales = $modelo->nombres_archivos_originales ?? [];
        $this->analisisDocumentalCompletado = $modelo->extraccion_estado === 'completada';
        $nuevasFirmas = $modelo->firmas_electronicas ?? [];
        $nuevasValidaciones = $modelo->validacion_archivos ?? [];
        foreach ($nuevasValidaciones as $nombre => $estado) {
            $mensaje = $modelo->validacion_previa_documentos[$nombre]['mensaje']
                ?? ($estado === 'revision_fallida' ? 'No se pudo leer y confirmar el tipo del PDF. Elimina el archivo y vuelve a cargarlo.'
                    : 'El PDF no supera la comprobación de contenido.');
            if (($this->validacionArchivos[$nombre] ?? null) !== $estado) {
                $this->dispatch('archivo-validado', nombre: $nombre, estado: $estado, mensaje: $mensaje);
                if ($estado === 'valido') unset($this->rechazosDocumentos[$nombre]);
            }
            if (! in_array($estado, ['valido', 'analizando'], true)) {
                $this->rechazosDocumentos[$nombre] = ['estado' => $estado, 'mensaje' => $mensaje];
            }
        }
        $this->validacionArchivos = $nuevasValidaciones;
        foreach ($nuevasFirmas as $nombre => $estado) {
            if (($this->firmasElectronicas[$nombre] ?? null) !== $estado) {
                $this->dispatch('firma-actualizada', nombre: $nombre, estado: $estado);
            }
        }
        $this->firmasElectronicas = $nuevasFirmas;
    }

    private function prepararRevisionesHistoricas(): void
    {
        $pendientes = DB::transaction(function (): array {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())->lockForUpdate()->first();
            if ($modelo === null) return [];
            $previas = $modelo->validacion_previa_documentos ?? [];
            $estados = $modelo->validacion_archivos ?? [];
            $firmas = $modelo->firmas_electronicas ?? [];
            $pendientes = [];
            foreach (($modelo->documentos_cargados ?? []) as $nombre => $ruta) {
                if (! in_array($nombre, $modelo->documentos_requeridos ?? [], true)
                    || ($previas[$nombre]['ruta'] ?? null) === $ruta) continue;
                $previas[$nombre] = ['ruta' => $ruta, 'firma_verificada' => false];
                $estados[$nombre] = 'analizando';
                unset($firmas[$nombre]);
                $pendientes[$nombre] = $ruta;
            }
            if ($pendientes !== []) {
                $metadata = $modelo->extraccion_metadatos ?? [];
                unset($metadata['ejecucion_id'], $metadata['confirmacion_humana']);
                $modelo->forceFill(['validacion_previa_documentos' => $previas,
                    'validacion_archivos' => $estados, 'firmas_electronicas' => $firmas,
                    'extraccion_estado' => 'pendiente', 'extraccion_metadatos' => $metadata])->save();
                $this->extraccionProcesando = false;
            }
            return $pendientes;
        });
        foreach ($pendientes as $nombre => $ruta) {
            try { ClasificarDocumentoCargadoJob::dispatch($this->solicitudId, $nombre, $ruta); }
            catch (\Throwable $error) {
                (new ClasificarDocumentoCargadoJob($this->solicitudId, $nombre, $ruta))->failed($error);
            }
        }
    }

    /**
     * Verifica el estado de la extracción asíncrona.
     */
    public function verificarExtraccion(
        ValidarDocumentacionInicialHandler $validar,
        ValidarIdentidadSolicitudHandler $validarIdentidad,
    ): void {
        if (! $this->extraccionProcesando || $this->solicitudId === null) {
            return;
        }

        $model = SolicitudDepositoEloquentModel::query()
            ->whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->first();

        if ($model === null) {
            return;
        }

        $this->documentosProcesados = $model->documentos_procesados ?? [];

        $segundosTranscurridos = $this->extraccionIniciadaEn > 0
            ? now()->timestamp - $this->extraccionIniciadaEn
            : 0;

        // Queue nunca arrancó el job (estado sigue null tras 45 s).
        if (in_array($model->extraccion_estado, [null, 'en_cola'], true) && $segundosTranscurridos > 45) {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_cola';
            $this->avanzarDesdeFalloExtraccion(
                'El análisis todavía no pudo iniciar. Conservamos tus archivos; vuelve a intentarlo en unos minutos.',
                'error_cola',
            );

            return;
        }

        // Job arrancó pero el worker murió a mitad (estado 'procesando' por más de 5 min).
        if ($model->extraccion_estado === 'procesando' && $segundosTranscurridos > 300) {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_cola';
            $this->avanzarDesdeFalloExtraccion(
                'El análisis excedió el tiempo disponible. Conservamos tus archivos; vuelve a intentarlo.',
                'error_cola',
            );

            return;
        }

        if ($model->extraccion_estado === 'error_modelo') {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_modelo';
            $this->metadatosExtraccion = $model->extraccion_metadatos ?? [];
            $this->avanzarDesdeFalloExtraccion(
                $this->mensajeFalloExtraccion('El motor local de lectura no está disponible temporalmente. Conservamos tus archivos; vuelve a intentar el análisis.'),
                'error_modelo',
            );

            return;
        }

        if ($model->extraccion_estado === 'fallida') {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_procesamiento';
            $this->metadatosExtraccion = $model->extraccion_metadatos ?? [];
            $this->avanzarDesdeFalloExtraccion(
                $this->mensajeFalloExtraccion('El análisis se interrumpió. Conservamos tus archivos; vuelve a intentar.'),
                'error_procesamiento',
            );

            return;
        }

        if ($model->extraccion_estado === 'completada') {
            $this->extraccionProcesando = false;

            $this->metadatosExtraccion = $model->extraccion_metadatos ?? [];
            $this->aplicarResultadosDocumentales($this->metadatosExtraccion);
            if ($this->estadoValidacionContenido === 'rechazado' && ! $this->revisionPreviaFavorableVigente()) {
                $this->addError(
                    'documentos',
                    'Los archivos no forman un expediente regulatorio coherente. Corrige los documentos indicados para continuar.',
                );

                return;
            }

            $outputValidar = ($validar)(new ValidarDocumentacionInicialInput(
                solicitudId: $this->solicitudId,
                provinciaOrigen: $this->provincia ?: null,
                documentosAdjuntos: $this->documentosCargados,
            ));
            $this->estadoDocumental = $outputValidar->estadoDocumental->value;

            $nombreEnDoc = $model->nombre_investigador_documento;
            if ($nombreEnDoc !== null) {
                $this->nombreEnDocumento = $nombreEnDoc;
                $outputId = ($validarIdentidad)(new ValidarIdentidadSolicitudInput(
                    solicitudId: $this->solicitudId,
                    nombrePerfil: auth()->user()->name,
                    nombreEnDocumento: $nombreEnDoc,
                ));
                $this->resultadoIdentidad = $outputId->resultado->value;
                $this->cartaDelegacionRequerida = $outputId->resultado === ResultadoValidacionIdentidad::DiscrepanciaTercero;
            }

            $this->datosExtraidos = $this->construirDatosExtraidos($model);
            $this->datosFaltantes = $model->datos_faltantes ?? [];
            $this->datosIngresadosManualmente = $model->datos_ingresados_manualmente ?? [];

            // Eliminar faltantes obsoletos que ya no pertenecen al flujo actual.
            $this->datosFaltantes = array_values(
                array_filter($this->datosFaltantes, fn ($campo) => array_key_exists($campo, $this->datosExtraidos))
            );

            $this->firmasElectronicas = $model->firmas_electronicas ?? [];
            $this->analisisDocumentalCompletado = true;
            $this->persistirEstadoWizard();
        }
    }

    /**
     * Avanza al paso 4 cuando la extracción falló, dejando todos los campos
     * vacíos para que el usuario los complete manualmente.
     */
    private function avanzarDesdeFalloExtraccion(
        ?string $mensaje = null,
        string $estado = 'error_procesamiento',
    ): void {
        $regulatorios = [
            'Copia de la autorización de recolección (MAE)',
            'Copia del permiso de movilización',
        ];
        if (array_intersect($regulatorios, $this->documentosRequeridos) !== []) {
            $this->estadoValidacionContenido = $estado;
            $this->erroresDocumentales = [
                $mensaje ?? 'No fue posible procesar los documentos regulatorios. Conservamos tus archivos; vuelve a intentar.',
            ];
            $this->addError('documentos', $this->erroresDocumentales[0]);

            return;
        }

        if ($this->tipoTramite === TipoTramite::Deposito->value) {
            if ($this->origenRecoleccion === 'Exterior (Extranjero)') {
                $this->datosExtraidos = [
                    'N.º Investigación' => null,
                    'Grupo Animal' => null,
                    'Administración Política' => null,
                    'Localidad' => null,
                    'N.º Individuos' => null,
                ];
            } else {
                $this->datosExtraidos = [
                    'N.º Permiso Recolección' => null,
                    'N.º Permiso Movilización' => null,
                    'Grupo Animal' => null,
                    'Provincia' => null,
                    'Localidad' => null,
                    'N.º Individuos' => null,
                    'N.º Morfoespecies' => null,
                    'N.º Lotes' => null,
                ];
            }
            $this->datosFaltantes = array_keys($this->datosExtraidos);
        } else {
            $this->datosExtraidos = [
                'Grupo Animal' => null,
                'Origen Donación' => null,
                'N.º Individuos' => null,
                'N.º Morfoespecies' => null,
                'N.º Lotes' => null,
            ];
            $this->datosFaltantes = ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'];
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 3]));
        $this->paso = 4;
        $this->persistirEstadoWizard();
        $this->avisarDatosPendientes();
    }

    private function avisarDatosPendientes(): void
    {
        if ($this->datosFaltantes !== []) {
            $this->mostrarToast('Completa los datos pendientes: '.implode(', ', $this->datosFaltantes).'.', 'error');
        }
    }

    private function mensajeFalloExtraccion(string $predeterminado): string
    {
        $fallo = $this->metadatosExtraccion['fallo_extraccion'] ?? null;
        $mensaje = is_array($fallo) ? ($fallo['mensaje_usuario'] ?? null) : null;

        return is_string($mensaje) && trim($mensaje) !== ''
            ? trim($mensaje)
            : $predeterminado;
    }

    // ── Paso 4 ────────────────────────────────────────────────────────────────────

    public function validarIdentidad(ValidarIdentidadSolicitudHandler $handler): void
    {
        $this->validate(
            ['nombreEnDocumento' => 'required|string|max:255'],
            ['nombreEnDocumento.required' => 'Ingresa el nombre tal como aparece en el documento.']
        );

        $output = ($handler)(new ValidarIdentidadSolicitudInput(
            solicitudId: $this->solicitudId,
            nombrePerfil: auth()->user()->name,
            nombreEnDocumento: $this->nombreEnDocumento,
        ));

        $this->resultadoIdentidad = $output->resultado->value;
        $this->cartaDelegacionRequerida = $output->resultado === ResultadoValidacionIdentidad::DiscrepanciaTercero;
    }

    public function resetearValidacionIdentidad(): void
    {
        $this->resultadoIdentidad = '';
        $this->nombreEnDocumento = '';
        $this->cartaDelegacionRequerida = false;
        $this->resetValidation(['nombreEnDocumento', 'identidad']);
    }

    public function iniciarEdicionDato(string $campo): void
    {
        $this->datosEnEdicion[$this->claveSegura($campo)] = $this->datosExtraidos[$campo] ?? '';
    }

    public function abrirEditorManual(string $campo): void
    {
        if (! in_array($campo, ['Cargo', 'Institución'], true) && ! array_key_exists($campo, $this->datosExtraidos)) {
            return;
        }

        $this->resetValidation(['valorEditorManual']);
        $this->campoEditorManual = $campo;
        $this->localidadEspecifica = '';
        $this->valorEditorManual = match ($campo) {
            'Cargo' => $this->cargoConsultor,
            'Institución' => $this->institucionConsultor,
            default => (string) ($this->datosExtraidos[$campo] ?? ''),
        };
        $this->modal('editar-dato-deposito')->show();
    }

    public function guardarEditorManual(CompletarDatosManualesHandler $handler): void
    {
        $campo = $this->campoEditorManual;
        if (! in_array($campo, ['Cargo', 'Institución'], true) && ! array_key_exists($campo, $this->datosExtraidos)) {
            return;
        }

        $valor = trim($this->valorEditorManual);
        if ($campo === 'Localidad' && $valor === '__OTRA__') {
            $nombreLugar = trim($this->localidadEspecifica);
            if ($nombreLugar === '' || mb_strlen($nombreLugar) > 140) {
                $this->addError('localidadEspecifica', 'Ingresa el nombre de la localidad (máximo 140 caracteres).');

                return;
            }
            $valor = "{$nombreLugar}, provincia {$this->provincia}, Ecuador";
        }
        $limite = $campo === 'Cargo' ? 120 : ($campo === 'Institución' ? 160 : 255);
        if ($valor === '' || mb_strlen($valor) > $limite) {
            $this->addError('valorEditorManual', "Ingresa un valor de hasta {$limite} caracteres.");

            return;
        }

        if ($campo === 'Institución' && ! DB::table('usuarios.instituciones_catalogo')
            ->where('nombre', $valor)->where('activo', true)->exists()) {
            $this->addError('valorEditorManual', 'Selecciona una institución de la lista.');

            return;
        }

        if ($campo === 'Provincia' && $this->provincia !== '' && $valor !== $this->provincia) {
            $this->addError('valorEditorManual', "Selecciona {$this->provincia}, la provincia indicada en la zona de recolección.");

            return;
        }

        if ($campo === 'Localidad' && (CatalogoLocalidadesEcuador::buscar($this->provincia, $this->localidadOrigenCodigo) === null
            || ($this->valorEditorManual !== '__OTRA__' && ! in_array($valor, $this->localidadesDisponibles(), true)))) {
            $this->addError('valorEditorManual', 'Selecciona una localidad de la provincia de recolección.');

            return;
        }

        if ($campo === 'Cargo' || $campo === 'Institución') {
            $atributo = $campo === 'Cargo' ? 'cargo' : 'institucion';
            auth()->user()->update([$atributo => $valor]);
            if ($campo === 'Cargo') {
                $this->cargoConsultor = $valor;
            } else {
                $this->institucionConsultor = $valor;
            }
        } else {
            $clave = $this->claveSegura($campo);
            $this->datosEnEdicion[$clave] = $valor;
            $this->guardarDatoFaltante($campo, $handler);
            if ($this->getErrorBag()->has("datosEnEdicion.{$clave}")) {
                $this->addError('valorEditorManual', $this->getErrorBag()->first("datosEnEdicion.{$clave}"));

                return;
            }
        }

        $this->campoEditorManual = '';
        $this->valorEditorManual = '';
        $this->localidadEspecifica = '';
        $this->modal('editar-dato-deposito')->close();
        if ($this->datosFaltantes === [] && trim($this->cargoConsultor) !== '' && trim($this->institucionConsultor) !== '') {
            $this->mostrarToast('Datos completos. Puedes continuar.', 'success');
        }
    }

    /**
     * Cancela la edición de un dato faltante.
     *
     * @param  string  $campo  El nombre del campo.
     */
    public function cancelarEdicionDato(string $campo): void
    {
        unset($this->datosEnEdicion[$this->claveSegura($campo)]);
    }

    public function guardarDatoFaltante(string $campo, CompletarDatosManualesHandler $handler): void
    {
        $clave = $this->claveSegura($campo);
        $valor = $this->datosEnEdicion[$clave] ?? '';

        if (empty($valor)) {
            $this->addError("datosEnEdicion.{$clave}", 'El valor no puede estar vacío.');

            return;
        }

        if ($campo === 'Provincia' && ! in_array($valor, array_column(CatalogoTerritorialEcuador::provincias(), 'nombre'), true)) {
            $this->addError("datosEnEdicion.{$clave}", 'Selecciona una provincia del catálogo.');

            return;
        }

        $camposCuantitativos = ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'];
        if (in_array($campo, $camposCuantitativos, true)
            && (! ctype_digit((string) $valor) || (int) $valor < (in_array($campo, ['N.º Individuos', 'N.º Lotes'], true) ? 1 : 0))) {
            $this->addError("datosEnEdicion.{$clave}", in_array($campo, ['N.º Individuos', 'N.º Lotes'], true)
                ? 'Ingresa un número entero mayor que cero.'
                : 'Ingresa un número entero mayor o igual a cero.');

            return;
        }

        $output = ($handler)(new CompletarDatosManualesInput(
            solicitudId: $this->solicitudId,
            campo: $campo,
            valor: $valor,
        ));

        $this->datosExtraidos[$campo] = $valor;
        $this->datosFaltantes = array_values(
            array_filter($this->datosFaltantes, fn (string $c) => $c !== $campo)
        );
        if (! in_array($campo, $this->datosIngresadosManualmente, true)) {
            $this->datosIngresadosManualmente[] = $campo;
        }
        unset($this->datosEnEdicion[$clave]);
        $this->invalidarConfirmacionExtraccion();
        $this->invalidarFirmaSolicitud();
    }

    public function guardarPasoCuatro(): void
    {
        foreach ($this->documentosRequeridos as $documento) {
            if (isset($this->documentosCargados[$documento])
                && (($this->validacionArchivos[$documento] ?? '') !== 'valido'
                    || ! in_array($this->firmasElectronicas[$documento] ?? '', ['firmado', 'firmado_sin_revocacion'], true))) {
                $this->mostrarToast('La firma de '.$documento.' debe verificarse antes de continuar.', 'error');

                return;
            }
        }
        $usuario = auth()->user();
        $cargo = trim($this->cargoConsultor);
        $institucion = trim($this->institucionConsultor);
        if ($cargo === '' || $institucion === '' || mb_strlen($cargo) > 120 || mb_strlen($institucion) > 160) {
            $this->mostrarToast('Ingresa un cargo y una institución válidos antes de continuar.', 'error');

            return;
        }

        if (! DB::table('usuarios.instituciones_catalogo')->where('nombre', $institucion)->where('activo', true)->exists()) {
            $this->mostrarToast('Selecciona una institución disponible en la lista.', 'error');

            return;
        }

        $usuario->update(['cargo' => $cargo, 'institucion' => $institucion]);

        if (! empty($this->datosFaltantes)) {
            $this->avisarDatosPendientes();

            return;
        }

        if ($this->origenRecoleccion === 'Nacional (Ecuador)') {
            $provinciaDato = trim((string) ($this->datosExtraidos['Provincia'] ?? ''));
            if ($provinciaDato !== '' && $provinciaDato !== $this->provincia) {
                $this->mostrarToast('La provincia del material debe coincidir con la zona de recolección.', 'error');

                return;
            }

        }

        // Validar que cada número de permiso ingresado tenga su documento de respaldo
        $permisosConDocumento = [
            'N.º Permiso Recolección' => 'Copia de la autorización de recolección (MAE)',
            'N.º Permiso Movilización' => 'Copia del permiso de movilización',
        ];

        foreach ($permisosConDocumento as $campo => $documento) {
            $tieneNumero = ! empty($this->datosExtraidos[$campo] ?? null);
            $tieneDocumento = isset($this->documentosCargados[$documento]);

            if ($tieneNumero && ! $tieneDocumento) {
                $this->mostrarToast(
                    "Ingresaste el {$campo} pero no adjuntaste el documento «{$documento}». Regresa al paso anterior y cárgalo.",
                    'error'
                );

                return;
            }
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 4, 5]));
        $this->paso = 6;
        $this->persistirEstadoWizard();
        $this->dispatch('close-toast');
    }

    public function guardarPasoFirmas(): void
    {
        $sinVerificar = array_filter(array_intersect_key($this->firmasElectronicas, array_flip($this->documentosRequeridos)), fn ($estado) => ! in_array($estado, ['firmado', 'firmado_sin_revocacion'], true));
        if (! empty($sinVerificar)) {
            $this->mostrarToast('Revisa el motivo de validación de cada firma antes de continuar.', 'error');

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 5]));
        $this->paso = 6;
        $this->persistirEstadoWizard();
    }

    public function volverADocumentos(): void
    {
        $this->paso = 3;
        $this->analisisDocumentalCompletado = false;
        $this->firmasElectronicas = [];
        $this->persistirEstadoWizard();
    }

    public function guardarPasoIdentidad(): void
    {
        if (empty($this->resultadoIdentidad)) {
            $this->mostrarToast('Valida la identidad del solicitante.', 'warning');

            return;
        }

        if ($this->cartaDelegacionRequerida && ! isset($this->documentosCargados['Carta de delegación / justificación de tercero'])) {
            $this->mostrarToast('Adjunta la Carta de Delegación.', 'warning');

            return;
        }

        $this->prepararRegistroNativoDesdeExpediente();

        $this->registrarConfirmacionExtraccion();
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 6]));
        $this->paso = 7;
        $this->persistirEstadoWizard();
    }

    private function prepararRegistroNativoDesdeExpediente(): void
    {
        $this->registroNativo = array_replace($this->registroNativo, [
            'identifiedBy' => auth()->user()->name,
            'recordedBy' => auth()->user()->name,
            'dateIdentified' => now()->toDateString(),
            'verbatimLocality' => (string) ($this->datosExtraidos['Localidad'] ?? $this->localidad),
            'researchPermit' => (string) ($this->datosExtraidos['N.º Permiso Recolección'] ?? 'No aplica — expediente justificado'),
            'transportPermit' => (string) ($this->datosExtraidos['N.º Permiso Movilización'] ?? 'No aplica — expediente justificado'),
            'recordNumber' => (string) ($this->muestrasDetectadas[0]['recordNumber'] ?? $this->numeroSolicitud.'-001'),
            'stateProvince' => (string) ($this->datosExtraidos['Provincia'] ?? ''),
        ]);
    }

    // ── Paso 5 – Matriz de especies ─────────────────────────────────────────────

    public function updatedArchivoMatriz(): void
    {
        if ($this->archivoMatriz === null) {
            return;
        }

        $this->validate(
            ['archivoMatriz' => 'file|mimes:xlsx|max:10240'],
            [
                'archivoMatriz.mimes' => 'Solo se admiten archivos .xlsx',
                'archivoMatriz.max' => 'El archivo no debe superar los 10 MB.',
            ]
        );

        $this->errorMatriz = '';
        $this->camposObligatoriosVacios = [];
        $this->archivoMatrizNombre = $this->archivoMatriz->getClientOriginalName();

        // Si ya existe una matriz para esta solicitud, eliminarla antes de crear la nueva
        if ($this->matrizId) {
            MatrizEspeciesEloquentModel::where('id', $this->matrizId)
                ->where('solicitud_id', $this->solicitudId)
                ->delete();
            $this->matrizId = null;
        }

        $parseado = $this->parsearXlsx($this->archivoMatriz);
        $campos = $parseado['campos'];
        $registros = $parseado['registros'];

        $this->camposDwCPresentes = $campos;

        $catalogo = app(CatalogoCuraduriaPort::class);
        $this->camposDwCCriticos = $catalogo->camposCriticos($this->solicitudId ?? '');
        $this->camposDwCRecomendados = $catalogo->camposRecomendados($this->solicitudId ?? '');

        $cargar = app(CargarMatrizEspeciesHandler::class);

        try {
            $output = ($cargar)(new CargarMatrizEspeciesInput(
                solicitudId: $this->solicitudId,
                camposDwCPresentes: array_fill_keys($campos, true),
                camposCriticos: $this->camposDwCCriticos,
                camposRecomendados: $this->camposDwCRecomendados,
                registros: $registros,
            ));
        } catch (CamposDwCFaltantesException $e) {
            $this->errorMatriz = $e->getMessage();
            $this->camposDwCRecomendadosFaltantes = array_values(array_filter(
                $this->camposDwCRecomendados,
                fn (string $campo) => ! in_array($campo, $this->camposDwCPresentes, true)
            ));
            $this->matrizCargada = true;

            return;
        } catch (CamposObligatoriosVaciosException $e) {
            // Campos obligatorios (críticos) con celdas vacías: se bloquea la carga
            // y se listan las filas/campos para que el depositante los complete.
            $this->errorMatriz = $e->getMessage();
            $this->camposObligatoriosVacios = $e->violaciones();
            $this->matrizCargada = true;

            return;
        }

        $this->matrizId = $output->matrizId;
        $this->estadoMatriz = $output->estadoMatriz->value;
        $this->validacionTipograficaAplicada = $output->validacionTipograficaAplicada;
        $this->totalRegistros = $output->totalRegistros;
        $this->camposDwCRecomendadosFaltantes = $output->camposRecomendadosFaltantes;
        $this->matrizCargada = true;
        $this->registrosMatriz = $registros;

        $repo = app(MatrizEspeciesRepositoryInterface::class);
        $matriz = $repo->buscarPorId(MatrizEspeciesId::from($this->matrizId));

        $this->poblarEstadosRegistros($matriz, $registros);
        $this->invalidarFirmaSolicitud();
        $this->persistirEstadoWizard();
    }

    /** Busca taxones controlados primero en PostgreSQL y completa con GBIF Species. */
    public function updatedBusquedaTaxon(): void
    {
        $consulta = trim($this->busquedaTaxon);
        $this->opcionesTaxones = [];
        if (($this->taxonSeleccionado['nombre'] ?? null) !== $consulta) {
            $this->taxonSeleccionado = [];
            $this->registroNativo['scientificName'] = '';
        }
        if (mb_strlen($consulta) < 3) {
            return;
        }

        $patronConsulta = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $consulta);
        $locales = TaxonEloquentModel::query()
            ->where('nombre_cientifico', 'ilike', '%'.$patronConsulta.'%')
            ->where('estado', 'activo')
            ->orderBy('nombre_cientifico')
            ->limit(8)
            ->get(['id', 'nombre_cientifico', 'rango', 'autor', 'epiteto_infraespecifico'])
            ->map(fn ($taxon): array => [
                'nombre' => $taxon->nombre_cientifico,
                'rango' => $taxon->rango,
                'fuente' => 'Catálogo EPN',
                'taxonID' => 'EPN:'.$taxon->id,
                'scientificNameAuthorship' => $taxon->autor,
                'infraspecificEpithet' => $taxon->epiteto_infraespecifico,
            ])->all();

        $remotos = $locales !== [] ? [] : Cache::remember('gbif-search:'.md5(mb_strtolower($consulta)), now()->addDays(7), function () use ($consulta): array {
            try {
                return Http::timeout(8)->get('https://api.gbif.org/v1/species/search', [
                    'q' => $consulta,
                    'kingdom' => 'Animalia',
                    'limit' => 10,
                ])->throw()->collect('results')
                    ->filter(fn ($item): bool => ($item['taxonomicStatus'] ?? null) === 'ACCEPTED')
                    ->map(fn ($item): array => [
                        'nombre' => (string) ($item['canonicalName'] ?? $item['scientificName'] ?? ''),
                        'scientificName' => (string) ($item['scientificName'] ?? $item['canonicalName'] ?? ''),
                        'rango' => mb_strtolower((string) ($item['rank'] ?? 'taxón')),
                        'fuente' => 'GBIF Backbone',
                        'taxonID' => isset($item['key']) ? 'https://www.gbif.org/species/'.$item['key'] : null,
                        'gbifKey' => $item['key'] ?? null,
                        'acceptedUsageKey' => $item['acceptedKey'] ?? $item['key'] ?? null,
                        'parentKey' => $item['parentKey'] ?? null,
                        'acceptedNameUsage' => $item['accepted'] ?? $item['scientificName'] ?? null,
                        'acceptedNameUsageID' => (isset($item['acceptedKey']) || isset($item['key']))
                            ? 'https://www.gbif.org/species/'.($item['acceptedKey'] ?? $item['key'])
                            : null,
                        'nameAccordingTo' => 'GBIF Backbone Taxonomy',
                        'nameAccordingToID' => 'https://www.gbif.org/species/search',
                        'taxonomicStatus' => $item['taxonomicStatus'] ?? null,
                        'kingdom' => $item['kingdom'] ?? null,
                        'phylum' => $item['phylum'] ?? null,
                        'class' => $item['class'] ?? null,
                        'order' => $item['order'] ?? null,
                        'family' => $item['family'] ?? null,
                        'genus' => $item['genus'] ?? null,
                        'specificEpithet' => $item['specificEpithet'] ?? null,
                        'scientificNameAuthorship' => $item['authorship'] ?? null,
                        'respuestaFuente' => $item,
                    ])->filter(fn (array $item): bool => $item['nombre'] !== '')
                    ->values()->all();
            } catch (\Throwable) {
                return [];
            }
        });

        $unicos = [];
        foreach ([...$locales, ...$remotos] as $opcion) {
            $unicos[mb_strtolower($opcion['nombre'])] ??= $opcion;
        }
        $this->opcionesTaxones = array_slice(array_values($unicos), 0, 12);
        $this->persistirTaxonesGbif($this->opcionesTaxones);
    }

    public function seleccionarTaxon(string $nombre): void
    {
        $opcion = collect($this->opcionesTaxones)->first(
            fn (array $opcion): bool => $opcion['nombre'] === $nombre
        );
        if (! is_array($opcion)) {
            $this->addError('registroNativo.scientificName', 'Selecciona un taxón de la lista EPN/GBIF.');

            return;
        }

        $this->registroNativo['scientificName'] = $nombre;
        $this->taxonSeleccionado = $opcion;
        $this->busquedaTaxon = $nombre;
        $this->opcionesTaxones = [];
        $this->resetErrorBag('registroNativo.scientificName');
    }

    public function agregarRegistroNativo(): void
    {
        $this->validate([
            'registroNativo.scientificName' => ['required', 'string', 'max:255'],
            'registroNativo.recordNumber' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z]{2,8}(?:-?[A-Za-z0-9]{1,16}){1,4}$/'],
            'registroNativo.origin' => ['required', 'in:research,consulting'],
            'registroNativo.identifiedBy' => ['required', 'string', 'max:255'],
            'registroNativo.dateIdentified' => ['required', 'date'],
            'registroNativo.researchPermit' => ['required', 'string', 'max:255'],
            'registroNativo.transportPermit' => ['required', 'string', 'max:255'],
            'registroNativo.decimalLatitude' => ['required', 'numeric', 'between:-90,90'],
            'registroNativo.decimalLongitude' => ['required', 'numeric', 'between:-180,180'],
            'registroNativo.verbatimLocality' => ['required', 'string', 'max:500'],
            'registroNativo.country' => ['required', 'string', 'max:120'],
            'registroNativo.eventDate' => ['required', 'date'],
            'registroNativo.recordedBy' => ['required', 'string', 'max:255'],
            'registroNativo.individualCount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'registroNativo.samplingProtocol' => ['required', 'string', 'max:120'],
            'registroNativo.preparations' => ['required', 'in:dry_pin,ethanol,slide,other'],
        ]);

        if (($this->taxonSeleccionado['nombre'] ?? null) !== $this->registroNativo['scientificName']) {
            $this->addError('registroNativo.scientificName', 'Busca y selecciona el nombre científico desde EPN/GBIF.');

            return;
        }

        if (collect($this->registrosNativos)->contains(
            fn (array $registro): bool => $registro['recordNumber'] === $this->registroNativo['recordNumber']
                && mb_strtolower((string) ($registro['scientificName'] ?? ''))
                    === mb_strtolower((string) $this->registroNativo['scientificName'])
        )) {
            $this->addError('registroNativo.recordNumber', 'Ese taxón ya fue agregado para el mismo código de campo. Un lote sí puede contener varios taxones distintos.');

            return;
        }

        $this->registrosNativos[] = $this->completarFilaPlantillaV2($this->registroNativo, $this->taxonSeleccionado);
        $this->registroNativo = array_replace($this->registroNativo, [
            'scientificName' => '',
            'recordNumber' => '',
            'dateIdentified' => '',
            'decimalLatitude' => '',
            'decimalLongitude' => '',
            'eventDate' => '',
            'individualCount' => 1,
        ]);
        $this->taxonSeleccionado = [];
        $this->busquedaTaxon = '';
        $this->opcionesTaxones = [];
        $this->seleccionarSiguienteMuestraDetectada();
    }

    public function eliminarRegistroNativo(int $indice): void
    {
        if (! array_key_exists($indice, $this->registrosNativos)) {
            return;
        }
        unset($this->registrosNativos[$indice]);
        $this->registrosNativos = array_values($this->registrosNativos);
        $this->matrizCargada = false;
        $this->estadoMatriz = '';
        $this->invalidarFirmaSolicitud();
    }

    public function guardarMatrizNativa(): void
    {
        if ($this->registrosNativos === []) {
            $this->mostrarToast('Agrega al menos un registro biológico.', 'warning');

            return;
        }

        if ($this->matrizId) {
            MatrizEspeciesEloquentModel::where('id', $this->matrizId)
                ->where('solicitud_id', $this->solicitudId)
                ->delete();
            $this->matrizId = null;
        }

        $campos = array_values(array_unique(array_merge(...array_map('array_keys', $this->registrosNativos))));
        $this->camposDwCPresentes = $campos;
        $catalogo = app(CatalogoCuraduriaPort::class);
        $this->camposDwCCriticos = $catalogo->camposCriticos($this->solicitudId ?? '');
        $this->camposDwCRecomendados = $catalogo->camposRecomendados($this->solicitudId ?? '');

        try {
            $output = app(CargarMatrizEspeciesHandler::class)(new CargarMatrizEspeciesInput(
                solicitudId: $this->solicitudId,
                camposDwCPresentes: array_fill_keys($campos, true),
                camposCriticos: $this->camposDwCCriticos,
                camposRecomendados: $this->camposDwCRecomendados,
                registros: $this->registrosNativos,
            ));
        } catch (CamposDwCFaltantesException|CamposObligatoriosVaciosException $e) {
            $this->errorMatriz = $e->getMessage();
            $this->matrizCargada = true;

            return;
        }

        $this->matrizId = $output->matrizId;
        $this->estadoMatriz = $output->estadoMatriz->value;
        $this->validacionTipograficaAplicada = $output->validacionTipograficaAplicada;
        $this->totalRegistros = $output->totalRegistros;
        $this->camposDwCRecomendadosFaltantes = $output->camposRecomendadosFaltantes;
        $this->matrizCargada = true;
        $this->errorMatriz = '';
        $this->registrosMatriz = $this->registrosNativos;

        $matriz = app(MatrizEspeciesRepositoryInterface::class)->buscarPorId(MatrizEspeciesId::from($this->matrizId));
        $this->poblarEstadosRegistros($matriz, $this->registrosNativos);
        $this->invalidarFirmaSolicitud();
        $this->persistirEstadoWizard();
    }

    /**
     * Pobla $estadosRegistros usando los IDs reales de la entidad MatrizEspecies.
     *
     * @param  array<int, array<string, mixed>>  $csvRegistros  Filas del CSV (solo en carga inicial, vacío en restauración)
     */
    private function poblarEstadosRegistros(MatrizEspecies $matriz, array $csvRegistros = []): void
    {
        $registros = $matriz->registros();

        if ($this->tipoTramite === TipoTramite::Donacion->value) {
            $i = 0;

            foreach ($registros as $id => $registro) {
                $csvRow = $csvRegistros[$i] ?? [];
                $this->estadosRegistros[$id] = [
                    'catalogoId' => $csvRow['catalogNumber'] ?? null,
                    'especieIngresada' => $registro->nombreCientifico(),
                    'estado' => 'Validado Técnicamente',
                    'especieSugerida' => null,
                    'especiesSugeridas' => [],
                    'especieCorregida' => null,
                    'noCatalogado' => false,
                    'motivoJustificacion' => null,
                    'comentarioJustificacion' => null,
                    'advertencias' => array_values(array_filter(
                        $registro->normalizaciones(),
                        fn (array $n) => ! empty($n['invalido'])
                    )),
                ];
                $i++;
            }

            return;
        }

        $nombres = array_values(array_map(fn ($r) => $r->nombreCientifico(), $registros));
        $validador = app(ValidacionTaxonomicaPort::class);
        $resultados = $validador->validarEspecies($nombres);

        $i = 0;
        $huboActualizacionEntidad = false;

        foreach ($registros as $id => $registro) {
            $csvRow = $csvRegistros[$i] ?? [];
            $validacion = $resultados[$i] ?? ['estado' => 'catalogado', 'sugerencia' => null, 'sugerencias' => []];
            $estadoEntidad = $registro->estado();

            // Si el registro ya fue resuelto (sugerencia aceptada o hallazgo justificado),
            // usar el estado de la entidad en vez de re-consultar GBIF.
            if (! $estadoEntidad->equals(EstadoRegistroEspecimen::Pendiente)) {
                $this->estadosRegistros[$id] = [
                    'catalogoId' => $csvRow['catalogNumber'] ?? null,
                    'especieIngresada' => $registro->nombreCientifico(),
                    'estado' => $estadoEntidad->value,
                    'especieSugerida' => null,
                    'especiesSugeridas' => [],
                    'especieCorregida' => $registro->nombreCorregido(),
                    'noCatalogado' => $registro->esNoCatalogado(),
                    'motivoJustificacion' => $registro->motivoJustificacion(),
                    'comentarioJustificacion' => $registro->comentarioJustificacion(),
                    'advertencias' => array_values(array_filter(
                        $registro->normalizaciones(),
                        fn (array $n) => ! empty($n['invalido'])
                    )),
                ];

                if ($registro->motivoJustificacion() !== null) {
                    $this->motivosJustificacion[$id] = $registro->motivoJustificacion();
                }

                if ($registro->comentarioJustificacion() !== null) {
                    $this->comentariosJustificacion[$id] = $registro->comentarioJustificacion();
                }
            } else {
                $estado = match ($validacion['estado']) {
                    'catalogado' => 'Validado Técnicamente',
                    'inconsistencia_tipografica' => 'Pendiente',
                    'no_catalogado' => 'Pendiente',
                    'no_verificado' => 'Validación Manual por Curaduría',
                    default => 'Pendiente',
                };

                $esNoCatalogado = $validacion['estado'] === 'no_catalogado';

                // La validación exacta debe quedar en el agregado, no solo en el
                // estado visual de Livewire: el envío vuelve a comprobar la matriz
                // desde persistencia para impedir saltarse este paso.
                if ($validacion['estado'] === 'catalogado') {
                    $matriz->validarRegistroCatalogado($id);
                    $huboActualizacionEntidad = true;
                }
                if ($validacion['estado'] === 'no_verificado') {
                    $matriz->mantenerNombreOriginal($id, 'GBIF no disponible; requiere revisión taxonómica de curaduría.');
                    $huboActualizacionEntidad = true;
                }

                // Sincronizar el flag noCatalogado en la entidad para que el guard
                // de justificar() funcione correctamente.
                if ($esNoCatalogado && ! $registro->esNoCatalogado()) {
                    $matriz->marcarRegistroNoCatalogado($id);
                    $huboActualizacionEntidad = true;
                }

                $this->estadosRegistros[$id] = [
                    'catalogoId' => $csvRow['catalogNumber'] ?? null,
                    'especieIngresada' => $registro->nombreCientifico(),
                    'estado' => $estado,
                    'especieSugerida' => $validacion['sugerencia'],
                    'especiesSugeridas' => $validacion['sugerencias'] ?? ($validacion['sugerencia'] !== null ? [$validacion['sugerencia']] : []),
                    'especieCorregida' => null,
                    'noCatalogado' => $esNoCatalogado || $validacion['estado'] === 'no_verificado',
                    'motivoJustificacion' => $validacion['estado'] === 'no_verificado'
                        ? 'GBIF no disponible; requiere revisión taxonómica de curaduría.' : null,
                    'comentarioJustificacion' => null,
                    'advertencias' => array_values(array_filter(
                        $registro->normalizaciones(),
                        fn (array $n) => ! empty($n['invalido'])
                    )),
                ];
            }

            $i++;
        }

        // Persistir los flags noCatalogado actualizados en la entidad
        if ($huboActualizacionEntidad) {
            $repo = app(MatrizEspeciesRepositoryInterface::class);
            $repo->guardar($matriz);
            $this->estadoMatriz = $matriz->estado()->value;
        }
    }

    /**
     * @return array{campos: string[], registros: array<int, array<string, mixed>>}
     */
    private function parsearXlsx(mixed $archivo): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (empty($filas)) {
            return ['campos' => [], 'registros' => []];
        }

        // Buscar la fila de cabeceras DwC: la primera que contenga 'scientificName'.
        // Esto permite que el XLSX tenga filas de título o encabezados decorativos antes.
        $indiceCabecera = null;
        foreach ($filas as $i => $fila) {
            $valores = array_map(fn ($v) => trim((string) $v), $fila);
            if (in_array('scientificName', $valores, true)) {
                $indiceCabecera = $i;
                break;
            }
        }

        if ($indiceCabecera === null) {
            return ['campos' => [], 'registros' => []];
        }

        $campos = array_map(fn ($c) => trim((string) $c), $filas[$indiceCabecera]);
        $filasData = array_slice($filas, $indiceCabecera + 1);

        $registros = [];
        foreach ($filasData as $fila) {
            $valores = array_map(fn ($v) => trim((string) $v), $fila);
            if (empty(array_filter($valores))) {
                continue;
            }
            $registro = [];
            foreach ($campos as $j => $campo) {
                if ($campo === '') {
                    continue;
                }
                $registro[$campo] = $valores[$j] ?? '';
            }
            if (trim($registro['scientificName'] ?? '') === '') {
                continue;
            }
            $registros[] = $registro;
        }

        return [
            'campos' => array_values(array_filter($campos)),
            'registros' => $registros,
        ];
    }

    /**
     * Elimina la matriz de especies cargada actualmente.
     */
    public function eliminarMatriz(): void
    {
        if ($this->matrizId) {
            MatrizEspeciesEloquentModel::where('id', $this->matrizId)
                ->where('solicitud_id', $this->solicitudId)
                ->delete();
        }

        $this->archivoMatriz = null;
        $this->archivoMatrizNombre = '';
        $this->matrizCargada = false;
        $this->matrizId = null;
        $this->estadoMatriz = '';
        $this->errorMatriz = '';
        $this->totalRegistros = 0;
        $this->camposDwCPresentes = [];
        $this->camposDwCCriticos = [];
        $this->camposDwCRecomendados = [];
        $this->camposDwCRecomendadosFaltantes = [];
        $this->registrosMatriz = [];
        $this->estadosRegistros = [];
        $this->validacionTipograficaAplicada = false;
        $this->persistirEstadoWizard();
    }

    public function aceptarSugerencia(string $registroId, string $especieCorregida): void
    {
        $handler = app(AceptarSugerenciaTaxonomicaHandler::class);

        $output = ($handler)(new AceptarSugerenciaTaxonomicaInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
            especieCorregida: $especieCorregida,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['especieCorregida'] = $especieCorregida;
        }

        $this->estadoMatriz = $output->estadoMatriz->value;
    }

    public function aceptarTodasLasSugerencias(): void
    {
        foreach ($this->estadosRegistros as $id => $reg) {
            if ($reg['estado'] === 'Pendiente' && $reg['especieSugerida'] !== null) {
                $this->aceptarSugerencia($id, $reg['especieSugerida']);
            }
        }

        $this->dispatch('modal-close', name: 'confirmar-aceptar-todas');
    }

    /** Conserva el nombre enviado y deja constancia para revisión curatorial. */
    public function mantenerNombreOriginal(string $registroId): void
    {
        $motivo = 'Nombre verificado por el investigador';
        $comentario = trim((string) ($this->comentariosJustificacion[$registroId] ?? '')) ?: null;

        $output = app(MantenerNombreTaxonomicoOriginalHandler::class)(
            new MantenerNombreTaxonomicoOriginalInput(
                solicitudId: $this->solicitudId,
                matrizId: $this->matrizId,
                registroId: $registroId,
                motivoJustificacion: $motivo,
                comentarioJustificacion: $comentario,
            ),
        );

        $this->motivosJustificacion[$registroId] = $motivo;

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['motivoJustificacion'] = $motivo;
            $this->estadosRegistros[$registroId]['comentarioJustificacion'] = $comentario;
            $this->estadosRegistros[$registroId]['noCatalogado'] = true;
        }

        $this->estadoMatriz = $output->estadoMatriz->value;
        $this->mostrarToast('Nombre original conservado. Se derivará a revisión de curaduría.', 'success');
    }

    /**
     * Justifica un hallazgo no catalogado. El depositante puede elegir un motivo,
     * escribir un comentario para curaduría, o ambos: con cualquiera de los dos basta.
     * Si solo deja comentario, se asigna el motivo neutro por defecto.
     */
    public function justificar(string $registroId): void
    {
        $motivo = trim((string) ($this->motivosJustificacion[$registroId] ?? ''));
        $comentario = trim((string) ($this->comentariosJustificacion[$registroId] ?? '')) ?: null;

        if ($motivo === '' && $comentario === null) {
            $this->mostrarToast('Elige un motivo o escribe un comentario para justificar.');

            return;
        }

        if ($motivo === '') {
            $motivo = self::MOTIVO_JUSTIFICACION_NEUTRO;
            $this->motivosJustificacion[$registroId] = $motivo;
        }

        $output = app(JustificarHallazgoTaxonomicoHandler::class)(new JustificarHallazgoTaxonomicoInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
            motivoJustificacion: $motivo,
            comentarioJustificacion: $comentario,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['motivoJustificacion'] = $motivo;
            $this->estadosRegistros[$registroId]['comentarioJustificacion'] = $comentario;
        }

        $this->estadoMatriz = $output->estadoMatriz->value;
        $this->mostrarToast('Hallazgo justificado. Se derivará a revisión de curaduría.', 'success');
    }

    /**
     * Actualiza la justificación de un registro ya derivado a curaduría (motivo +
     * comentario libre). Si se vacía el motivo, se conserva el neutro por defecto.
     */
    public function actualizarJustificacion(string $registroId): void
    {
        $motivo = trim((string) ($this->motivosJustificacion[$registroId] ?? ''));
        $comentario = trim((string) ($this->comentariosJustificacion[$registroId] ?? '')) ?: null;

        if ($motivo === '') {
            $motivo = self::MOTIVO_JUSTIFICACION_NEUTRO;
            $this->motivosJustificacion[$registroId] = $motivo;
        }

        app(CambiarJustificacionTaxonomicaHandler::class)(new CambiarJustificacionTaxonomicaInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
            nuevoMotivo: $motivo,
            comentarioJustificacion: $comentario,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['motivoJustificacion'] = $motivo;
            $this->estadosRegistros[$registroId]['comentarioJustificacion'] = $comentario;
        }

        $this->mostrarToast('Justificación actualizada.', 'success');
    }

    public function deshacerSugerencia(string $registroId): void
    {
        $handler = app(RevertirSugerenciaTaxonomicaHandler::class);

        $output = ($handler)(new RevertirSugerenciaTaxonomicaInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $especieOriginal = $this->estadosRegistros[$registroId]['especieIngresada'];

            // Re-consultar validación taxonómica para recuperar la(s) sugerencia(s)
            $validador = app(ValidacionTaxonomicaPort::class);
            $especieSugerida = null;
            $especiesSugeridas = [];

            try {
                $resultados = $validador->validarEspecies([$especieOriginal]);

                if (isset($resultados[0]) && $resultados[0]['estado'] === 'inconsistencia_tipografica') {
                    $especieSugerida = $resultados[0]['sugerencia'];
                    $especiesSugeridas = $resultados[0]['sugerencias'] ?? ($especieSugerida !== null ? [$especieSugerida] : []);
                }
            } catch (\Throwable) {
                // Si GBIF no responde, se muestra como pendiente sin sugerencia
            }

            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['especieCorregida'] = null;
            $this->estadosRegistros[$registroId]['especieSugerida'] = $especieSugerida;
            $this->estadosRegistros[$registroId]['especiesSugeridas'] = $especiesSugeridas;
        }

        $this->estadoMatriz = $output->estadoMatriz->value;
    }

    public function guardarPasoCinco(): void
    {
        if (! $this->matrizCargada) {
            $this->mostrarToast('La matriz de especímenes es obligatoria.', 'error');

            return;
        }

        if (! empty($this->errorMatriz)) {
            $this->mostrarToast('Corrige los errores de la matriz antes de continuar.', 'error');

            return;
        }

        $pendientes = array_filter(
            $this->estadosRegistros,
            fn (array $r) => in_array($r['estado'], ['Pendiente', 'No Verificado'], true)
        );

        if (! empty($pendientes)) {
            $this->mostrarToast(count($pendientes).' registro(s) requieren tu acción.', 'warning');

            return;
        }

        // Las coordenadas (latitud/longitud) deben ser válidas para poder avanzar.
        $coordenadasInvalidas = 0;
        foreach ($this->estadosRegistros as $r) {
            foreach ($r['advertencias'] ?? [] as $adv) {
                if (in_array($adv['campo'] ?? '', ['decimalLatitude', 'decimalLongitude'], true)) {
                    $coordenadasInvalidas++;
                }
            }
        }

        if ($coordenadasInvalidas > 0) {
            $this->mostrarToast(
                $coordenadasInvalidas.' coordenada(s) inválida(s). Corrige latitud/longitud en el archivo y vuelve a cargar la matriz para continuar.',
                'error',
            );

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 7]));
        $this->paso = 8;
        $this->persistirEstadoWizard();
    }

    // ── Paso 6 – Envío ───────────────────────────────────────────────────────────

    public function enviarSolicitud(EnviarSolicitudDepositoHandler $handler): void
    {
        $this->validate(
            ['declaracionAceptada' => 'accepted'],
            ['declaracionAceptada.accepted' => 'Debes aceptar la declaración para enviar la solicitud.']
        );

        $documento = SolicitudDepositoEloquentModel::query()
            ->whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->firstOrFail();
        if ($documento?->solicitud_firmada_en === null) {
            $this->addError('solicitudFirmada', 'Firma electrónicamente la solicitud oficial antes de enviarla.');

            return;
        }

        $this->solicitudFirmada = true;

        try {
            $output = ($handler)(new EnviarSolicitudDepositoInput(solicitudId: $this->solicitudId));
        } catch (SolicitudDepositoYaProcesada) {
            $persistida = SolicitudDepositoEloquentModel::query()
                ->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())
                ->firstOrFail();
            $this->presentarEstadoPersistido($persistida);

            return;
        }

        $this->estadoFinal = $output->estado->value;
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 8]));
        $this->paso = 9;
    }

    private function presentarEstadoPersistido(SolicitudDepositoEloquentModel $model): void
    {
        abort_unless($model->investigador_id === (string) auth()->id(), 404);

        $this->solicitudId = $model->id;
        $this->numeroSolicitud = $model->numero;
        $this->tipoTramite = $model->tipo_tramite;
        $this->origenRecoleccion = (string) ($model->origen_recoleccion ?? '');
        $this->situacionRegulatoria = (string) ($model->situacion_regulatoria ?? '');
        $this->provincia = (string) ($model->provincia_origen ?? '');
        $this->localidadOrigenCodigo = (string) ($model->localidad_origen_codigo ?? '');
        $this->localidad = (string) ($model->localidad ?? '');
        $this->matrizCargada = $model->matriz_id !== null;
        $this->solicitudFirmada = $model->solicitud_firmada_en !== null;
        $this->estadoFinal = $model->estado;
        $this->modoCorreccion = false;
        $this->comentarioCurador = '';
        $this->borradorRestaurado = false;
        $this->pasosCompletados = [1, 2, 3, 4, 5, 6, 7, 8];
        $this->paso = 9;
        $this->mensajeEstadoSincronizado = match ($model->estado) {
            EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value => 'Esta solicitud ya fue enviada y está pendiente de revisión.',
            EstadoSolicitudDeposito::AprobadaDocumentalmente->value => 'Esta solicitud ya fue aprobada documentalmente.',
            default => 'El expediente cambió en otra sesión. Se muestra su estado actual.',
        };
    }

    // ── Navegación ────────────────────────────────────────────────────────────────

    /**
     * Retrocede al paso anterior en el wizard.
     */
    public function retroceder(): void
    {
        if ($this->paso > 1) {
            if ($this->paso === 4) {
                $this->extraccionProcesando = false;
                $this->analisisDocumentalCompletado = true;

                if (in_array('N.º Permiso Movilización', $this->datosFaltantes, true)
                    && ! in_array('Copia del permiso de movilización', $this->documentosRequeridos, true)
                ) {
                    $this->documentosRequeridos[] = 'Copia del permiso de movilización';
                }
            }

            if ($this->paso === 8) {
                $this->declaracionAceptada = false;
            }

            $this->paso = $this->paso === 6 ? 4 : $this->paso - 1;
            $this->dispatch('close-toast');

            if ($this->paso === 2 && $this->tipoTramite === TipoTramite::Donacion->value) {
                $this->paso = 1;
            }

            $this->persistirEstadoWizard();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function mostrarToast(string $message, string $variant = 'warning'): void
    {
        $this->dispatch('show-toast', message: $message, variant: $variant);
    }

    /** @param array<string, mixed> $metadatos */
    private function aplicarResultadosDocumentales(array $metadatos): void
    {
        $validacion = is_array($metadatos['validacion_contenido'] ?? null)
            ? $metadatos['validacion_contenido']
            : [];
        $this->estadoValidacionContenido = (string) ($validacion['estado'] ?? '');
        $this->erroresDocumentales = array_values(array_filter($validacion['errores'] ?? [], 'is_string'));
        $this->advertenciasDocumentales = array_values(array_filter($validacion['advertencias'] ?? [], 'is_string'));
        $this->muestrasDetectadas = array_values(array_filter(
            $metadatos['registros_sugeridos'] ?? [],
            static fn (mixed $registro): bool => is_array($registro) && ! empty($registro['recordNumber']),
        ));
    }

    /** Una decisión humana solo resuelve incertidumbres de la misma versión de archivos. */
    private function revisionPreviaFavorableVigente(): bool
    {
        $revision = $this->metadatosExtraccion['revision_documental'] ?? [];
        if (! is_array($revision) || ($revision['estado'] ?? null) !== 'favorable') {
            return false;
        }

        $version = $revision['version_documental_persistida'] ?? $revision['version_documental'] ?? null;

        if (! is_string($version) || ! hash_equals($version, ExtraccionDatosDocumentoJob::huellaDocumental($this->documentosCargados))) {
            return false;
        }

        // Una aprobación humana no es una dispensa general: solo cubre los
        // hallazgos concretos que quedaron registrados al resolverla.
        $alcance = $revision['hallazgos_resueltos'] ?? [];
        if (! is_array($alcance) || $alcance === []) {
            return false;
        }

        return collect($this->erroresDocumentales)->every(
            static fn (string $error): bool => in_array($error, $alcance, true),
        );
    }

    /** @return array<string, mixed> */
    private function datosRevisionDocumental(string $motivo): array
    {
        return [
            'estado' => 'pendiente',
            'solicitada_por' => (string) auth()->id(),
            'solicitada_en' => now()->toIso8601String(),
            'motivo' => $motivo,
            'errores' => $this->erroresDocumentales,
            'advertencias' => $this->advertenciasDocumentales,
            'version_documental' => ExtraccionDatosDocumentoJob::huellaDocumental($this->documentosCargados),
        ];
    }

    private function registrarConfirmacionExtraccion(): void
    {
        if ($this->solicitudId === null) {
            return;
        }

        $valores = $this->datosExtraidos;
        ksort($valores);
        $json = json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $metadatos = $this->metadatosExtraccion;
        $metadatos['confirmacion_humana'] = [
            'estado' => 'confirmada',
            'usuario_id' => (string) auth()->id(),
            'confirmada_en' => now()->toIso8601String(),
            'valores_sha256' => hash('sha256', $json),
        ];

        SolicitudDepositoEloquentModel::whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->update([
                'extraccion_metadatos' => $metadatos,
            ]);
        $this->metadatosExtraccion = $metadatos;
    }

    private function invalidarConfirmacionExtraccion(): void
    {
        if ($this->solicitudId === null || $this->metadatosExtraccion === []) {
            return;
        }

        $this->metadatosExtraccion['confirmacion_humana'] = [
            'estado' => 'pendiente',
            'invalidada_en' => now()->toIso8601String(),
            'invalidada_por' => (string) auth()->id(),
        ];
        SolicitudDepositoEloquentModel::whereKey($this->solicitudId)
            ->where('investigador_id', (string) auth()->id())
            ->update([
                'extraccion_metadatos' => $this->metadatosExtraccion,
            ]);
    }

    private function persistirRetiroDocumento(string $nombre): ?string
    {
        if ($this->solicitudId === null) {
            return null;
        }

        return DB::transaction(function () use ($nombre): ?string {
            $modelo = SolicitudDepositoEloquentModel::query()
                ->whereKey($this->solicitudId)
                ->where('investigador_id', (string) auth()->id())
                ->lockForUpdate()->firstOrFail();
            $documentos = $modelo->documentos_cargados ?? [];
            $nombres = $modelo->nombres_archivos_originales ?? [];
            $ruta = $documentos[$nombre] ?? null;
            unset($documentos[$nombre], $nombres[$nombre]);
            $firmas = $modelo->firmas_electronicas ?? [];
            $validaciones = $modelo->validacion_archivos ?? [];
            unset($firmas[$nombre], $validaciones[$nombre]);
            $previas = $modelo->validacion_previa_documentos ?? [];
            unset($previas[$nombre]);
            unset($this->rechazosDocumentos[$nombre]);
            $metadatos = $modelo->extraccion_metadatos ?? [];
            $revision = $metadatos['revision_documental'] ?? null;
            if (is_array($revision) && ($revision['estado'] ?? null) !== 'invalidada') {
                $invalida = [...$revision, 'estado' => 'invalidada', 'invalidada_en' => now()->toIso8601String(), 'motivo_invalidacion' => 'El consultor eliminó documentación del expediente.'];
                $metadatos['revision_documental'] = $invalida;
                $metadatos['revision_documental_historial'] = [...($metadatos['revision_documental_historial'] ?? []), $invalida];
            }
            $modelo->forceFill([
                'documentos_cargados' => $documentos,
                'nombres_archivos_originales' => $nombres,
                'documentos_procesados' => [],
                'firmas_electronicas' => $firmas,
                'validacion_archivos' => $validaciones,
                'validacion_previa_documentos' => $previas,
                'extraccion_estado' => null,
                'extraccion_metadatos' => $metadatos,
            ])->save();
            $this->documentosCargados = $documentos;
            $this->nombresArchivosOriginales = $nombres;
            $this->firmasElectronicas = $firmas;
            $this->validacionArchivos = $validaciones;
            return is_string($ruta) ? $ruta : null;
        });
    }

    public function usarMuestraDetectada(string $codigo): void
    {
        $muestra = collect($this->muestrasDetectadas)->first(
            static fn (array $item): bool => ($item['recordNumber'] ?? null) === $codigo,
        );
        if (! is_array($muestra)) {
            return;
        }

        $this->registroNativo = array_replace($this->registroNativo, array_filter([
            'recordNumber' => $muestra['recordNumber'] ?? null,
            'researchPermit' => $muestra['researchPermit'] ?? null,
            'transportPermit' => $muestra['transportPermit'] ?? null,
            'verbatimLocality' => $muestra['verbatimLocality'] ?? null,
        ], static fn (mixed $valor): bool => is_string($valor) && trim($valor) !== ''));
    }

    private function seleccionarSiguienteMuestraDetectada(): void
    {
        $utilizados = array_column($this->registrosNativos, 'recordNumber');
        foreach ($this->muestrasDetectadas as $muestra) {
            $codigo = (string) ($muestra['recordNumber'] ?? '');
            if ($codigo !== '' && ! in_array($codigo, $utilizados, true)) {
                $this->usarMuestraDetectada($codigo);

                return;
            }
        }
    }

    public function updatedRegistroNativo(mixed $valor, string $campo): void
    {
        if ($campo !== 'country' || ! is_string($valor)) {
            return;
        }

        $seleccion = collect($this->catalogoPaises)->first(
            static fn (array $item): bool => $item['nombre'] === $valor,
        );
        if (is_array($seleccion)) {
            $this->registroNativo['countryCode'] = $seleccion['codigo'];
            $this->registroNativo['continent'] = $seleccion['continente'] === 'América del Sur'
                ? 'South America'
                : $seleccion['continente'];
        }
    }

    /** @param array<string, mixed> $fila @param array<string, mixed> $taxon @return array<string, mixed> */
    private function completarFilaPlantillaV2(array $fila, array $taxon): array
    {
        $fechaColecta = (string) ($fila['eventDate'] ?? '');
        $partesFecha = $fechaColecta !== '' ? date_parse($fechaColecta) : [];
        $nombre = (string) ($taxon['nombre'] ?? $fila['scientificName'] ?? '');
        $rango = mb_strtolower((string) ($taxon['rango'] ?? ''));
        $partesNombre = preg_split('/\s+/u', trim($nombre)) ?: [];
        $epiteto = in_array($rango, ['species', 'especie', 'subspecies', 'subespecie'], true)
            ? ($partesNombre[1] ?? null)
            : null;

        $defaults = [
            'origin' => 'research',
            'type' => 'PhysicalObject',
            'language' => 'es',
            'license' => 'https://creativecommons.org/licenses/by-nc/4.0/',
            'rightsHolder' => 'Museo de Historia Natural Gustavo Orcés V — Escuela Politécnica Nacional',
            'accessRights' => 'https://creativecommons.org/licenses/by-nc/4.0/',
            'institutionID' => 'bd33fb5b-71da-43c7-9ed0-c95cbc7a5383',
            'collectionID' => '914603d1-c523-42ef-84d4-82b9b246216a',
            'institutionCode' => 'MHNGOV',
            'collectionCode' => 'MEPN-INV',
            'datasetName' => 'Colección de Invertebrados MEPN',
            'ownerInstitutionCode' => 'EPN',
            'basisOfRecord' => 'PreservedSpecimen',
            'occurrenceStatus' => 'present',
            'kingdom' => 'Animalia',
            'nomenclaturalCode' => 'ICZN',
            // El depositante describe material propuesto. Solo el acta final firmada
            // completa la accesion y permite considerarlo parte de la coleccion.
            'disposition' => 'pending accession',
            'geodeticDatum' => 'WGS84',
            'continent' => 'South America',
            'country' => 'Ecuador',
            'countryCode' => 'EC',
            'recordCreatedBy' => auth()->user()->name.', '.now()->translatedFormat('M-Y'),
            'iptUpload' => 'no',
        ];
        $taxonomia = array_filter([
            'taxonID' => $taxon['taxonID'] ?? null,
            'scientificName' => $nombre,
            'taxonRank' => $rango,
            'kingdom' => $taxon['kingdom'] ?? 'Animalia',
            'phylum' => $taxon['phylum'] ?? null,
            'class' => $taxon['class'] ?? null,
            'order' => $taxon['order'] ?? null,
            'family' => $taxon['family'] ?? null,
            'genus' => $taxon['genus'] ?? null,
            'specificEpithet' => $epiteto,
            'infraspecificEpithet' => $taxon['infraspecificEpithet'] ?? null,
            'scientificNameAuthorship' => $taxon['scientificNameAuthorship'] ?? null,
            'acceptedNameUsage' => $taxon['acceptedNameUsage'] ?? null,
            'acceptedNameUsageID' => $taxon['acceptedNameUsageID'] ?? null,
            'nameAccordingTo' => $taxon['nameAccordingTo'] ?? null,
            'nameAccordingToID' => $taxon['nameAccordingToID'] ?? null,
            'taxonomicStatus' => isset($taxon['taxonomicStatus'])
                ? mb_strtolower((string) $taxon['taxonomicStatus'])
                : null,
        ], static fn (mixed $valor): bool => $valor !== null && $valor !== '');
        $fecha = array_filter([
            'year' => $partesFecha['year'] ?? null,
            'month' => $partesFecha['month'] ?? null,
            'day' => $partesFecha['day'] ?? null,
        ], static fn (mixed $valor): bool => is_int($valor) && $valor > 0);

        return array_filter(
            array_replace($defaults, $fila, $taxonomia, $fecha),
            static fn (mixed $valor): bool => $valor !== null && $valor !== '',
        );
    }

    /** @param list<array<string, mixed>> $opciones */
    private function persistirTaxonesGbif(array $opciones): void
    {
        try {
            if (! Schema::hasTable('recepciones.catalogo_taxones_externos')) {
                return;
            }
            $ahora = now();
            $filas = [];
            foreach ($opciones as $opcion) {
                if (($opcion['fuente'] ?? null) !== 'GBIF Backbone' || ! is_numeric($opcion['gbifKey'] ?? null)) {
                    continue;
                }
                $nombre = (string) $opcion['nombre'];
                $partes = preg_split('/\s+/u', $nombre) ?: [];
                $filas[] = [
                    'gbif_key' => (int) $opcion['gbifKey'],
                    'scientific_name' => (string) ($opcion['scientificName'] ?? $nombre),
                    'canonical_name' => $nombre,
                    'rank' => (string) $opcion['rango'],
                    'taxonomic_status' => (string) ($opcion['taxonomicStatus'] ?? 'ACCEPTED'),
                    'kingdom' => $opcion['kingdom'] ?? null,
                    'phylum' => $opcion['phylum'] ?? null,
                    'class' => $opcion['class'] ?? null,
                    'order' => $opcion['order'] ?? null,
                    'family' => $opcion['family'] ?? null,
                    'genus' => $opcion['genus'] ?? null,
                    'specific_epithet' => count($partes) >= 2 ? $partes[1] : null,
                    'accepted_usage_key' => is_numeric($opcion['acceptedUsageKey'] ?? null)
                        ? (int) $opcion['acceptedUsageKey']
                        : null,
                    'parent_key' => is_numeric($opcion['parentKey'] ?? null)
                        ? (int) $opcion['parentKey']
                        : null,
                    'taxon_id' => $opcion['taxonID'] ?? null,
                    'accepted_name_usage' => $opcion['acceptedNameUsage'] ?? null,
                    'accepted_name_usage_id' => $opcion['acceptedNameUsageID'] ?? null,
                    'name_according_to' => $opcion['nameAccordingTo'] ?? null,
                    'name_according_to_id' => $opcion['nameAccordingToID'] ?? null,
                    'respuesta_fuente' => json_encode($opcion['respuestaFuente'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sincronizado_en' => $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            if ($filas !== []) {
                DB::table('recepciones.catalogo_taxones_externos')->upsert(
                    $filas,
                    ['gbif_key'],
                    ['scientific_name', 'canonical_name', 'rank', 'taxonomic_status', 'kingdom', 'phylum', 'class', 'order', 'family', 'genus', 'specific_epithet', 'accepted_usage_key', 'parent_key', 'taxon_id', 'accepted_name_usage', 'accepted_name_usage_id', 'name_according_to', 'name_according_to_id', 'respuesta_fuente', 'sincronizado_en', 'updated_at'],
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function cargarCatalogosControlados(): void
    {
        try {
            if (Schema::hasTable('recepciones.catalogo_grupos_invertebrados')) {
                $this->catalogoGrupos = DB::table('recepciones.catalogo_grupos_invertebrados')
                    ->where('activo', true)->orderBy('orden_visual')
                    ->get(['codigo', 'nombre', 'rango_referencia as rango'])
                    ->map(static fn (object $fila): array => (array) $fila)->all();
            }
            if (Schema::hasTable('recepciones.catalogo_paises')) {
                $this->catalogoPaises = DB::table('recepciones.catalogo_paises')
                    ->where('activo', true)->orderBy('orden_visual')
                    ->get(['codigo_iso2 as codigo', 'nombre_es as nombre', 'continente'])
                    ->map(static fn (object $fila): array => (array) $fila)->all();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->catalogoGrupos = $this->catalogoGrupos ?: [
            ['codigo' => 'INSECTA', 'nombre' => 'Insectos', 'rango' => 'class'],
            ['codigo' => 'ARACHNIDA', 'nombre' => 'Arácnidos', 'rango' => 'class'],
            ['codigo' => 'CRUSTACEA', 'nombre' => 'Crustáceos', 'rango' => 'subphylum'],
            ['codigo' => 'MOLLUSCA', 'nombre' => 'Moluscos', 'rango' => 'phylum'],
            ['codigo' => 'ANNELIDA', 'nombre' => 'Anélidos', 'rango' => 'phylum'],
        ];
        $this->catalogoPaises = $this->catalogoPaises ?: [
            ['codigo' => 'EC', 'nombre' => 'Ecuador', 'continente' => 'América del Sur'],
            ['codigo' => 'CO', 'nombre' => 'Colombia', 'continente' => 'América del Sur'],
            ['codigo' => 'PE', 'nombre' => 'Perú', 'continente' => 'América del Sur'],
        ];
    }

    private function invalidarFirmaSolicitud(): void
    {
        if ($this->solicitudId === null) {
            return;
        }
        $invalidada = app(InvalidarFirmaSolicitud::class)(
            $this->solicitudId,
            (string) auth()->id(),
        );
        if (! $invalidada) {
            return;
        }
        $this->solicitudFirmada = false;
        $this->solicitudFirmaMetadata = [];
    }

    // ── Helper público para vistas ────────────────────────────────────────────────

    /**
     * Convierte el nombre de un campo en una clave segura para usar en arrays
     * de Livewire (wire:model). Los puntos y caracteres especiales en el nombre
     * serían interpretados como separadores de anidamiento por Livewire.
     */
    public function claveSegura(string $campo): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '_', $campo);
    }

    /**
     * Mapea el nombre de un documento a su correspondiente propiedad en la clase.
     *
     * @param  string  $nombre  El nombre del documento.
     *
     * @throws \InvalidArgumentException Si el nombre del documento es desconocido.
     */
    public function propiedadParaDocumento(string $nombre): string
    {
        return match ($nombre) {
            'Formato solicitud depósito' => 'archivoFormatoDeposito',
            'Formato solicitud donación' => 'archivoFormatoDonacion',
            'Copia de la autorización de recolección (MAE)' => 'archivoAutorizacionMae',
            'Copia del permiso de movilización' => 'archivoPermisoMovilizacion',
            'Documento de explicación de motivos y/o carta de justificación (institucional o personal)' => 'archivoCartaJustificacion',
            'Documento de procedencia de los especimenes',
            'Carta de procedencia firmada por el responsable de la colección de origen' => 'archivoCartaProcedencia',
            'Carta de cesión de derechos / origen lícito' => 'archivoCartaCesion',
            'Carta de delegación / justificación de tercero' => 'archivoCartaDelegacion',
            default => throw new \InvalidArgumentException("Documento desconocido: {$nombre}"),
        };
    }

    /**
     * Renderiza el componente.
     */
    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.registro-solicitud-deposito', [
            'provinciasCatalogo' => CatalogoTerritorialEcuador::provincias(),
            'localidadesOrigenCatalogo' => $this->paso === 2
                ? CatalogoLocalidadesEcuador::opciones($this->provincia, $this->busquedaLocalidadOrigen, $this->localidadOrigenCodigo) : [],
            'localidadesCatalogo' => $this->campoEditorManual === 'Localidad' ? $this->localidadesDisponibles() : [],
            'institucionesCatalogo' => DB::table('usuarios.instituciones_catalogo')
                ->where('activo', true)->orderBy('nombre')->pluck('nombre')->all(),
        ]);
    }

    /** @return list<string> */
    private function localidadesDisponibles(): array
    {
        $fila = CatalogoLocalidadesEcuador::buscar($this->provincia, $this->localidadOrigenCodigo, false);
        if ($fila === null) {
            return [];
        }

        $opciones = ["{$fila->nombre}, provincia {$this->provincia}, Ecuador"];
        if (Schema::hasTable('taxonomia.localidades')) {
            $nombres = DB::table('taxonomia.localidades')
                ->whereRaw('LOWER(state_province) = LOWER(?)', [$this->provincia])
                ->whereRaw('LOWER(municipality) = LOWER(?)', [$fila->canton])
                ->distinct()->orderBy('nombre_canonico')->limit(200)
                ->pluck('nombre_canonico');
            foreach ($nombres as $nombre) {
                $opciones[] = "{$nombre}, provincia {$this->provincia}, Ecuador";
            }
        }

        return array_values(array_unique($opciones));
    }
}
