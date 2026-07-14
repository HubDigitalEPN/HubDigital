<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote\RechazarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote\RechazarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeNotificacionInvestigadorAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryRecepcionLoteRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudDepositoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: recepcion_muestras_fisicas.feature
 * Capability: RecepcionValidacionLotesEspecimenesYDatos
 *
 * Estrategia: 100% In-Memory. Cero persistencia real.
 *
 * El dominio, los casos de uso y el repositorio de recepción física ya están
 * implementados: este Context ejercita los Handlers reales (Iniciar/Aprobar/Rechazar/
 * Aceptar) contra repositorios In-Memory y fakes de notificación. La recepción del
 * lote se materializa con IniciarRecepcionLoteHandler al acceder por Código QR.
 */
final class RecepcionMuestrasFisicasContext extends BaseContext
{
    // ── Repositorios In-Memory (acceso directo para @Given y @Then) ──────────

    private InMemorySolicitudDepositoRepository $solicitudRepo;

    private InMemoryRecepcionLoteRepository $recepcionRepo;

    // ── Adaptadores fake inspeccionables ─────────────────────────────────────

    private FakeEventPublisherAdapter $fakePublisher;

    private FakeNotificacionInvestigadorAdapter $fakeNotificacionInvestigador;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private AprobarDocumentalmenteSolicitudHandler $aprobarDocumentalHandler;

    private AprobarDonacionConTransferenciaHandler $aprobarDonacionHandler;

    private IniciarRecepcionLoteHandler $iniciarRecepcionHandler;

    private AprobarRecepcionLoteHandler $aprobarRecepcionHandler;

    private RechazarRecepcionLoteHandler $rechazarRecepcionHandler;

    private AceptarRecepcionConObservacionesHandler $aceptarConObservacionesHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudDeposito $solicitudEnCurso = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-rec-001';

    private string $curadorId = 'cur-001';

    private ?CodigoQRLote $codigoQrLote = null;

    /** @var array<int, array{item: string, resultado: string}> */
    private array $itemsVerificacion = [];

    private ?string $motivoFallo = null;

    /** @var list<string> Ítems del checklist marcados como no conformes. */
    private array $itemsNoConformes = [];

    // ── Constructor — inyecta In-Memory antes de resolver Handlers ───────────

