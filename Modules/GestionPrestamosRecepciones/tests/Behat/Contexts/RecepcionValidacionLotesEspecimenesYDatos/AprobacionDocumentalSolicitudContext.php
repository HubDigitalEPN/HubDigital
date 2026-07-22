<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Application\Ports\ColaRevisionCuratorialPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaTransferenciaDominioGenerada;
use Modules\GestionPrestamosRecepciones\Domain\Events\CodigoQRAsignado;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudAprobadaDocumentalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudDepositoPendienteDeRevision;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPriorizada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudRechazadaDocumentalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudRequiereCorreccion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoAlerta;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeColaRevisionCuratorialAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionCuratoriaAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionInvestigadorAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryMatrizEspeciesRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudDepositoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: aprobacion_documental_solicitud.feature
 * Capability: RecepcionValidacionLotesEspecimenesYDatos
 *
 * Estrategia: 100% In-Memory. Cero persistencia real.
 */
final class AprobacionDocumentalSolicitudContext extends BaseContext
{
    // ── Repositorios In-Memory (acceso directo para @Given y @Then) ──────────

    private InMemorySolicitudDepositoRepository $repo;

    private FakeEventPublisherAdapter $fakePublisher;

    private FakeNotificacionCuratoriaAdapter $fakeNotificacionCuratoria;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private EnviarSolicitudDepositoHandler $enviarSolicitudHandler;

    private AprobarDocumentalmenteSolicitudHandler $aprobarHandler;

    private AceptarJustificacionesAlertasHandler $aceptarJustificacionesHandler;

    private RechazarJustificacionesAlertasHandler $rechazarJustificacionesHandler;

    private RechazarDocumentalmenteSolicitudHandler $rechazarHandler;

    private PriorizarSolicitudEnColaHandler $priorizarHandler;

    private AprobarDonacionConTransferenciaHandler $aprobarDonacionHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudDeposito $solicitudEnCurso = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-rec-001';

    private string $curadorId = 'cur-001';

    private ?string $tipoAlertaJustificada = null;

    /** @var array<int, string> */
    private array $justificacionesPresentes = [];

    private bool $cartaCesionValida = true;

    // ── Constructor — inyecta In-Memory antes de resolver Handlers ───────────

