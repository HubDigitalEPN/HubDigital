<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito\ActualizarOrigenSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeclararSinDocumentacion\DeclararSinDocumentacionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaInput;
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

    public bool $intervencionCuratoriaActiva = false;

    // ── Paso 4 – Datos ────────────────────────────────────────────────────────────

    /** @var array<string, string|null> */
    public array $datosExtraidos = [];

    /** @var string[] */
    public array $datosFaltantes = [];

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

    public function avanzarPaso1(RegistrarSolicitudDepositoHandler $handler): void
    {
        if (empty($this->tipoTramite)) {
            $this->addError('tipoTramite', 'Selecciona un tipo de trámite para continuar.');

            return;
        }

        if ($this->solicitudId === null) {
            try {
                $output = ($handler)(new RegistrarSolicitudDepositoInput(
                    investigadorId: (string) auth()->id(),
                    tipoTramite: $this->tipoTramite,
                ));
                $this->solicitudId = $output->id;
                $this->numeroSolicitud = $output->numero;
                $this->limiteAlcanzado = false;
                $this->mensajeLimite = '';
            } catch (LimiteAnualDepositosAlcanzado $e) {
                $this->limiteAlcanzado = true;
                $this->mensajeLimite = $e->getMessage();

                return;
            }
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 1]));
        $this->paso = 2;
    }

    // ── Paso 2 ────────────────────────────────────────────────────────────────────

    public function guardarPasoDos(
        ActualizarOrigenSolicitudDepositoHandler $actualizar,
        DeterminarDocumentacionRequeridaHandler $determinar,
    ): void {
        $this->validate([
            'origenRecoleccion' => 'required|string',
            'situacionRegulatoria' => 'required|string',
        ], [
            'origenRecoleccion.required' => 'Selecciona la procedencia de los especímenes.',
            'situacionRegulatoria.required' => 'Selecciona la situación regulatoria.',
        ]);

        ($actualizar)(new ActualizarOrigenSolicitudDepositoInput(
            solicitudId: $this->solicitudId,
            origenRecoleccion: $this->origenRecoleccion,
            situacionRegulatoria: $this->situacionRegulatoria,
            provinciaOrigen: $this->provincia,
        ));

        $output = ($determinar)(new DeterminarDocumentacionRequeridaInput(
            solicitudId: $this->solicitudId,
        ));

        $this->documentosRequeridos = $output->documentosRequeridos;
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 2]));
        $this->paso = 3;
    }

    // ── Paso 3 – File upload lifecycle hooks ──────────────────────────────────────

    public function updatedArchivoFormatoDeposito(): void
    {
        $this->registrarDocumentoCargado('Formato Solicitud Depósito', $this->archivoFormatoDeposito);
    }

    public function updatedArchivoFormatoDonacion(): void
    {
        $this->registrarDocumentoCargado('Formato Solicitud Donación', $this->archivoFormatoDonacion);
    }

    public function updatedArchivoAutorizacionMaate(): void
    {
        $this->registrarDocumentoCargado('Copia de la Autorización de Recolección (MAATE)', $this->archivoAutorizacionMaate);
    }

    public function updatedArchivoPermisoMovilizacion(): void
    {
        $this->registrarDocumentoCargado('Copia del Permiso de Movilización', $this->archivoPermisoMovilizacion);
    }

    public function updatedArchivoCartaJustificacion(): void
    {
        $this->registrarDocumentoCargado(
            'Documento de Explicación de Motivos y/o Carta de Justificación (Institucional o Personal)',
            $this->archivoCartaJustificacion
        );
    }

    public function updatedArchivoCartaProcedencia(): void
    {
        $this->registrarDocumentoCargado(
            'Carta de Procedencia firmada por el responsable de la colección de origen',
            $this->archivoCartaProcedencia
        );
    }

    public function updatedArchivoCartaCesion(): void
    {
        $this->registrarDocumentoCargado('Carta de Cesión de Derechos / Origen Lícito', $this->archivoCartaCesion);
    }

    public function updatedArchivoCartaDelegacion(): void
    {
        $this->registrarDocumentoCargado('Carta de Delegación / Justificación de Tercero', $this->archivoCartaDelegacion);
    }

    private function registrarDocumentoCargado(string $nombre, mixed $archivo): void
    {
        if ($archivo === null) {
            return;
        }

        $ruta = $archivo->store('depositos', 'public');
        $this->documentosCargados[$nombre] = $ruta;
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

    public function guardarPasoTres(
        CargarDocumentacionOficialHandler $cargar,
        ValidarDocumentacionInicialHandler $validar,
    ): void {
        foreach ($this->documentosRequeridos as $doc) {
            if (! isset($this->documentosCargados[$doc])) {
                $this->addError('documentos', "El documento \"{$doc}\" es requerido.");

                return;
            }
        }

        ($cargar)(new CargarDocumentacionOficialInput(
            solicitudId: $this->solicitudId,
            documentos: $this->documentosCargados,
        ));

        $outputValidar = ($validar)(new ValidarDocumentacionInicialInput(
            solicitudId: $this->solicitudId,
            provinciaOrigen: $this->provincia,
            documentosAdjuntos: $this->documentosCargados,
        ));
        $this->estadoDocumental = $outputValidar->estadoDocumental->value;

        $model = SolicitudDepositoEloquentModel::findOrFail($this->solicitudId);
        $this->datosExtraidos = array_filter([
            'N.º Permiso Recolección' => $model->nro_permiso_recoleccion,
            'N.º Permiso Movilización' => $model->nro_permiso_movilizacion,
            'Grupo Animal' => $model->grupo_animal,
            'Provincia' => $model->provincia_origen,
            'Localidad' => $model->localidad,
        ]);
        $this->datosFaltantes = $model->datos_faltantes ?? [];

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

    public function iniciarEdicionDato(string $campo): void
    {
        $this->datosEnEdicion[$campo] = $this->datosExtraidos[$campo] ?? '';
    }

    public function cancelarEdicionDato(string $campo): void
    {
        unset($this->datosEnEdicion[$campo]);
    }

    public function guardarDatoFaltante(string $campo, CompletarDatosManualesHandler $handler): void
    {
        $valor = $this->datosEnEdicion[$campo] ?? '';

        if (empty($valor)) {
            $this->addError("datosEnEdicion.{$campo}", 'El valor no puede estar vacío.');

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
        unset($this->datosEnEdicion[$campo]);

        if ($output->estado === EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria) {
            $this->estadoFinal = $output->estado->value;
        }
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

        if ($this->cartaDelegacionRequerida && ! isset($this->documentosCargados['Carta de Delegación / Justificación de Tercero'])) {
            $this->addError('cartaDelegacion', 'Adjunta la Carta de Delegación para continuar.');

            return;
        }

        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 4]));
        $this->paso = 5;
    }

    // ── Paso 5 ────────────────────────────────────────────────────────────────────

    public function enviarSolicitud(): void
    {
        $this->validate(
            ['declaracionAceptada' => 'accepted'],
            ['declaracionAceptada.accepted' => 'Debes aceptar la declaración para enviar la solicitud.']
        );

        $model = SolicitudDepositoEloquentModel::findOrFail($this->solicitudId);
        $this->estadoFinal = $model->estado;
        $this->pasosCompletados = array_values(array_unique([...$this->pasosCompletados, 5]));
        $this->paso = 6;
    }

    // ── Navegación ────────────────────────────────────────────────────────────────

    public function retroceder(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    // ── Helper público para vistas ────────────────────────────────────────────────

    public function propiedadParaDocumento(string $nombre): string
    {
        return match ($nombre) {
            'Formato Solicitud Depósito' => 'archivoFormatoDeposito',
            'Formato Solicitud Donación' => 'archivoFormatoDonacion',
            'Copia de la Autorización de Recolección (MAATE)' => 'archivoAutorizacionMaate',
            'Copia del Permiso de Movilización' => 'archivoPermisoMovilizacion',
            'Documento de Explicación de Motivos y/o Carta de Justificación (Institucional o Personal)' => 'archivoCartaJustificacion',
            'Carta de Procedencia firmada por el responsable de la colección de origen' => 'archivoCartaProcedencia',
            'Carta de Cesión de Derechos / Origen Lícito' => 'archivoCartaCesion',
            'Carta de Delegación / Justificación de Tercero' => 'archivoCartaDelegacion',
            default => throw new \InvalidArgumentException("Documento desconocido: {$nombre}"),
        };
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.registro-solicitud-deposito');
    }
}