    public function __construct()
    {
        // 0. Asegurar que Laravel está booteado antes de usar el container.
        self::bootApp();

        // 1. Crear instancias In-Memory fresh para este escenario.
        $this->solicitudRepo = new InMemorySolicitudDepositoRepository;
        $this->recepcionRepo = new InMemoryRecepcionLoteRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->fakeNotificacionInvestigador = new FakeNotificacionInvestigadorAdapter;

        // 2. Interceptar el container para que los Handlers reciban estas instancias.
        self::$app->instance(SolicitudDepositoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(RecepcionLoteRepositoryInterface::class, $this->recepcionRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(NotificacionInvestigadorPort::class, $this->fakeNotificacionInvestigador);

        // 3. Resolver Handlers — ya usan las instancias In-Memory.
        $this->aprobarDocumentalHandler = $this->make(AprobarDocumentalmenteSolicitudHandler::class);
        $this->aprobarDonacionHandler = $this->make(AprobarDonacionConTransferenciaHandler::class);
        $this->iniciarRecepcionHandler = $this->make(IniciarRecepcionLoteHandler::class);
        $this->aprobarRecepcionHandler = $this->make(AprobarRecepcionLoteHandler::class);
        $this->rechazarRecepcionHandler = $this->make(RechazarRecepcionLoteHandler::class);
        $this->aceptarConObservacionesHandler = $this->make(AceptarRecepcionConObservacionesHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    /**
     * Crea, envía y aprueba documentalmente una SolicitudDeposito reproduciendo el
     * flujo real (cadena de handlers). Deja la solicitud en estado
     * "Aprobada Documentalmente" con un Código QR de lote vigente asignado.
     */
    private function sembrarSolicitudAprobadaDocumentalmente(string $tipoTramite): SolicitudDeposito
    {
        $solicitud = SolicitudDeposito::crear(
            id: $this->solicitudRepo->nextIdentity(),
            numero: $this->solicitudRepo->nextNumero(),
            investigadorId: $this->investigadorId,
            tipoTramite: $tipoTramite,
        );

        // Documentación oficial + matriz Darwin Core (información completa exigida).
        $solicitud->adjuntarDocumento('Formato de Solicitud', 'documentos/formato-solicitud-test.pdf');
        $solicitud->adjuntarDocumento('Matriz Darwin Core', 'documentos/matriz-darwin-core-test.csv');
        $solicitud->avanzarARevisionCuraduria();
        $this->solicitudRepo->guardar($solicitud);

        // Aprobación documental por la vía real según el tipo de trámite.
        if (TipoTramite::from($tipoTramite)->equals(TipoTramite::Donacion)) {
            ($this->aprobarDonacionHandler)(
                new AprobarDonacionConTransferenciaInput(
                    solicitudId: (string) $solicitud->id(),
                    curadorId: $this->curadorId,
                )
            );
        } else {
            ($this->aprobarDocumentalHandler)(
                new AprobarDocumentalmenteSolicitudInput(
                    solicitudId: (string) $solicitud->id(),
                    curadorId: $this->curadorId,
                )
            );
        }

        // Aserciones de integridad de la precondición.
        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud base no fue persistida en el repositorio In-Memory');
        Assert::assertSame($this->investigadorId, $persistida->investigadorId(), 'La solicitud no pertenece al investigador esperado');
        Assert::assertSame($tipoTramite, $persistida->tipoTramite(), "Se esperaba un trámite de '{$tipoTramite}'");
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitudDeposito::AprobadaDocumentalmente),
            "Se esperaba estado 'Aprobada Documentalmente', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotNull(
            $persistida->codigoQR(),
            'Se esperaba que la solicitud aprobada tuviera un Código QR de lote asignado'
        );

        $this->solicitudEnCurso = $persistida;
        $this->codigoQrLote = $persistida->codigoQR();

        return $persistida;
    }

    /**
     * Indica si el FakeEventPublisher recibió al menos un evento del tipo dado.
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

    /**
     * Materializa la recepción del lote de la solicitud en curso invocando el caso de
     * uso real, y asevera que el lote quedó iniciado y localizable por solicitud.
     */
    private function iniciarRecepcionDelLote(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        ($this->iniciarRecepcionHandler)(
            new IniciarRecepcionLoteInput(
                solicitudId: (string) $this->solicitudEnCurso->id(),
                curadorId: $this->curadorId,
            )
        );

        $lote = $this->recepcionRepo->buscarPorSolicitudId($this->solicitudEnCurso->id());
        Assert::assertNotNull($lote, 'La recepción del lote no fue iniciada');
    }

    // =========================================================================
    // ANTECEDENTES (Background)
    // =========================================================================

    #[Given('que el investigador entrega el lote físico de una solicitud :estado')]
    public function queElInvestigadorEntregaElLoteFisicoDeUnaSolicitud(string $estado): void
    {
        // Por defecto se siembra un Depósito; los escenarios que exijan otro tipo de
        // trámite lo re-siembran en su propio paso "es un trámite de <tipo_tramite>".
        $solicitud = $this->sembrarSolicitudAprobadaDocumentalmente('Depósito');

        Assert::assertTrue(
            $solicitud->estado()->equals(EstadoSolicitudDeposito::from($estado)),
            "Se esperaba una solicitud en estado '{$estado}', se obtuvo: {$solicitud->estado()->value}"
        );
    }

    #[Given('el curador accede a los datos de la solicitud a partir de su Código QR')]
    public function elCuradorAccedeALosDatosDeLaSolicitudAPartirDeSuCodigoQr(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotNull($this->codigoQrLote, 'La solicitud no tiene un Código QR con el cual acceder al lote');

        // El curador resuelve la solicitud a partir del QR que rotula su lote físico.
        $solicitud = $this->solicitudRepo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'No se pudo recuperar la solicitud desde el repositorio');
        Assert::assertNotNull($solicitud->codigoQR(), 'La solicitud recuperada no expone un Código QR');
        Assert::assertTrue(
            $solicitud->codigoQR()->equals($this->codigoQrLote),
            'El Código QR de la solicitud no coincide con el asignado al aprobarse documentalmente'
        );

        // El curador abre la recepción física del lote entregado.
        $this->iniciarRecepcionDelLote();
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Recepción física conforme cuando el lote supera la
    // lista de verificación
    // =========================================================================

    #[Given('que la solicitud es un trámite de :tipo_tramite')]
    public function queLaSolicitudEsUnTramiteDe(string $tipo_tramite): void
    {
        // Re-siembra la solicitud con el tipo de trámite requerido por el ejemplo.
        $solicitud = $this->sembrarSolicitudAprobadaDocumentalmente($tipo_tramite);

        Assert::assertTrue(
            TipoTramite::from($solicitud->tipoTramite())->equals(TipoTramite::from($tipo_tramite)),
            "Se esperaba un trámite de '{$tipo_tramite}', se obtuvo: {$solicitud->tipoTramite()}"
        );

        // Re-abre la recepción para el lote de la solicitud recién sembrada.
        $this->iniciarRecepcionDelLote();
    }

    #[Given('el lote cumple todos los ítems de la lista de verificación de recepción:')]
    public function elLoteCumpleTodosLosItemsDeLaListaDeVerificacion(TableNode $tabla): void
    {
        $items = [];
        foreach ($tabla->getRows() as $indice => $fila) {
            if ($indice === 0) {
                continue; // saltar encabezado
            }

            $item = trim($fila[0]);
            $resultado = trim($fila[1]);

            Assert::assertNotSame('', $item, 'El ítem de verificación no puede estar vacío');
            Assert::assertNotSame('', $resultado, "El resultado del ítem '{$item}' no puede estar vacío");

            $items[] = ['item' => $item, 'resultado' => $resultado];
        }

        Assert::assertNotEmpty($items, 'La lista de verificación de recepción no puede estar vacía');
        $this->itemsVerificacion = $items;
    }

    #[When('el curador aprueba la recepción del lote')]
    public function elCuradorApruebaLaRecepcionDelLote(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($this->itemsVerificacion, 'La recepción conforme requiere una lista de verificación cumplida');

        try {
            $this->ultimaRespuesta = ($this->aprobarRecepcionHandler)(
                new AprobarRecepcionLoteInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                    itemsVerificacion: $this->itemsVerificacion,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el lote pasa a estado :estado')]
    public function elLotePasaAEstado(string $estado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $lote = $this->recepcionRepo->buscarPorSolicitudId($this->solicitudEnCurso->id());
        Assert::assertNotNull($lote, 'No se encontró el lote de recepción en el repositorio');
        Assert::assertTrue(
            $lote->estado()->equals(EstadoRecepcionLote::from($estado)),
            "Se esperaba estado de lote '{$estado}', se obtuvo: {$lote->estado()->value}"
        );
    }

    #[Then('se emite el "Acta Digital de Recepción"')]
    public function seEmiteElActaDigitalDeRecepcion(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->actaDigitalRecepcionEmitida,
            'Se esperaba que se emitiera el Acta Digital de Recepción'
        );

        $lote = $this->recepcionRepo->buscarPorSolicitudId($this->solicitudEnCurso->id());
        Assert::assertNotNull($lote, 'No se encontró el lote de recepción en el repositorio');
        Assert::assertTrue(
            $lote->actaEmitida(),
            'Se esperaba que el lote registrara el Acta Digital de Recepción emitida'
        );
    }

    #[Then('los especímenes asociados ingresan a la colección en estado :estado_coleccion')]
    public function losEspecimenesIngresanALaColeccionEnEstado(string $estado_coleccion): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $lote = $this->recepcionRepo->buscarPorSolicitudId($this->solicitudEnCurso->id());
        Assert::assertNotNull($lote, 'No se encontró el lote de recepción en el repositorio');
        Assert::assertNotNull($lote->estadoColeccion(), 'El lote no registró el estado de colección de sus especímenes');
        Assert::assertTrue(
            $lote->estadoColeccion()->equals(EstadoColeccion::from($estado_coleccion)),
            "Se esperaba estado de colección '{$estado_coleccion}', se obtuvo: {$lote->estadoColeccion()->value}"
        );
    }

    #[Then('se notifica al investigador la finalización exitosa de la entrega')]
    public function seNotificaAlInvestigadorLaFinalizacionExitosa(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionInvestigadorEnviada,
            'Se esperaba que se notificara al investigador la finalización exitosa de la entrega'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Rechazo y devolución al investigador cuando la
    // anomalía es subsanable
    // =========================================================================

    #[When('el curador registra el fallo de integridad subsanable :motivo_fallo')]
    public function elCuradorRegistraElFalloDeIntegridadSubsanable(string $motivo_fallo): void
    {
        Assert::assertNotSame('', trim($motivo_fallo), 'El motivo del fallo subsanable no puede estar vacío');
        $this->motivoFallo = $motivo_fallo;
    }

    #[Then('el curador rechaza la recepción del lote')]
    public function elCuradorRechazaLaRecepcionDelLote(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotNull($this->motivoFallo, 'El rechazo requiere un fallo de integridad subsanable registrado');

        try {
            $this->ultimaRespuesta = ($this->rechazarRecepcionHandler)(
                new RechazarRecepcionLoteInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                    motivoFallo: $this->motivoFallo,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se emite una orden de :accion_correctiva para el investigador')]
    public function seEmiteUnaOrdenDeAccionCorrectiva(string $accion_correctiva): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            $accion_correctiva,
            $this->ultimaRespuesta->ordenAccionCorrectiva,
            "Se esperaba una orden de '{$accion_correctiva}', se obtuvo: '{$this->ultimaRespuesta->ordenAccionCorrectiva}'"
        );
    }

    #[Then('el Código QR permanece vigente para reintentar la recepción del mismo lote')]
    public function elCodigoQrPermaneceVigenteParaReintentar(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->codigoQrVigente,
            'Se esperaba que el Código QR permaneciera vigente tras el rechazo'
        );

        // El QR del lote no debe cambiar: sigue siendo el mismo con el que se accedió.
        $solicitud = $this->solicitudRepo->buscarPorId($this->solicitudEnCurso->id());
        Assert::assertNotNull($solicitud, 'No se pudo recuperar la solicitud desde el repositorio');
        Assert::assertNotNull($solicitud->codigoQR(), 'Se esperaba que el Código QR siguiera asignado');
        Assert::assertTrue(
            $solicitud->codigoQR()->equals($this->codigoQrLote),
            'El Código QR cambió tras el rechazo; debía permanecer vigente para el reintento'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Aceptación con observaciones según los ítems no
    // conformes del lote
    // =========================================================================

    #[Given('el lote presenta el ítem no conforme :item_no_conforme')]
    public function elLotePresentaElItemNoConforme(string $item_no_conforme): void
    {
        Assert::assertNotSame('', trim($item_no_conforme), 'El ítem no conforme no puede estar vacío');
        $this->itemsNoConformes = [$item_no_conforme];
    }

    #[When('el curador acepta la recepción del lote con observaciones')]
    public function elCuradorAceptaLaRecepcionDelLoteConObservaciones(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($this->itemsNoConformes, 'La aceptación con observaciones requiere al menos un ítem no conforme');

        try {
            $this->ultimaRespuesta = ($this->aceptarConObservacionesHandler)(
                new AceptarRecepcionConObservacionesInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    curadorId: $this->curadorId,
                    itemsNoConformes: $this->itemsNoConformes,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('las observaciones quedan registradas en el "Acta Digital de Recepción"')]
    public function lasObservacionesQuedanRegistradasEnElActa(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->observacionesRegistradasEnActa,
            'Se esperaba que las observaciones quedaran registradas en el Acta Digital de Recepción'
        );

        $lote = $this->recepcionRepo->buscarPorSolicitudId($this->solicitudEnCurso->id());
        Assert::assertNotNull($lote, 'No se encontró el lote de recepción en el repositorio');
        Assert::assertNotEmpty(
            $lote->observaciones(),
            'Se esperaba que el lote conservara las observaciones registradas'
        );
        foreach ($this->itemsNoConformes as $item) {
            Assert::assertContains(
                $item,
                $lote->observaciones(),
                "Se esperaba que el ítem no conforme '{$item}' quedara registrado como observación en el lote"
            );
        }
    }

    #[Then('se notifica al investigador la finalización de la entrega con observaciones')]
    public function seNotificaAlInvestigadorLaFinalizacionConObservaciones(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionInvestigadorEnviada,
            'Se esperaba que se notificara al investigador la finalización de la entrega con observaciones'
        );
    }
}
