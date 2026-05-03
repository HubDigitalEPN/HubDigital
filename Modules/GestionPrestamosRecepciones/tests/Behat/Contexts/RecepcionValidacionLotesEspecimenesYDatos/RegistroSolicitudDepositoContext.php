<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesInput;
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
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoDocumental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: registro_solicitud_deposito.feature
 * Capability: RecepcionValidacionLotesEspecimenesYDatos
 */
final class RegistroSolicitudDepositoContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarSolicitudDepositoHandler $registrarHandler;

    private CargarDocumentacionOficialHandler $cargarDocumentacionHandler;

    private CompletarDatosManualesHandler $completarDatosHandler;

    private ValidarDocumentacionInicialHandler $validarDocumentacionHandler;

    private ValidarIdentidadSolicitudHandler $validarIdentidadHandler;

    private DeterminarDocumentacionRequeridaHandler $determinarDocumentacionHandler;

    private SolicitarIntervencionCuratoriaHandler $solicitarIntervencionHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudDeposito $solicitudEnCurso = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ID fijo del investigador actor en todos los escenarios de esta feature
    private string $investigadorId = 'inv-rec-001';

    // Estado acumulado entre pasos @Given del mismo escenario
    private string $provinciaDeclarada = '';

    private string $origenRecoleccion = '';

    private string $situacionRegulatoria = '';

    private string $nombrePerfil = '';

    private ?string $campoDatoFaltante = null;

    private array $documentosCargados = [];

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->registrarHandler = $this->make(RegistrarSolicitudDepositoHandler::class);
        $this->cargarDocumentacionHandler = $this->make(CargarDocumentacionOficialHandler::class);
        $this->completarDatosHandler = $this->make(CompletarDatosManualesHandler::class);
        $this->validarDocumentacionHandler = $this->make(ValidarDocumentacionInicialHandler::class);
        $this->validarIdentidadHandler = $this->make(ValidarIdentidadSolicitudHandler::class);
        $this->determinarDocumentacionHandler = $this->make(DeterminarDocumentacionRequeridaHandler::class);
        $this->solicitarIntervencionHandler = $this->make(SolicitarIntervencionCuratoriaHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    /**
     * Crea, persiste y asigna una SolicitudDeposito en estado borrador para el investigador actor.
     * Las mutaciones de estado posteriores deben realizarse en el paso @Given que llame a este helper.
     */
    private function sembrarSolicitudDepositoBase(string $tipoTramite = 'Depósito'): SolicitudDeposito
    {
        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);

        $solicitud = SolicitudDeposito::crear(
            id: $repo->nextIdentity(),
            investigadorId: $this->investigadorId,
            tipoTramite: $tipoTramite,
        );

        $repo->guardar($solicitud);
        $this->solicitudEnCurso = $solicitud;

        return $solicitud;
    }

    /**
     * Siembra N solicitudes previas del tipo dado para simular el historial anual del investigador.
     */
    private function sembrarSolicitudesPrevias(int $cantidad, string $tipoTramite): void
    {
        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);

        for ($i = 0; $i < $cantidad; $i++) {
            $solicitud = SolicitudDeposito::crear(
                id: $repo->nextIdentity(),
                investigadorId: $this->investigadorId,
                tipoTramite: $tipoTramite,
            );
            $repo->guardar($solicitud);
        }
    }

    /**
     * Convierte un nombre de documento en un slug apto para rutas de prueba.
     */
    private function slugify(string $texto): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $texto) ?? '');
    }

    // =========================================================================
    // ANTECEDENTES (Background)
    // =========================================================================

    #[Given('que el investigador tiene una cuenta activa en el sistema')]
    public function queElInvestigadorTieneUnaCuentaActivaEnElSistema(): void
    {
        Assert::assertNotEmpty(
            $this->investigadorId,
            'investigador_id no puede estar vacío — se requiere un actor válido para todos los escenarios'
        );
    }

    #[Given('ha iniciado una nueva solicitud de depósito')]
    public function haIniciadoUnaNuevaSolicitudDeDeposito(): void
    {
        // El investigador ha accedido al formulario de nueva solicitud.
        // Cada escenario siembra su propia solicitud según sus precondiciones específicas.
        Assert::assertNotEmpty(
            $this->investigadorId,
            'Se requiere un investigador_id válido para iniciar la solicitud'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Aplicación de límite anual por tipo de trámite
    // =========================================================================

    #[Given('que el investigador tiene :solicitudesPrevias solicitudes de tipo :tipoTramite registradas este año')]
    public function queElInvestigadorTieneSolicitudesPreviasRegistradasEsteAnio(
        int $solicitudesPrevias,
        string $tipoTramite
    ): void {
        Assert::assertGreaterThanOrEqual(
            0,
            $solicitudesPrevias,
            'El número de solicitudes previas no puede ser negativo'
        );
        Assert::assertNotEmpty($tipoTramite, 'El tipo de trámite no puede estar vacío');

        $this->sembrarSolicitudesPrevias($solicitudesPrevias, $tipoTramite);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $conteo = $repo->contarPorInvestigadorYTipoEnAnioActual($this->investigadorId, $tipoTramite);

        Assert::assertSame(
            $solicitudesPrevias,
            $conteo,
            "Se esperaban {$solicitudesPrevias} solicitudes previas de tipo '{$tipoTramite}', se encontraron: {$conteo}"
        );
    }

    #[When('el investigador intenta crear una nueva solicitud de tipo :tipoTramite')]
    public function elInvestigadorIntentaCrearUnaNuevaSolicitudDeTipo(string $tipoTramite): void
    {
        Assert::assertNotEmpty($tipoTramite, 'El tipo de trámite no puede estar vacío');

        try {
            $this->ultimaRespuesta = ($this->registrarHandler)(
                new RegistrarSolicitudDepositoInput(
                    investigadorId: $this->investigadorId,
                    tipoTramite: $tipoTramite,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud queda en estado :estadoSolicitud')]
    public function laSolicitudQuedaEnEstado(string $estadoSolicitud): void
    {
        if ($estadoSolicitud === 'Rechazada') {
            Assert::assertNotNull(
                $this->excepcionCapturada,
                'Se esperaba que el registro fuera rechazado (límite anual) pero el handler completó sin error'
            );

            return;
        }

        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitudDeposito::from($estadoSolicitud)),
            "Se esperaba estado '{$estadoSolicitud}', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    #[Then('el investigador es notificado con el mensaje :mensajeAlerta')]
    public function elInvestigadorEsNotificadoConElMensaje(string $mensajeAlerta): void
    {
        if ($mensajeAlerta === 'Ninguno') {
            Assert::assertNull(
                $this->excepcionCapturada,
                'No se esperaba ningún mensaje de alerta, pero el handler lanzó: '.$this->excepcionCapturada?->getMessage()
            );

            return;
        }

        Assert::assertNotNull(
            $this->excepcionCapturada,
            "Se esperaba que el investigador recibiera el mensaje '{$mensajeAlerta}' pero no hubo error ni notificación"
        );
        Assert::assertStringContainsString(
            $mensajeAlerta,
            $this->excepcionCapturada->getMessage(),
            "El mensaje de alerta esperado '{$mensajeAlerta}' no está presente en la excepción capturada"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Documentación legal requerida según el origen de los especímenes
    // =========================================================================

    #[Given('que el investigador declara que el origen de los especímenes es :origenRecoleccion')]
    public function queElInvestigadorDeclaraOrigenDeEspecimenes(string $origenRecoleccion): void
    {
        Assert::assertNotEmpty($origenRecoleccion, 'El origen de recolección no puede estar vacío');

        $this->origenRecoleccion = $origenRecoleccion;

        $solicitud = $this->sembrarSolicitudDepositoBase();
        $solicitud->declararOrigenRecoleccion($origenRecoleccion);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($solicitud);

        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras declarar el origen');
        Assert::assertSame(
            $origenRecoleccion,
            $persistida->origenRecoleccion(),
            "El origen de recolección persistido no coincide: esperado '{$origenRecoleccion}', obtenido '{$persistida->origenRecoleccion()}'"
        );
    }

    #[Given('su situación regulatoria actual es :situacionRegulatoria')]
    public function suSituacionRegulatoriaActualEs(string $situacionRegulatoria): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertNotEmpty($situacionRegulatoria, 'La situación regulatoria no puede estar vacía');

        $this->situacionRegulatoria = $situacionRegulatoria;

        $this->solicitudEnCurso->declararSituacionRegulatoria($situacionRegulatoria);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($this->solicitudEnCurso);

        $persistida = $repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras declarar la situación regulatoria');
        Assert::assertSame(
            $situacionRegulatoria,
            $persistida->situacionRegulatoria(),
            "La situación regulatoria persistida no coincide: esperada '{$situacionRegulatoria}', obtenida '{$persistida->situacionRegulatoria()}'"
        );
    }

    #[Then('la solicitud exige adjuntar los siguientes documentos: :documentoRequerido')]
    public function laSolicitudExigeAdjuntarLosSiguientesDocumentos(string $documentoRequerido): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertNotEmpty($this->origenRecoleccion, 'Se requiere el origen de recolección del paso Dado anterior');
        Assert::assertNotEmpty($this->situacionRegulatoria, 'Se requiere la situación regulatoria del paso Dado anterior');

        try {
            $this->ultimaRespuesta = ($this->determinarDocumentacionHandler)(
                new DeterminarDocumentacionRequeridaInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }

        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        $combinado = implode(' y ', $this->ultimaRespuesta->documentosRequeridos);
        Assert::assertSame(
            $documentoRequerido,
            $combinado,
            "Se esperaba documentos requeridos '{$documentoRequerido}', se obtuvo: '{$combinado}'"
        );
    }

    // =========================================================================
    // ESCENARIO: Escalabilidad de la solicitud por falta total de documentación
    // =========================================================================

    #[Given('que el investigador carece de los documentos del MAATE y de carta de justificación')]
    public function queElInvestigadorCareceDeLosDocumentosDelMAATEYDeJustificacionesFormales(): void
    {
        $solicitud = $this->sembrarSolicitudDepositoBase();
        $solicitud->marcarSinDocumentacionDisponible();

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($solicitud);

        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras marcarla sin documentación');
        Assert::assertTrue(
            $persistida->sinDocumentacionDisponible(),
            'Se esperaba que la solicitud estuviera marcada sin documentación disponible'
        );
    }

    #[When('el investigador solicita la intervención directa de curaduría')]
    public function elInvestigadorSolicitaLaIntervencionDirectaDeCuraduria(): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertTrue(
            $this->solicitudEnCurso->sinDocumentacionDisponible(),
            'La solicitud debe estar sin documentación disponible para escalar a curaduría'
        );

        try {
            $this->ultimaRespuesta = ($this->solicitarIntervencionHandler)(
                new SolicitarIntervencionCuratoriaInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    investigadorId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el proceso de carga documental se pausa')]
    public function elProcedeDeCargaDocumentalSePausa(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->cargaDocumentalPausada,
            'Se esperaba que la carga documental quedara pausada tras escalar a curaduría'
        );
    }

    #[Then('la solicitud pasa al estado :estadoEsperado')]
    public function laSolicitudPasaAlEstado(string $estadoEsperado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $solicitud = $repo->buscarPorId($this->solicitudEnCurso->id());

        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertTrue(
            $solicitud->estado()->equals(EstadoSolicitudDeposito::from($estadoEsperado)),
            "Se esperaba estado '{$estadoEsperado}', se obtuvo: {$solicitud->estado()->value}"
        );
    }

    #[Then('se notifica al curador para que inicie el contacto directo con el investigador')]
    public function seNotificaAlCuradorParaQueInicieElContactoDirectoConElInvestigador(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionCuradorEnviada,
            'Se esperaba que la notificación al curador fuera enviada tras la escalada'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->curadorNotificadoId,
            'Se esperaba que el ID del curador notificado quedara registrado en la respuesta'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Validación de Permiso de Movilización por provincia
    // =========================================================================

    #[Given('que el investigador declara que las muestras provienen de la provincia de :provincia')]
    public function queElInvestigadorDeclaraQueProvinciaDeLasMuestras(string $provincia): void
    {
        Assert::assertNotEmpty($provincia, 'La provincia de origen no puede estar vacía');

        $this->provinciaDeclarada = $provincia;

        $solicitud = $this->sembrarSolicitudDepositoBase();
        $solicitud->declararProvincia($provincia);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($solicitud);

        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras declarar la provincia');
        Assert::assertSame(
            $provincia,
            $persistida->provinciaOrigen(),
            "La provincia persistida no coincide: esperada '{$provincia}', obtenida '{$persistida->provinciaOrigen()}'"
        );
    }

    #[Given('el documento :nombreDocumento se encuentra :estadoAdjunto')]
    public function elDocumentoSeEncuentra(string $nombreDocumento, string $estadoAdjunto): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertContains(
            $estadoAdjunto,
            ['Adjuntado', 'No Adjuntado'],
            "El estado de adjunto '{$estadoAdjunto}' no es un valor reconocido"
        );

        if ($estadoAdjunto === 'Adjuntado') {
            $this->documentosCargados[$nombreDocumento] = "documentos/{$this->slugify($nombreDocumento)}-test.pdf";
        }
    }

    #[When('el investigador envía la documentación inicial')]
    public function elInvestigadorEnviaLaDocumentacionInicial(): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertNotEmpty(
            $this->provinciaDeclarada,
            'Se requiere una provincia declarada del paso Dado anterior'
        );

        try {
            $this->ultimaRespuesta = ($this->validarDocumentacionHandler)(
                new ValidarDocumentacionInicialInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    provinciaOrigen: $this->provinciaDeclarada,
                    documentosAdjuntos: $this->documentosCargados,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el estado documental de la solicitud es :estadoDocumental')]
    public function elEstadoDocumentalDeLaSolicitudEs(string $estadoDocumental): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estadoDocumental->equals(EstadoDocumental::from($estadoDocumental)),
            "Se esperaba estado documental '{$estadoDocumental}', se obtuvo: {$this->ultimaRespuesta->estadoDocumental->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Integración de datos (Depósito) / Carga de documentación (Donación)
    // =========================================================================

    #[Given('que el investigador seleccionó el trámite de :tipoTramite')]
    public function queElInvestigadorSeleccionoElTramiteDe(string $tipoTramite): void
    {
        Assert::assertContains(
            $tipoTramite,
            ['Depósito', 'Donación'],
            "El tipo de trámite '{$tipoTramite}' no es reconocido"
        );

        $solicitud = $this->sembrarSolicitudDepositoBase($tipoTramite);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $persistida = $repo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras crearla');
        Assert::assertSame(
            $tipoTramite,
            $persistida->tipoTramite(),
            "El tipo de trámite persistido '{$persistida->tipoTramite()}' no coincide con '{$tipoTramite}'"
        );
    }

    #[When('el investigador carga los siguientes documentos:')]
    public function elInvestigadorCargaLosSiguientesDocumentos(TableNode $tabla): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );

        $documentos = [];

        foreach ($tabla->getRows() as $indice => $fila) {
            if ($indice === 0) {
                continue; // Saltar la cabecera
            }

            $nombreDocumento = $fila[0];
            $documentos[$nombreDocumento] = "documentos/{$this->slugify($nombreDocumento)}-test.pdf";
        }

        Assert::assertNotEmpty($documentos, 'La lista de documentos a cargar no puede estar vacía');

        try {
            $this->ultimaRespuesta = ($this->cargarDocumentacionHandler)(
                new CargarDocumentacionOficialInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    documentos: $documentos,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud incorpora automáticamente la siguiente información:')]
    public function laSolicitudIncorporaAutomaticamenteLaSiguienteInformacion(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $solicitud = $repo->buscarPorId($this->solicitudEnCurso->id());

        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio tras cargar la documentación');

        /** @var array<string, string> Mapea el nombre del campo en el .feature al getter de la entidad */
        $getterPorCampo = [
            'N.º Permiso Recolección' => 'nroPermisoRecoleccion',
            'N.º Permiso Movilización' => 'nroPermisoMovilizacion',
            'Grupo Animal' => 'grupoAnimal',
            'Provincia' => 'provinciaOrigen',
            'Localidad' => 'localidad',
        ];

        foreach ($tabla->getRows() as $indice => $fila) {
            if ($indice === 0) {
                continue; // Saltar cabecera
            }

            $informacionRequerida = $fila[0];

            Assert::assertArrayHasKey(
                $informacionRequerida,
                $getterPorCampo,
                "El campo '{$informacionRequerida}' no está mapeado a ningún getter de la entidad"
            );

            $getter = $getterPorCampo[$informacionRequerida];

            Assert::assertNotNull(
                $solicitud->$getter(),
                "Se esperaba que '{$informacionRequerida}' fuera extraído automáticamente de la documentación pero está vacío"
            );
        }
    }

    // =========================================================================
    // ESCENARIO: Carga de documentación oficial para Donaciones
    // =========================================================================

    #[When('el investigador carga los siguientes documentos obligatorios:')]
    public function elInvestigadorCargaLosSiguientesDocumentosObligatorios(TableNode $tabla): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );

        $documentos = [];

        foreach ($tabla->getRows() as $indice => $fila) {
            if ($indice === 0) {
                continue; // Saltar la cabecera
            }

            $nombreDocumento = $fila[0];
            $documentos[$nombreDocumento] = "documentos/{$this->slugify($nombreDocumento)}-test.pdf";
        }

        Assert::assertNotEmpty($documentos, 'La lista de documentos obligatorios a cargar no puede estar vacía');

        try {
            $this->ultimaRespuesta = ($this->cargarDocumentacionHandler)(
                new CargarDocumentacionOficialInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    documentos: $documentos,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud registra el origen de la donación')]
    public function laSolicitudRegistraElOrigenDeLaDonacion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $solicitud = $repo->buscarPorId($this->solicitudEnCurso->id());

        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertNotNull(
            $solicitud->origenDonacion(),
            'Se esperaba que el origen de la donación quedara registrado en la solicitud'
        );
    }

    #[Then('pasa a estar :estadoEsperado')]
    public function pasaAEstar(string $estadoEsperado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitudDeposito::from($estadoEsperado)),
            "Se esperaba estado '{$estadoEsperado}', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Completitud de datos obligatorios faltantes en la documentación
    // =========================================================================

    #[Given('que la documentación oficial no contiene el :campoDatoFaltante')]
    public function queLaDocumentacionOficialNoContieneEl(string $campoDatoFaltante): void
    {
        Assert::assertNotEmpty($campoDatoFaltante, 'El nombre del dato faltante no puede estar vacío');

        $this->campoDatoFaltante = $campoDatoFaltante;

        $solicitud = $this->sembrarSolicitudDepositoBase();
        $solicitud->marcarDatoComoFaltante($campoDatoFaltante);

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($solicitud);

        $persistida = $repo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras marcar el dato faltante');
        Assert::assertTrue(
            $persistida->tieneDatoFaltante($campoDatoFaltante),
            "Se esperaba que '{$campoDatoFaltante}' estuviera marcado como faltante en la solicitud pero no lo está"
        );
    }

    #[When('el investigador provee esta información faltante')]
    public function elInvestigadorProveeEstaInformacionFaltante(): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertNotNull(
            $this->campoDatoFaltante,
            'Se requiere un campo faltante definido del paso Dado anterior'
        );

        /** @var array<string, string> Valores canónicos de prueba por campo faltante */
        $valoresPorCampo = [
            'Grupo Animal' => 'Insecta - Coleoptera',
        ];

        $valorProveido = $valoresPorCampo[$this->campoDatoFaltante]
            ?? "Valor de prueba para {$this->campoDatoFaltante}";

        Assert::assertNotEmpty(
            $valorProveido,
            "El valor a proveer para '{$this->campoDatoFaltante}' no puede estar vacío"
        );

        try {
            $this->ultimaRespuesta = ($this->completarDatosHandler)(
                new CompletarDatosManualesInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    campo: $this->campoDatoFaltante,
                    valor: $valorProveido,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud se registra exitosamente')]
    public function laSolicitudSeRegistraExitosamente(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $solicitud = $repo->buscarPorId($this->solicitudEnCurso->id());

        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio tras completar los datos');
        Assert::assertFalse(
            $solicitud->tieneDatosFaltantes(),
            'Se esperaba que la solicitud no tuviera datos faltantes tras completarlos manualmente'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Validación de identidad mediante el Formato de Solicitud
    // =========================================================================

    #[Given('que el investigador ha cargado el :nombreFormulario')]
    public function queElInvestigadorHaCargadoEl(string $nombreFormulario): void
    {
        Assert::assertNotEmpty($nombreFormulario, 'El nombre del formulario no puede estar vacío');

        $solicitud = $this->sembrarSolicitudDepositoBase();
        $solicitud->adjuntarDocumento(
            nombre: $nombreFormulario,
            ruta: "documentos/{$this->slugify($nombreFormulario)}-test.pdf",
        );

        $repo = $this->make(SolicitudDepositoRepositoryInterface::class);
        $repo->guardar($solicitud);

        $persistida = $repo->buscarPorId($solicitud->id());

        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras adjuntar el formulario');
        Assert::assertTrue(
            $persistida->tieneDocumentoAdjunto($nombreFormulario),
            "Se esperaba que '{$nombreFormulario}' estuviera adjunto en la solicitud pero no lo está"
        );
    }

    #[Given('su perfil de usuario está registrado como :nombrePerfil')]
    public function suPerfilDeUsuarioEstaRegistradoComo(string $nombrePerfil): void
    {
        Assert::assertNotEmpty($nombrePerfil, 'El nombre del perfil no puede estar vacío');
        $this->nombrePerfil = $nombrePerfil;
    }

    #[When('se compara el perfil del investigador con el nombre :nombreEnDocumento del formulario')]
    public function seComparaElPerfilDelInvestigadorConElNombreDelFormulario(string $nombreEnDocumento): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );
        Assert::assertNotEmpty(
            $this->nombrePerfil,
            'Se requiere el nombre de perfil del paso Dado anterior'
        );
        Assert::assertNotEmpty($nombreEnDocumento, 'El nombre en el documento no puede estar vacío');

        try {
            $this->ultimaRespuesta = ($this->validarIdentidadHandler)(
                new ValidarIdentidadSolicitudInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    nombrePerfil: $this->nombrePerfil,
                    nombreEnDocumento: $nombreEnDocumento,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el resultado de la validación es :resultado')]
    public function elResultadoDeLaValidacionEs(string $resultado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->resultado->equals(ResultadoValidacionIdentidad::from($resultado)),
            "Se esperaba resultado '{$resultado}', se obtuvo: {$this->ultimaRespuesta->resultado->value}"
        );
    }

    #[Then('se habilita la acción: :accionPermitida')]
    public function seHabilitaLaAccion(string $accionPermitida): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertContains(
            $accionPermitida,
            $this->ultimaRespuesta->accionesPermitidas,
            "Se esperaba que la acción '{$accionPermitida}' estuviera habilitada, pero no está en las acciones disponibles"
        );
    }
}