    public function __construct()
    {
        // 0. Asegurar que Laravel está booteado antes de usar el container
        self::bootApp();

        // 1. Crear instancias In-Memory fresh para este escenario
        $this->repo = new InMemorySolicitudDepositoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->fakeNotificacionCuratoria = new FakeNotificacionCuratoriaAdapter;

        // 2. Interceptar el container para que los Handlers reciban estas instancias
        self::$app->instance(SolicitudDepositoRepositoryInterface::class, $this->repo);
        // Los handlers de aprobación consultan la matriz para avisar de las correcciones
        // de curaduría: sin este binding resolverían el repositorio Eloquent y el escenario
        // dejaría de ser 100% en memoria.
        self::$app->instance(MatrizEspeciesRepositoryInterface::class, new InMemoryMatrizEspeciesRepository);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(NotificacionCuratoriaPort::class, $this->fakeNotificacionCuratoria);
        self::$app->instance(NotificacionInvestigadorPort::class, new FakeNotificacionInvestigadorAdapter);
        self::$app->instance(ColaRevisionCuratorialPort::class, new FakeColaRevisionCuratorialAdapter);

        // 3. Resolver Handlers — ya usan las instancias In-Memory
        $this->enviarSolicitudHandler = $this->make(EnviarSolicitudDepositoHandler::class);
        $this->aprobarHandler = $this->make(AprobarDocumentalmenteSolicitudHandler::class);
        $this->aceptarJustificacionesHandler = $this->make(AceptarJustificacionesAlertasHandler::class);
        $this->rechazarJustificacionesHandler = $this->make(RechazarJustificacionesAlertasHandler::class);
        $this->rechazarHandler = $this->make(RechazarDocumentalmenteSolicitudHandler::class);
        $this->priorizarHandler = $this->make(PriorizarSolicitudEnColaHandler::class);
        $this->aprobarDonacionHandler = $this->make(AprobarDonacionConTransferenciaHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    /**
     * Crea, persiste y deja una SolicitudDeposito en estado borrador con la
     * documentación oficial y la matriz Darwin Core cargadas (información completa).
     */
    private function sembrarSolicitudConDocumentacionCompleta(string $tipoTramite = 'Depósito'): SolicitudDeposito
    {
        $solicitud = SolicitudDeposito::crear(
            id: $this->repo->nextIdentity(),
            numero: $this->repo->nextNumero(),
            investigadorId: $this->investigadorId,
            tipoTramite: $tipoTramite,
        );

        // Documentación oficial + matriz Darwin Core cargada (antecedente del feature).
        $solicitud->adjuntarDocumento('Formato de Solicitud', 'documentos/formato-solicitud-test.pdf');
        $solicitud->adjuntarDocumento('Matriz Darwin Core', 'documentos/matriz-darwin-core-test.csv');

        $this->repo->guardar($solicitud);

        // Aserción de integridad: la entidad quedó persistida y es del investigador dueño.
        $persistida = $this->repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud base no fue persistida en el repositorio In-Memory');
        Assert::assertSame($this->investigadorId, $persistida->investigadorId(), 'La solicitud no pertenece al investigador esperado');
        Assert::assertSame($tipoTramite, $persistida->tipoTramite());
        Assert::assertTrue(
            $persistida->tieneDocumentoAdjunto('Formato de Solicitud'),
            'Se esperaba que el Formato de Solicitud estuviera adjunto'
        );
        Assert::assertTrue(
            $persistida->tieneDocumentoAdjunto('Matriz Darwin Core'),
            'Se esperaba que la Matriz Darwin Core estuviera adjunta'
        );

        $this->solicitudEnCurso = $solicitud;

        return $solicitud;
    }

    /**
     * Lleva una SolicitudDeposito (con documentación completa) hasta el estado
     * "Pendiente de Revisión por Curaduría".
     */
    private function sembrarSolicitudEnRevision(string $tipoTramite = 'Depósito'): SolicitudDeposito
    {
        $solicitud = $this->sembrarSolicitudConDocumentacionCompleta($tipoTramite);
        $solicitud->avanzarARevisionCuraduria();
        $this->repo->guardar($solicitud);

        // Aserción de precondición: el estado esperado quedó persistido.
        $persistida = $this->repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria),
            "Se esperaba estado 'Pendiente de Revisión por Curaduría', se obtuvo: {$persistida->estado()->value}"
        );

        $this->solicitudEnCurso = $solicitud;

        return $solicitud;
    }

    /**
     * Siembra una solicitud en revisión con una alerta ya justificada por el investigador.
     */
    private function sembrarSolicitudConAlertaJustificada(string $tipoAlerta, string $tipoTramite = 'Depósito'): SolicitudDeposito
    {
        $solicitud = $this->sembrarSolicitudEnRevision($tipoTramite);
        $solicitud->registrarAlertaJustificada(TipoAlerta::from($tipoAlerta), "Justificación del investigador para: {$tipoAlerta}");
        $this->repo->guardar($solicitud);

        $this->tipoAlertaJustificada = $tipoAlerta;
        $this->justificacionesPresentes[] = $tipoAlerta;

        // Aserción de precondición: la justificación quedó registrada.
        $persistida = $this->repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->tieneAlertaJustificada(TipoAlerta::from($tipoAlerta)),
            "Se esperaba que la alerta '{$tipoAlerta}' quedara justificada"
        );

        $this->solicitudEnCurso = $solicitud;

