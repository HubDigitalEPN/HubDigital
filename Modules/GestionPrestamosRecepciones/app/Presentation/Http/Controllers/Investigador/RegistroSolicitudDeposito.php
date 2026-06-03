<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
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
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica\RevertirSugerenciaTaxonomicaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica\RevertirSugerenciaTaxonomicaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposDwCFaltantesException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\LimiteAnualDepositosAlcanzado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ExtraccionDatosDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    // ── Paso 1 – Trámite ──────────────────────────────────────────────────────────

    public string $tipoTramite = '';

    public ?string $solicitudId = null;

    public string $numeroSolicitud = '';

    public bool $limiteAlcanzado = false;

    public string $mensajeLimite = '';

    public int $solicitudesPreviasDeposito = 0;

    // ── Paso 2 – Origen ───────────────────────────────────────────────────────────

    public string $origenRecoleccion = '';

    public string $situacionRegulatoria = '';

    public string $provincia = '';

    public string $localidad = '';

    // ── Paso 3 – Documentos (file uploads) ───────────────────────────────────────

    public $archivoFormatoDeposito = null;

    public $archivoFormatoDonacion = null;

    public $archivoAutorizacionMaate = null;

    public $archivoPermisoMovilizacion = null;

    public $archivoCartaJustificacion = null;

    public $archivoCartaProcedencia = null;

    public $archivoCartaCesion = null;

    public $archivoCartaDelegacion = null;

    /** @var string[] */
    public array $documentosRequeridos = [];

    /** @var array<string, string> [nombre => ruta_storage] */
    public array $documentosCargados = [];

    /** @var array<string, string> [nombre => nombre_original_archivo] */
    public array $nombresArchivosOriginales = [];

    public bool $intervencionCuratoriaActiva = false;

    public bool $extraccionProcesando = false;

    /** Timestamp Unix del momento en que se despachó el job de extracción. */
    public int $extraccionIniciadaEn = 0;

    /**
     * Tipo de advertencia cuando la extracción falla pero el flujo continúa.
     * Valores posibles: '' | 'error_modelo' | 'error_cola'
     */
    public string $advertenciaExtraccion = '';

    /** @var string[] */
    public array $documentosProcesados = [];

    // ── Paso 4 – Datos ────────────────────────────────────────────────────────────

    /** @var array<string, string|null> */
    public array $datosExtraidos = [];

    /** @var string[] */
    public array $datosFaltantes = [];

    /** @var string[] */
    public array $datosIngresadosManualmente = [];

    /** @var array<string, string> */
    public array $datosEnEdicion = [];

    public string $resultadoIdentidad = '';

    public string $nombreEnDocumento = '';

    public bool $cartaDelegacionRequerida = false;

    public string $estadoDocumental = '';

    /** @var array<string, string> [nombre_documento => estado_firma] */
    public array $firmasElectronicas = [];

    // ── Paso 5 – Matriz de especies ─────────────────────────────────────────────

    public $archivoMatriz = null;

    public ?string $matrizId = null;

    public string $estadoMatriz = '';

    public bool $validacionTipograficaAplicada = false;

    public int $totalRegistros = 0;

    /** @var string[] */
    public array $camposDwCPresentes = [];

    /** @var string[] */
    public array $camposDwCRequeridos = [];

    /** @var array<int, array<string, mixed>> */
    public array $registrosMatriz = [];

    /**
     * Estado de cada registro para la tabla visual.
     *
     * @var array<string, array{catalogoId: string|null, especieIngresada: string, estado: string, especieSugerida: string|null, especieCorregida: string|null, noCatalogado: bool, motivoJustificacion: string|null}>
     */
    public array $estadosRegistros = [];

    public string $archivoMatrizNombre = '';

    public bool $matrizCargada = false;

    public string $errorMatriz = '';

    public string $filtroTabla = 'todos';

    // ── Paso 6 – Envío ────────────────────────────────────────────────────────────

    public bool $declaracionAceptada = false;

    public string $estadoFinal = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $borrador = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->first();

        if ($borrador) {
            $this->restaurarDesdeBorrador($borrador);

            return;
        }

        $this->solicitudesPreviasDeposito = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('tipo_tramite', TipoTramite::Deposito->value)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->whereYear('created_at', (int) date('Y'))
            ->count();
    }

    public function updatedOrigenRecoleccion(): void
    {
        if ($this->origenRecoleccion === 'Exterior (Extranjero)') {
            $this->situacionRegulatoria = 'Proviene de colección foránea';
            $this->provincia = '';
        } elseif ($this->situacionRegulatoria === 'Proviene de colección foránea') {
            $this->situacionRegulatoria = '';
        }
    }

    // ── Restauración de borrador ──────────────────────────────────────────────────

    private function restaurarDesdeBorrador(SolicitudDepositoEloquentModel $model): void
    {
        $this->borradorRestaurado = true;
        $this->solicitudId = $model->id;
        $this->numeroSolicitud = $model->numero;
        $this->tipoTramite = $model->tipo_tramite;

        $pasoGuardado = $model->paso_actual ?? 1;

        // Paso 2 data
        $this->origenRecoleccion = $model->origen_recoleccion ?? '';
        $this->situacionRegulatoria = $model->situacion_regulatoria ?? '';
        $this->provincia = $model->provincia_origen ?? '';

        // Paso 3 data
        $this->documentosRequeridos = $model->documentos_requeridos ?? [];
        $this->documentosCargados = $model->documentos_cargados ?? [];
        $this->nombresArchivosOriginales = $model->nombres_archivos_originales ?? [];

        // Si hay archivos cargados, verificar que aún existen en storage
        $this->documentosCargados = array_filter(
            $this->documentosCargados,
            fn (string $ruta) => Storage::disk('public')->exists($ruta)
        );

        // Limpiar originales de documentos cuyo archivo ya no existe
        $this->nombresArchivosOriginales = array_intersect_key(
            $this->nombresArchivosOriginales,
            $this->documentosCargados
        );

        // Solo reactivar polling si estaba en paso 3 (extracción en curso).
        // En paso 4+ la extracción ya fue procesada, no necesita polling.
        if ($pasoGuardado === 3 && $model->extraccion_estado !== null) {
            $this->extraccionProcesando = true;
            $this->extraccionIniciadaEn = $model->updated_at->timestamp;
        }

        // Paso 4+ data
        if ($pasoGuardado >= 4) {
            $this->datosExtraidos = $this->construirDatosExtraidos($model);
            $this->datosFaltantes = $model->datos_faltantes ?? [];
            $this->datosIngresadosManualmente = $model->datos_ingresados_manualmente ?? [];
            $this->firmasElectronicas = $model->firmas_electronicas ?? [];
            $this->nombreEnDocumento = $model->nombre_investigador_documento ?? '';
            $this->documentosProcesados = $model->documentos_procesados ?? [];

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

        // Paso 5+ data (Matriz de especies)
        if ($pasoGuardado >= 5 && $model->matriz_id !== null) {
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
                $this->camposDwCRequeridos = $catalogo->camposRequeridos($this->solicitudId ?? '');

                $this->poblarEstadosRegistros($matriz);
            }
        }

        // Restaurar paso y pasos completados
        $this->paso = $pasoGuardado;
        $this->pasosCompletados = $this->calcularPasosCompletados($pasoGuardado);

        $this->solicitudesPreviasDeposito = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('tipo_tramite', TipoTramite::Deposito->value)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->whereYear('created_at', (int) date('Y'))
            ->count();
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

        SolicitudDepositoEloquentModel::where('id', $this->solicitudId)->update([
            'paso_actual' => $this->paso,
            'documentos_cargados' => $this->documentosCargados,
            'nombres_archivos_originales' => $this->nombresArchivosOriginales,
            'documentos_requeridos' => $this->documentosRequeridos,
            'matriz_id' => $this->matrizId,
        ]);
    }

    public function descartarBorrador(): void
    {
        if ($this->solicitudId !== null) {
            // Eliminar archivos cargados del storage
            foreach ($this->documentosCargados as $ruta) {
                Storage::disk('public')->delete($ruta);
            }

            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)->delete();
        }

        $this->reset();
        $this->borradorRestaurado = false;
        $this->solicitudesPreviasDeposito = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('tipo_tramite', TipoTramite::Deposito->value)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->whereYear('created_at', (int) date('Y'))
            ->count();
    }

    // ── Paso 1 ────────────────────────────────────────────────────────────────────

    public function avanzarPaso1(RegistrarSolicitudDepositoHandler $registrar): void
    {
        if (empty($this->tipoTramite)) {
            $this->addError('tipoTramite', 'Selecciona un tipo de trámite para continuar.');

            return;
        }

        if ($this->tipoTramite === TipoTramite::Deposito->value) {
            $conteo = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
                ->where('tipo_tramite', TipoTramite::Deposito->value)
                ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
                ->whereYear('created_at', (int) date('Y'))
                ->count();

            if ($conteo >= 3) {
                $this->limiteAlcanzado = true;
                $this->mensajeLimite = 'Has alcanzado el límite anual de 3 depósitos.';

                return;
            }
        }

        $this->limiteAlcanzado = false;
        $this->mensajeLimite = '';

        // Crear registro en BD si aún no existe
        if ($this->solicitudId === null) {
            try {
                $output = ($registrar)(new RegistrarSolicitudDepositoInput(
                    investigadorId: (string) auth()->id(),
                    tipoTramite: $this->tipoTramite,
                ));
                $this->solicitudId = $output->id;
                $this->numeroSolicitud = $output->numero;
            } catch (LimiteAnualDepositosAlcanzado $e) {
                $this->limiteAlcanzado = true;
                $this->mensajeLimite = $e->getMessage();

                return;
            }
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 1]));

        if ($this->tipoTramite === TipoTramite::Donacion->value) {
            $this->documentosRequeridos = ['Formato solicitud donación', 'Carta de cesión de derechos / origen lícito'];
            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
            $this->paso = 3;
            $this->persistirEstadoWizard();

            return;
        }

        $this->paso = 2;
        $this->persistirEstadoWizard();
    }

    // ── Paso 2 ────────────────────────────────────────────────────────────────────

    public function guardarPasoDos(
        DeterminarDocumentacionRequeridaHandler $determinar,
        ActualizarOrigenSolicitudDepositoHandler $actualizar,
    ): void {
        $rules = [
            'origenRecoleccion' => 'required|string',
            'situacionRegulatoria' => 'required|string',
        ];

        $messages = [
            'origenRecoleccion.required' => 'Selecciona la procedencia de los especímenes.',
            'situacionRegulatoria.required' => 'Selecciona la situación regulatoria.',
        ];

        if ($this->origenRecoleccion === 'Nacional (Ecuador)') {
            $rules['provincia'] = 'required|in:Pichincha,Fuera de Pichincha';
            $messages['provincia.required'] = 'Selecciona si la recolección fue dentro o fuera de Pichincha.';
            $messages['provincia.in'] = 'Selecciona si la recolección fue dentro o fuera de Pichincha.';
        }

        $this->validate($rules, $messages);

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
        ));

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
        $this->paso = 3;
        $this->persistirEstadoWizard();
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

    public function updatedArchivoAutorizacionMaate(): void
    {
        $this->registrarDocumentoCargado('archivoAutorizacionMaate', 'Copia de la autorización de recolección (MAATE)', $this->archivoAutorizacionMaate);
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
            'Carta de procedencia firmada por el responsable de la colección de origen',
            $this->archivoCartaProcedencia
        );
    }

    public function updatedArchivoCartaCesion(): void
    {
        $this->registrarDocumentoCargado('archivoCartaCesion', 'Carta de cesión de derechos / origen lícito', $this->archivoCartaCesion);
    }

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
            [$propiedad => 'file|mimes:pdf|max:20480'],
            [
                "{$propiedad}.mimes" => "Solo se aceptan archivos PDF para \"{$nombre}\".",
                "{$propiedad}.max" => 'El archivo no debe superar los 20 MB.',
            ]
        );

        $ruta = $archivo->store('depositos', 'public');
        $this->documentosCargados[$nombre] = $ruta;
        $this->nombresArchivosOriginales[$nombre] = $archivo->getClientOriginalName();

        $this->persistirEstadoWizard();
    }

    public function eliminarDocumento(string $nombre): void
    {
        if (isset($this->documentosCargados[$nombre])) {
            Storage::disk('public')->delete($this->documentosCargados[$nombre]);
            unset($this->documentosCargados[$nombre]);
            unset($this->nombresArchivosOriginales[$nombre]);
        }

        $propiedad = $this->propiedadParaDocumento($nombre);
        $this->reset($propiedad);

        $this->persistirEstadoWizard();
    }

    public function solicitarIntervencion(
        DeclararSinDocumentacionHandler $declarar,
        SolicitarIntervencionCuratoriaHandler $escalar,
    ): void {
        ($declarar)(new DeclararSinDocumentacionInput(solicitudId: $this->solicitudId));
        ($escalar)(new SolicitarIntervencionCuratoriaInput(
            solicitudId: $this->solicitudId,
            investigadorId: (string) auth()->id(),
        ));
        $this->intervencionCuratoriaActiva = true;
    }

    public function guardarPasoTres(): void
    {
        foreach ($this->documentosRequeridos as $doc) {
            if (! isset($this->documentosCargados[$doc])) {
                $this->addError('documentos', "El documento \"{$doc}\" es requerido.");

                return;
            }
        }

        if (! $this->extraccionProcesando) {
            ExtraccionDatosDocumentoJob::dispatch($this->solicitudId, $this->documentosCargados);
            $this->extraccionProcesando = true;
            $this->extraccionIniciadaEn = now()->timestamp;
        }
    }

    public function verificarExtraccion(
        ValidarDocumentacionInicialHandler $validar,
        ValidarIdentidadSolicitudHandler $validarIdentidad,
    ): void {
        if (! $this->extraccionProcesando || $this->solicitudId === null) {
            return;
        }

        $model = SolicitudDepositoEloquentModel::find($this->solicitudId);

        if ($model === null) {
            return;
        }

        $this->documentosProcesados = $model->documentos_procesados ?? [];

        $segundosTranscurridos = $this->extraccionIniciadaEn > 0
            ? now()->timestamp - $this->extraccionIniciadaEn
            : 0;

        // Queue nunca arrancó el job (estado sigue null tras 45 s).
        if ($model->extraccion_estado === null && $segundosTranscurridos > 45) {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_cola';
            $this->avanzarDesdeFalloExtraccion();

            return;
        }

        // Job arrancó pero el worker murió a mitad (estado 'procesando' por más de 5 min).
        if ($model->extraccion_estado === 'procesando' && $segundosTranscurridos > 300) {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_cola';
            $this->avanzarDesdeFalloExtraccion();

            return;
        }

        if ($model->extraccion_estado === 'error_modelo') {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_modelo';
            $this->avanzarDesdeFalloExtraccion();

            return;
        }

        if ($model->extraccion_estado === 'fallida') {
            $this->extraccionProcesando = false;
            $this->advertenciaExtraccion = 'error_modelo';
            $this->avanzarDesdeFalloExtraccion();

            return;
        }

        if ($model->extraccion_estado === 'completada') {
            $this->extraccionProcesando = false;

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
            $this->firmasElectronicas = $model->firmas_electronicas ?? [];

            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 3]));
            $this->paso = 4;
            $this->persistirEstadoWizard();
        }
    }

    /**
     * Avanza al paso 4 cuando la extracción falló, dejando todos los campos
     * vacíos para que el usuario los complete manualmente.
     */
    private function avanzarDesdeFalloExtraccion(): void
    {
        if ($this->tipoTramite === TipoTramite::Deposito->value) {
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
            $this->datosFaltantes = ['N.º Permiso Recolección', 'N.º Permiso Movilización', 'Grupo Animal', 'Provincia', 'Localidad', 'N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'];
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

        $camposCuantitativos = ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'];
        if (in_array($campo, $camposCuantitativos, true) && (! is_numeric($valor) || (int) $valor < 0)) {
            $this->addError("datosEnEdicion.{$clave}", 'Ingresa un número entero válido mayor o igual a 0.');

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
    }

    public function guardarPasoCuatro(): void
    {
        if (! empty($this->datosFaltantes)) {
            $this->mostrarToast('Completa los datos faltantes.', 'error');

            return;
        }

        // Validar que cada número de permiso ingresado tenga su documento de respaldo
        $permisosConDocumento = [
            'N.º Permiso Recolección' => 'Copia de la autorización de recolección (MAATE)',
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

        $sinVerificar = array_filter($this->firmasElectronicas, fn ($estado) => $estado === 'no_verificado');
        if (! empty($sinVerificar)) {
            $this->mostrarToast('No se pudo verificar la firma de algunos documentos. Vuelve al paso anterior y vuelve a subirlos.', 'error');

            return;
        }

        if (empty($this->resultadoIdentidad)) {
            $this->mostrarToast('Valida la identidad del solicitante.', 'warning');

            return;
        }

        if ($this->cartaDelegacionRequerida && ! isset($this->documentosCargados['Carta de delegación / justificación de tercero'])) {
            $this->mostrarToast('Adjunta la Carta de Delegación.', 'warning');

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 4]));
        $this->paso = 5;
        $this->persistirEstadoWizard();
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
        $this->archivoMatrizNombre = $this->archivoMatriz->getClientOriginalName();

        // Si ya existe una matriz para esta solicitud, eliminarla antes de crear la nueva
        if ($this->matrizId) {
            MatrizEspeciesEloquentModel::where('id', $this->matrizId)->delete();
            $this->matrizId = null;
        }

        $parseado = $this->parsearXlsx($this->archivoMatriz);
        $campos = $parseado['campos'];
        $registros = $parseado['registros'];

        $this->camposDwCPresentes = $campos;

        $catalogo = app(CatalogoCuraduriaPort::class);
        $this->camposDwCRequeridos = $catalogo->camposRequeridos($this->solicitudId ?? '');

        $cargar = app(CargarMatrizEspeciesHandler::class);

        try {
            $output = ($cargar)(new CargarMatrizEspeciesInput(
                solicitudId: $this->solicitudId,
                camposDwCPresentes: array_fill_keys($campos, true),
                camposDwCExigidosPorCatalogo: $this->camposDwCRequeridos,
                registros: $registros,
            ));
        } catch (CamposDwCFaltantesException $e) {
            $this->errorMatriz = $e->getMessage();
            $this->matrizCargada = true;

            return;
        }

        $this->matrizId = $output->matrizId;
        $this->estadoMatriz = $output->estadoMatriz->value;
        $this->validacionTipograficaAplicada = $output->validacionTipograficaAplicada;
        $this->totalRegistros = $output->totalRegistros;
        $this->matrizCargada = true;
        $this->registrosMatriz = $registros;

        $repo = app(MatrizEspeciesRepositoryInterface::class);
        $matriz = $repo->buscarPorId(MatrizEspeciesId::from($this->matrizId));

        $this->poblarEstadosRegistros($matriz, $registros);
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
                    'especieCorregida' => null,
                    'noCatalogado' => false,
                    'motivoJustificacion' => null,
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
            $validacion = $resultados[$i] ?? ['estado' => 'catalogado', 'sugerencia' => null];
            $estadoEntidad = $registro->estado();

            // Si el registro ya fue resuelto (sugerencia aceptada o hallazgo justificado),
            // usar el estado de la entidad en vez de re-consultar GBIF.
            if (! $estadoEntidad->equals(EstadoRegistroEspecimen::Pendiente)) {
                $this->estadosRegistros[$id] = [
                    'catalogoId' => $csvRow['catalogNumber'] ?? null,
                    'especieIngresada' => $registro->nombreCientifico(),
                    'estado' => $estadoEntidad->value,
                    'especieSugerida' => null,
                    'especieCorregida' => $registro->nombreCorregido(),
                    'noCatalogado' => $registro->esNoCatalogado(),
                    'motivoJustificacion' => $registro->motivoJustificacion(),
                ];
            } else {
                $estado = match ($validacion['estado']) {
                    'catalogado' => 'Validado Técnicamente',
                    'inconsistencia_tipografica' => 'Pendiente',
                    'no_catalogado' => 'Pendiente',
                    'no_verificado' => 'No Verificado',
                    default => 'Pendiente',
                };

                $esNoCatalogado = $validacion['estado'] === 'no_catalogado';

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
                    'especieCorregida' => null,
                    'noCatalogado' => $esNoCatalogado,
                    'motivoJustificacion' => null,
                ];
            }

            $i++;
        }

        // Persistir los flags noCatalogado actualizados en la entidad
        if ($huboActualizacionEntidad) {
            $repo = app(MatrizEspeciesRepositoryInterface::class);
            $repo->guardar($matriz);
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

    public function eliminarMatriz(): void
    {
        if ($this->matrizId) {
            MatrizEspeciesEloquentModel::where('id', $this->matrizId)->delete();
        }

        $this->archivoMatriz = null;
        $this->archivoMatrizNombre = '';
        $this->matrizCargada = false;
        $this->matrizId = null;
        $this->estadoMatriz = '';
        $this->errorMatriz = '';
        $this->totalRegistros = 0;
        $this->camposDwCPresentes = [];
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

    public function justificarHallazgo(string $registroId, string $motivo): void
    {
        if (empty($motivo)) {
            return;
        }

        $handler = app(JustificarHallazgoTaxonomicoHandler::class);

        $output = ($handler)(new JustificarHallazgoTaxonomicoInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
            motivoJustificacion: $motivo,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['motivoJustificacion'] = $motivo;
        }

        $this->estadoMatriz = $output->estadoMatriz->value;
    }

    public function cambiarJustificacion(string $registroId, string $nuevoMotivo): void
    {
        if (empty($nuevoMotivo)) {
            return;
        }

        $handler = app(CambiarJustificacionTaxonomicaHandler::class);

        ($handler)(new CambiarJustificacionTaxonomicaInput(
            solicitudId: $this->solicitudId,
            matrizId: $this->matrizId,
            registroId: $registroId,
            nuevoMotivo: $nuevoMotivo,
        ));

        if (isset($this->estadosRegistros[$registroId])) {
            $this->estadosRegistros[$registroId]['motivoJustificacion'] = $nuevoMotivo;
        }
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

            // Re-consultar validación taxonómica para recuperar la sugerencia
            $validador = app(ValidacionTaxonomicaPort::class);
            $especieSugerida = null;

            try {
                $resultados = $validador->validarEspecies([$especieOriginal]);

                if (isset($resultados[0]) && $resultados[0]['estado'] === 'inconsistencia_tipografica') {
                    $especieSugerida = $resultados[0]['sugerencia'];
                }
            } catch (\Throwable) {
                // Si GBIF no responde, se muestra como pendiente sin sugerencia
            }

            $this->estadosRegistros[$registroId]['estado'] = $output->estadoRegistro->value;
            $this->estadosRegistros[$registroId]['especieCorregida'] = null;
            $this->estadosRegistros[$registroId]['especieSugerida'] = $especieSugerida;
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
            fn (array $r) => $r['estado'] === 'Pendiente'
        );

        if (! empty($pendientes)) {
            $this->mostrarToast(count($pendientes).' registro(s) requieren tu acción.', 'warning');

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 5]));
        $this->paso = 6;
        $this->persistirEstadoWizard();
    }

    // ── Paso 6 – Envío ───────────────────────────────────────────────────────────

    public function enviarSolicitud(EnviarSolicitudDepositoHandler $handler): void
    {
        $this->validate(
            ['declaracionAceptada' => 'accepted'],
            ['declaracionAceptada.accepted' => 'Debes aceptar la declaración para enviar la solicitud.']
        );

        $output = ($handler)(new EnviarSolicitudDepositoInput(solicitudId: $this->solicitudId));
        $this->estadoFinal = $output->estado->value;
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 6]));
        $this->paso = 7;
    }

    // ── Navegación ────────────────────────────────────────────────────────────────

    public function retroceder(): void
    {
        if ($this->paso > 1) {
            if ($this->paso === 4) {
                $this->extraccionProcesando = false;
                $this->firmasElectronicas = [];

                if ($this->solicitudId !== null) {
                    SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                        ->update(['extraccion_estado' => null, 'firmas_electronicas' => '{}']);
                }

                if (in_array('N.º Permiso Movilización', $this->datosFaltantes, true)
                    && ! in_array('Copia del permiso de movilización', $this->documentosRequeridos, true)
                ) {
                    $this->documentosRequeridos[] = 'Copia del permiso de movilización';
                }
            }

            if ($this->paso === 6) {
                $this->declaracionAceptada = false;
            }

            $this->paso--;

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

    public function propiedadParaDocumento(string $nombre): string
    {
        return match ($nombre) {
            'Formato solicitud depósito' => 'archivoFormatoDeposito',
            'Formato solicitud donación' => 'archivoFormatoDonacion',
            'Copia de la autorización de recolección (MAATE)' => 'archivoAutorizacionMaate',
            'Copia del permiso de movilización' => 'archivoPermisoMovilizacion',
            'Documento de explicación de motivos y/o carta de justificación (institucional o personal)' => 'archivoCartaJustificacion',
            'Carta de procedencia firmada por el responsable de la colección de origen' => 'archivoCartaProcedencia',
            'Carta de cesión de derechos / origen lícito' => 'archivoCartaCesion',
            'Carta de delegación / justificación de tercero' => 'archivoCartaDelegacion',
            default => throw new \InvalidArgumentException("Documento desconocido: {$nombre}"),
        };
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.registro-solicitud-deposito');
    }
}
