<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\LimiteAnualDepositosAlcanzado;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\ExtraccionDatosDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Nueva Solicitud de Depósito'])]
final class RegistroSolicitudDeposito extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    // ── Wizard ────────────────────────────────────────────────────────────────────

    public int $paso = 1;

    /** @var int[] */
    public array $pasosCompletados = [];

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

    // ── Paso 5 – Envío ────────────────────────────────────────────────────────────

    public bool $declaracionAceptada = false;

    public string $estadoFinal = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
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

    // ── Paso 1 ────────────────────────────────────────────────────────────────────

    public function avanzarPaso1(): void
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
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 1]));

        if ($this->tipoTramite === TipoTramite::Donacion->value) {
            // Donación no requiere declarar origen ni situación regulatoria
            $this->documentosRequeridos = ['Formato solicitud donación', 'Carta de cesión de derechos / origen lícito'];
            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
            $this->paso = 3;

            return;
        }

        $this->paso = 2;
    }

    // ── Paso 2 ────────────────────────────────────────────────────────────────────

    public function guardarPasoDos(DeterminarDocumentacionRequeridaHandler $determinar): void
    {
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
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
        $this->paso = 3;
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
    }

    public function solicitarIntervencion(
        RegistrarSolicitudDepositoHandler $registrar,
        ActualizarOrigenSolicitudDepositoHandler $actualizar,
        DeclararSinDocumentacionHandler $declarar,
        SolicitarIntervencionCuratoriaHandler $escalar,
    ): void {
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
                $this->paso = 1;

                return;
            }

            if ($this->tipoTramite !== TipoTramite::Donacion->value) {
                ($actualizar)(new ActualizarOrigenSolicitudDepositoInput(
                    solicitudId: $this->solicitudId,
                    origenRecoleccion: $this->origenRecoleccion,
                    situacionRegulatoria: $this->situacionRegulatoria,
                    provinciaOrigen: $this->provincia,
                ));
            }
        }

        ($declarar)(new DeclararSinDocumentacionInput(solicitudId: $this->solicitudId));
        ($escalar)(new SolicitarIntervencionCuratoriaInput(
            solicitudId: $this->solicitudId,
            investigadorId: (string) auth()->id(),
        ));
        $this->intervencionCuratoriaActiva = true;
    }

    public function guardarPasoTres(
        RegistrarSolicitudDepositoHandler $registrar,
        ActualizarOrigenSolicitudDepositoHandler $actualizar,
    ): void {
        foreach ($this->documentosRequeridos as $doc) {
            if (! isset($this->documentosCargados[$doc])) {
                $this->addError('documentos', "El documento \"{$doc}\" es requerido.");

                return;
            }
        }

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
                $this->paso = 1;

                return;
            }

            if ($this->tipoTramite !== TipoTramite::Donacion->value) {
                ($actualizar)(new ActualizarOrigenSolicitudDepositoInput(
                    solicitudId: $this->solicitudId,
                    origenRecoleccion: $this->origenRecoleccion,
                    situacionRegulatoria: $this->situacionRegulatoria,
                    provinciaOrigen: $this->provincia ?: null,
                ));
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

            $this->datosExtraidos = $this->tipoTramite === TipoTramite::Deposito->value
                ? [
                    'N.º Permiso Recolección' => $model->nro_permiso_recoleccion,
                    'N.º Permiso Movilización' => $model->nro_permiso_movilizacion,
                    'Grupo Animal' => $model->grupo_animal,
                    'Provincia' => ($model->provincia_origen === 'Fuera de Pichincha') ? null : $model->provincia_origen,
                    'Localidad' => $model->localidad,
                ]
                : [
                    'Grupo Animal' => $model->grupo_animal,
                    'Origen Donación' => $model->origen_donacion,
                ];
            $this->datosFaltantes = $model->datos_faltantes ?? [];
            $this->datosIngresadosManualmente = $model->datos_ingresados_manualmente ?? [];

            $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 3]));
            $this->paso = 4;
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
            ];
            $this->datosFaltantes = ['N.º Permiso Recolección', 'N.º Permiso Movilización', 'Grupo Animal', 'Provincia', 'Localidad'];
        } else {
            $this->datosExtraidos = [
                'Grupo Animal' => null,
                'Origen Donación' => null,
            ];
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 3]));
        $this->paso = 4;
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
            $this->addError('datosFaltantes', 'Completa todos los datos faltantes antes de continuar.');

            return;
        }

        if (empty($this->resultadoIdentidad)) {
            $this->addError('identidad', 'Valida la identidad del documento antes de continuar.');

            return;
        }

        if ($this->cartaDelegacionRequerida && ! isset($this->documentosCargados['Carta de delegación / justificación de tercero'])) {
            $this->addError('cartaDelegacion', 'Adjunta la Carta de Delegación para continuar.');

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 4]));
        $this->paso = 5;
    }

    // ── Paso 5 ────────────────────────────────────────────────────────────────────

    public function enviarSolicitud(EnviarSolicitudDepositoHandler $handler): void
    {
        $this->validate(
            ['declaracionAceptada' => 'accepted'],
            ['declaracionAceptada.accepted' => 'Debes aceptar la declaración para enviar la solicitud.']
        );

        $output = ($handler)(new EnviarSolicitudDepositoInput(solicitudId: $this->solicitudId));
        $this->estadoFinal = $output->estado->value;
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 5]));
        $this->paso = 6;
    }

    // ── Navegación ────────────────────────────────────────────────────────────────

    public function retroceder(): void
    {
        if ($this->paso > 1) {
            if ($this->paso === 4) {
                $this->extraccionProcesando = false;

                if ($this->solicitudId !== null) {
                    SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                        ->update(['extraccion_estado' => null]);
                }

                if (in_array('N.º Permiso Movilización', $this->datosFaltantes, true)
                    && ! in_array('Copia del permiso de movilización', $this->documentosRequeridos, true)
                ) {
                    $this->documentosRequeridos[] = 'Copia del permiso de movilización';
                }
            }

            $this->paso--;

            if ($this->paso === 2 && $this->tipoTramite === TipoTramite::Donacion->value) {
                $this->paso = 1;
            }
        }
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