        return $solicitud;
    }

    /**
     * Indica si el FakeEventPublisher recibió al menos un evento de dominio del tipo dado.
     *
     * @param  class-string  $fqcnEvento
     */
    private function huboEventoDeTipo(string $fqcnEvento): bool
    {
        foreach ($this->fakePublisher->publishedEvents() as $evento) {
            if ($evento instanceof $fqcnEvento) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // ANTECEDENTES (Background)
    // =========================================================================

    #[Given('que el investigador completó la carga de los documentos oficiales y de la matriz Darwin Core')]
    public function queElInvestigadorCompletoLaCargaDeDocumentosYMatriz(): void
    {
        $solicitud = $this->sembrarSolicitudConDocumentacionCompleta();

        // Aserción del antecedente: documentos oficiales y matriz presentes (no vacíos).
        Assert::assertNotEmpty(
            $solicitud->documentosAdjuntosParaPersistir(),
            'Se esperaba que la solicitud tuviera documentos oficiales adjuntos'
        );
        Assert::assertTrue(
            $solicitud->tieneDocumentoAdjunto('Matriz Darwin Core'),
            'Se esperaba que la matriz Darwin Core estuviera cargada'
        );
    }

    // =========================================================================
    // ESCENARIO: El curador es notificado de una nueva solicitud por revisar
    // =========================================================================

    #[When('el investigador envía la solicitud para revisión documental')]
    public function elInvestigadorEnviaLaSolicitudParaRevisionDocumental(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        try {
            $this->ultimaRespuesta = ($this->enviarSolicitudHandler)(
                new EnviarSolicitudDepositoInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud pasa a estado :estadoEsperado')]
    public function laSolicitudPasaAEstado(string $estadoEsperado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        // Verificar el estado directamente en el repositorio In-Memory.
        $solicitud = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertTrue(
            $solicitud->estado()->equals(EstadoSolicitudDeposito::from($estadoEsperado)),
            "Se esperaba estado '{$estadoEsperado}', se obtuvo: {$solicitud->estado()->value}"
        );
    }

    #[Then('se notifica al curador que hay una nueva solicitud por revisar')]
    public function seNotificaAlCuradorQueHayUnaNuevaSolicitudPorRevisar(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionCuradorEnviada,
            'Se esperaba que el curador fuera notificado de la nueva solicitud por revisar'
        );

        // El envío a revisión debe haber publicado el evento de dominio correspondiente.
        Assert::assertTrue(
            $this->huboEventoDeTipo(SolicitudDepositoPendienteDeRevision::class),
            'Se esperaba que se publicara el evento SolicitudDepositoPendienteDeRevision'
        );
    }

    // =========================================================================
    // ESCENARIO: El curador aprueba una solicitud que llega sin alertas
    // =========================================================================

    #[Given('que la solicitud está :estadoPrevio')]
    public function queLaSolicitudEsta(string $estadoPrevio): void
    {
        $this->sembrarSolicitudEnRevision();

        $persistida = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitudDeposito::from($estadoPrevio)),
            "Se esperaba estado '{$estadoPrevio}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[Given('que la solicitud está en estado :estadoPrevio')]
    public function queLaSolicitudEstaEnEstado(string $estadoPrevio): void
    {
        $this->queLaSolicitudEsta($estadoPrevio);
    }

    #[Given('no presenta discrepancias de identidad ni alertas taxonómicas pendientes')]
    public function noPresentaDiscrepanciasNiAlertasPendientes(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertEmpty(
            $this->justificacionesPresentes,
            'Se esperaba que la solicitud no tuviera alertas pendientes'
        );
        Assert::assertFalse(
            $this->solicitudEnCurso->tieneAlertasPendientes(),
            'Se esperaba que la solicitud no presentara alertas taxonómicas ni discrepancias'
        );
    }

    #[When('el curador confirma que la solicitud está validada')]
    public function elCuradorConfirmaQueLaSolicitudEstaValidada(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        try {
            $this->ultimaRespuesta = ($this->aprobarHandler)(
                new AprobarDocumentalmenteSolicitudInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la aprobación queda registrada en la auditoría con el curador responsable')]
    public function laAprobacionQuedaRegistradaEnLaAuditoriaConElCuradorResponsable(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            $this->curadorId,
            $this->ultimaRespuesta->curadorResponsable,
            'La auditoría debe registrar al curador responsable de la aprobación'
        );

        // Verificar que la auditoría quedó persistida en la entidad, no solo en el Output.
        $solicitud = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertSame(
            $this->curadorId,
            $solicitud->curadorResponsable(),
            'La entidad debe registrar al curador responsable de la aprobación'
        );
        Assert::assertNotNull(
            $solicitud->aprobadaEn(),
            'La entidad debe registrar la fecha de aprobación documental'
        );

        // La aprobación documental debe haber publicado su evento de dominio.
        Assert::assertTrue(
            $this->huboEventoDeTipo(SolicitudAprobadaDocumentalmente::class),
            'Se esperaba que se publicara el evento SolicitudAprobadaDocumentalmente'
        );
    }

    #[Then('se asigna un Código QR único para identificar el lote de muestras')]
    public function seAsignaUnCodigoQrUnico(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->codigoQR,
            'Se esperaba que se asignara un Código QR único al lote de muestras'
        );

        // El Código QR debe quedar persistido en la entidad.
        $solicitud = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertNotNull(
            $solicitud->codigoQR(),
            'Se esperaba que el Código QR quedara asignado en la solicitud'
        );

        // La asignación del Código QR debe haber publicado su evento de dominio.
        Assert::assertTrue(
            $this->huboEventoDeTipo(CodigoQRAsignado::class),
            'Se esperaba que se publicara el evento CodigoQRAsignado'
        );
    }

    #[Then('el Código QR queda disponible para el investigador')]
    public function elCodigoQrQuedaDisponibleParaElInvestigador(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->codigoQRDisponible,
            'Se esperaba que el Código QR quedara disponible para el investigador'
        );
    }

    #[Then('se notifica al investigador que ya puede descargar el Código QR para la entrega física')]
    public function seNotificaAlInvestigadorQueYaPuedeDescargarElCodigoQr(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionInvestigadorEnviada,
            'Se esperaba que el investigador fuera notificado para descargar el Código QR'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: El curador aprueba validando las alertas justificadas
    // =========================================================================

    #[Given('llega con la alerta :tipoAlerta justificada por el investigador')]
    public function llegaConLaAlertaJustificadaPorElInvestigador(string $tipoAlerta): void
    {
        // El estado "Pendiente de Revisión por Curaduría" ya fue sembrado por el Given anterior.
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($tipoAlerta, 'El tipo de alerta no puede estar vacío');

        $this->solicitudEnCurso->registrarAlertaJustificada(TipoAlerta::from($tipoAlerta), "Justificación del investigador para: {$tipoAlerta}");
        $this->repo->guardar($this->solicitudEnCurso);

        $this->tipoAlertaJustificada = $tipoAlerta;
        $this->justificacionesPresentes[] = $tipoAlerta;

        // Aserción de precondición: la justificación quedó persistida.
        $persistida = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->tieneAlertaJustificada(TipoAlerta::from($tipoAlerta)),
            "Se esperaba que la alerta '{$tipoAlerta}' quedara justificada"
        );
    }

    #[When('el curador acepta todas las justificaciones pendientes')]
    public function elCuradorAceptaTodasLasJustificacionesPendientes(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty(
            $this->justificacionesPresentes,
            'Se requiere al menos una justificación pendiente para aceptar'
        );

        try {
            $this->ultimaRespuesta = ($this->aceptarJustificacionesHandler)(
                new AceptarJustificacionesAlertasInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // ESCENARIO: El curador rechaza una solicitud por no aceptar una justificación
    // =========================================================================

    #[Given('llega con varias alertas justificadas por el investigador')]
    public function llegaConVariasAlertasJustificadasPorElInvestigador(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        $alertas = [
            'Discrepancia de Identidad (Tercero)',
            'Especie no listada en catálogo',
        ];

        foreach ($alertas as $alerta) {
            $this->solicitudEnCurso->registrarAlertaJustificada(TipoAlerta::from($alerta), "Justificación del investigador para: {$alerta}");
            $this->justificacionesPresentes[] = $alerta;
        }

        $this->repo->guardar($this->solicitudEnCurso);

        // Aserción de precondición: hay varias justificaciones presentes.
        Assert::assertGreaterThan(
            1,
            count($this->justificacionesPresentes),
            'Se esperaban varias alertas justificadas'
        );
        $persistida = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida);
        foreach ($alertas as $alerta) {
            Assert::assertTrue(
                $persistida->tieneAlertaJustificada(TipoAlerta::from($alerta)),
                "Se esperaba que la alerta '{$alerta}' quedara justificada"
            );
        }
    }

    #[When('el curador no acepta al menos una de las justificaciones')]
    public function elCuradorNoAceptaAlMenosUnaDeLasJustificaciones(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($this->justificacionesPresentes, 'Se requieren justificaciones presentes');

        // El curador rechaza la primera justificación (las demás podrían ser válidas).
        $justificacionRechazada = $this->justificacionesPresentes[0];

        try {
            $this->ultimaRespuesta = ($this->rechazarJustificacionesHandler)(
                new RechazarJustificacionesAlertasInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                    justificacionesRechazadas: [$justificacionRechazada],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el comentario para el investigador indica cuáles justificaciones no fueron aceptadas')]
    public function elComentarioIndicaCualesJustificacionesNoFueronAceptadas(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->comentarioInvestigador,
            'Se esperaba un comentario indicando las justificaciones no aceptadas'
        );
        Assert::assertStringContainsString(
            $this->justificacionesPresentes[0],
            $this->ultimaRespuesta->comentarioInvestigador,
            'El comentario debe mencionar la justificación que no fue aceptada'
        );

        // El rechazo de justificaciones debe llevar la solicitud a requerir corrección.
        Assert::assertTrue(
            $this->huboEventoDeTipo(SolicitudRequiereCorreccion::class),
            'Se esperaba que se publicara el evento SolicitudRequiereCorreccion'
        );
    }

    #[Then('se notifica al investigador el rechazo de su solicitud')]
    public function seNotificaAlInvestigadorElRechazoDeSuSolicitud(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionInvestigadorEnviada,
            'Se esperaba que el investigador fuera notificado del rechazo'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: El curador rechaza justificando el motivo
    // =========================================================================

    #[When('el curador la rechaza como :tipoRechazo indicando el motivo')]
    public function elCuradorLaRechazaComoIndicandoElMotivo(string $tipoRechazo): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertContains(
            $tipoRechazo,
            ['Subsanable', 'Definitivo'],
            "El tipo de rechazo '{$tipoRechazo}' no es reconocido"
        );

        $motivo = "Motivo del rechazo {$tipoRechazo}: la documentación presenta inconsistencias.";

        try {
            $this->ultimaRespuesta = ($this->rechazarHandler)(
                new RechazarDocumentalmenteSolicitudInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                    tipoRechazo: $tipoRechazo,
                    motivo: $motivo,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el motivo del rechazo queda registrado como comentario para el investigador')]
    public function elMotivoDelRechazoQuedaRegistradoComoComentario(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->comentarioInvestigador,
            'Se esperaba que el motivo del rechazo quedara registrado como comentario'
        );

        // Verificar que el comentario también quedó persistido en la entidad.
        $solicitud = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud);
        Assert::assertNotEmpty(
            $solicitud->comentarioCurador(),
            'Se esperaba que el comentario del curador quedara persistido en la solicitud'
        );

        // El rechazo documental debe haber publicado su evento de dominio.
        Assert::assertTrue(
            $this->huboEventoDeTipo(SolicitudRechazadaDocumentalmente::class),
            'Se esperaba que se publicara el evento SolicitudRechazadaDocumentalmente'
        );
    }

    // =========================================================================
    // ESCENARIO: Priorización de una solicitud en la cola de revisión curatorial
    // =========================================================================

    #[When('el curador la clasifica como :prioridad')]
    public function elCuradorLaClasificaComo(string $prioridad): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($prioridad, 'La prioridad no puede estar vacía');

        try {
            $this->ultimaRespuesta = ($this->priorizarHandler)(
                new PriorizarSolicitudEnColaInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    prioridad: $prioridad,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la solicitud se posiciona al inicio de la cola de revisión de curaduría')]
    public function laSolicitudSePosicionaAlInicioDeLaCola(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            1,
            $this->ultimaRespuesta->posicionEnCola,
            'Se esperaba que la solicitud prioritaria quedara en la posición 1 de la cola'
        );

        // La priorización debe haber publicado su evento de dominio.
        Assert::assertTrue(
            $this->huboEventoDeTipo(SolicitudPriorizada::class),
            'Se esperaba que se publicara el evento SolicitudPriorizada'
        );
    }

    // =========================================================================
    // ESCENARIO (@donacion): El curador aprueba una donación con transferencia
    // =========================================================================

    #[Given('que la solicitud de :tipoTramite está :estadoPrevio')]
    public function queLaSolicitudDeTipoEsta(string $tipoTramite, string $estadoPrevio): void
    {
        Assert::assertContains(
            $tipoTramite,
            ['Depósito', 'Donación'],
            "El tipo de trámite '{$tipoTramite}' no es reconocido"
        );

        $this->sembrarSolicitudEnRevision($tipoTramite);

        $persistida = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida);
        Assert::assertSame($tipoTramite, $persistida->tipoTramite());
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitudDeposito::from($estadoPrevio)),
            "Se esperaba estado '{$estadoPrevio}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    #[Given('no presenta alertas taxonómicas ni discrepancias de identidad')]
    public function noPresentaAlertasTaxonomicasNiDiscrepancias(): void
    {
        $this->noPresentaDiscrepanciasNiAlertasPendientes();
    }

    #[When('el curador confirma que la solicitud y su Carta de Cesión están validadas')]
    public function elCuradorConfirmaQueLaSolicitudYSuCartaDeCesionEstanValidadas(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        try {
            $this->ultimaRespuesta = ($this->aprobarDonacionHandler)(
                new AprobarDonacionConTransferenciaInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se genera el :nombreActa')]
    public function seGeneraElActa(string $nombreActa): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            'Acta de Transferencia de Dominio',
            $nombreActa,
            "El acta esperada no coincide: '{$nombreActa}'"
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->actaTransferenciaDominioRuta,
            'Se esperaba que se generara el Acta de Transferencia de Dominio'
        );

        // El acta debe quedar persistida en la entidad.
        $solicitud = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertNotNull(
            $solicitud->actaTransferenciaDominio(),
            'Se esperaba que el Acta de Transferencia de Dominio quedara registrada en la solicitud'
        );

        // La generación del acta debe haber publicado su evento de dominio.
        Assert::assertTrue(
            $this->huboEventoDeTipo(ActaTransferenciaDominioGenerada::class),
            'Se esperaba que se publicara el evento ActaTransferenciaDominioGenerada'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO (@donacion): rechazo de donación por Carta de Cesión
    // =========================================================================

    #[Given('el archivo adjunto como Carta de Cesión no corresponde a una carta de cesión válida')]
    public function elArchivoAdjuntoComoCartaDeCesionNoCorresponde(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        $this->cartaCesionValida = false;
        $this->solicitudEnCurso->adjuntarDocumento('Carta de Cesión', 'documentos/archivo-incorrecto-test.pdf');
        $this->repo->guardar($this->solicitudEnCurso);

        // Aserción de precondición: el documento existe pero se considera inválido.
        $persistida = $this->repo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->tieneDocumentoAdjunto('Carta de Cesión'),
            'Se esperaba que existiera un archivo adjunto como Carta de Cesión'
        );
        Assert::assertFalse(
            $this->cartaCesionValida,
            'La Carta de Cesión debe estar marcada como no válida para este escenario'
        );
    }
}
