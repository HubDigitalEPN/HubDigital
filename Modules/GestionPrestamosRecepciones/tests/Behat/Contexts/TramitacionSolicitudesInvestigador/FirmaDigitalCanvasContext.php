<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente\FirmarActaDigitalmenteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente\FirmarActaDigitalmenteInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaNoPerteneceAlInvestigador;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaBase64Invalida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakePdfGeneratorAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: firma_digital_canvas.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class FirmaDigitalCanvasContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    private FakePdfGeneratorAdapter $fakePdfGenerator;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private FirmarActaDigitalmenteHandler $handler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?ActaPrestamo $actaExistente = null;

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-001';

    private string $curadorId = 'cur-001';

    private const FIRMA_BASE64_VALIDA = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // ── Constructor ───────────────────────────────────────────────────────────

    public function __construct()
    {
        self::bootApp();

        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->fakePdfGenerator = new FakePdfGeneratorAdapter;

        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(PdfGeneratorPort::class, $this->fakePdfGenerator);

        $this->handler = $this->make(FirmarActaDigitalmenteHandler::class);
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    private function sembrarSolicitudAprobada(string $investigadorId): SolicitudPrestamo
    {
        $items = [
            ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenCodigoExterno: 'ESP-001',
                cantidadSolicitada: 2,
            ),
        ];

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            numeroSolicitud: NumeroSolicitud::generate(),
            investigadorId: $investigadorId,
            alcancePrestamo: AlcancePrestamo::Nacional,
            tituloEstudio: 'Revisión taxonómica del género Morpho en Ecuador',
            institucionAdscripcion: 'Universidad Central del Ecuador',
            lineaInvestigacion: 'Entomología sistemática',
            propositoPrestamo: 'Estudio comparativo de morfología alar',
            duracionPropuestaMeses: 3,
            items: $items,
        );

        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $solicitud->pullEvents();
        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    private function sembrarActaEnEstado(EstadoActa $estado, string $investigadorId): ActaPrestamo
    {
        $solicitud = $this->sembrarSolicitudAprobada($investigadorId);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');
        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            numeroPrestamo: NumeroPrestamo::generate(),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        if (in_array($estado, [EstadoActa::PendienteFirma, EstadoActa::PendienteValidacion, EstadoActa::Validada], true)) {
            $acta->marcarEnviada($investigadorId);
        }

        if (in_array($estado, [EstadoActa::PendienteValidacion, EstadoActa::Validada], true)) {
            $acta->subirFirma(
                pdfFirmadoRuta: 'actas-firmadas/'.(string) $solicitud->id().'-firmada.pdf',
                documentoIdentidadRuta: 'documentos-identidad/'.(string) $solicitud->id().'-id.pdf',
            );
        }

        if ($estado === EstadoActa::Validada) {
            $acta->validar(validadoPor: $this->curadorId);
        }

        $acta->pullEvents();
        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;

        return $acta;
    }

    // =========================================================================
    // GIVEN
    // =========================================================================

    #[Given('que existe un acta en estado pendiente de firma asignada al investigador')]
    public function queExisteUnActaEnEstadoPendienteDeFirmaAsignadaAlInvestigador(): void
    {
        $acta = $this->sembrarActaEnEstado(EstadoActa::PendienteFirma, $this->investigadorId);

        Assert::assertNotNull(
            $this->actaRepo->buscarPorId($acta->id()),
            'El acta no fue encontrada tras sembrarla'
        );
        Assert::assertTrue(
            $acta->estado()->equals(EstadoActa::PendienteFirma),
            "Precondición fallida: se esperaba estado 'pendiente_firma'"
        );
    }

    #[Given('que existe un acta en estado :estadoStr asignada al investigador')]
    public function queExisteUnActaEnEstadoAsignadaAlInvestigador(string $estadoStr): void
    {
        $estado = EstadoActa::from($estadoStr);
        $this->sembrarActaEnEstado($estado, $this->investigadorId);
    }

    // =========================================================================
    // WHEN
    // =========================================================================

    #[When('el investigador envía su firma en formato PNG base64 válido')]
    public function elInvestigadorEnviaSuFirmaEnFormatoPngBase64Valido(): void
    {
        Assert::assertNotNull($this->actaExistente);

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new FirmarActaDigitalmenteInput(
                    actaId: (string) $this->actaExistente->id(),
                    investigadorId: $this->investigadorId,
                    firmaBase64: self::FIRMA_BASE64_VALIDA,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('otro investigador intenta firmar digitalmente esa acta')]
    public function otroInvestigadorIntentaFirmarDigitalmenteEsaActa(): void
    {
        Assert::assertNotNull($this->actaExistente);

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new FirmarActaDigitalmenteInput(
                    actaId: (string) $this->actaExistente->id(),
                    investigadorId: 'inv-otro-999',
                    firmaBase64: self::FIRMA_BASE64_VALIDA,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el investigador envía un base64 con prefijo incorrecto')]
    public function elInvestigadorEnviaUnBase64ConPrefijoIncorrecto(): void
    {
        Assert::assertNotNull($this->actaExistente);

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new FirmarActaDigitalmenteInput(
                    actaId: (string) $this->actaExistente->id(),
                    investigadorId: $this->investigadorId,
                    firmaBase64: 'data:image/jpeg;base64,/9j/4AAQSkZJRgAB',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el investigador envía un base64 con contenido corrupto')]
    public function elInvestigadorEnviaUnBase64ConContenidoCorrupto(): void
    {
        Assert::assertNotNull($this->actaExistente);

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new FirmarActaDigitalmenteInput(
                    actaId: (string) $this->actaExistente->id(),
                    investigadorId: $this->investigadorId,
                    firmaBase64: 'data:image/png;base64,!!!contenido-invalido!!!',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el investigador intenta firmar digitalmente con una firma válida')]
    public function elInvestigadorIntentaFirmarDigitalmenteConUnaFirmaValida(): void
    {
        Assert::assertNotNull($this->actaExistente);

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new FirmarActaDigitalmenteInput(
                    actaId: (string) $this->actaExistente->id(),
                    investigadorId: $this->investigadorId,
                    firmaBase64: self::FIRMA_BASE64_VALIDA,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // =========================================================================
    // THEN
    // =========================================================================

    #[Then('el acta permanece en estado pendiente de firma')]
    public function elActaPermanecerEnEstadoPendienteDeFirma(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoActa::PendienteFirma->value,
            $this->ultimaRespuesta->estadoActa,
        );

        $persistida = $this->actaRepo->buscarPorId($this->actaExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue($persistida->estado()->equals(EstadoActa::PendienteFirma));
    }

    #[Then('la ruta de la firma digital queda registrada en el acta')]
    public function laRutaDeLaFirmaDigitalQuedaRegistradaEnElActa(): void
    {
        $persistida = $this->actaRepo->buscarPorId($this->actaExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertNotEmpty(
            $persistida->pdfFirmadoRuta(),
            'El acta debe tener la ruta de la imagen de firma registrada'
        );
        Assert::assertNull(
            $persistida->firmadaSubidaEn(),
            'firmadaSubidaEn no debe establecerse hasta subir el documento de identidad'
        );
        Assert::assertTrue(
            $this->fakePdfGenerator->fueInvocado(),
            'El adaptador de PDF debió haber sido invocado para almacenar la imagen de firma'
        );
    }

    #[Then('el sistema rechaza la operación por pertenencia')]
    public function elSistemaRechazaLaOperacionPorPertenencia(): void
    {
        Assert::assertNull($this->ultimaRespuesta);
        Assert::assertInstanceOf(
            ActaNoPerteneceAlInvestigador::class,
            $this->excepcionCapturada,
            'Se esperaba excepción ActaNoPerteneceAlInvestigador, se obtuvo: '.($this->excepcionCapturada?->getMessage() ?? 'ninguna')
        );
    }

    #[Then('el sistema rechaza la operación por firma inválida')]
    public function elSistemaRechazaLaOperacionPorFirmaInvalida(): void
    {
        Assert::assertNull($this->ultimaRespuesta);
        Assert::assertInstanceOf(
            FirmaBase64Invalida::class,
            $this->excepcionCapturada,
            'Se esperaba excepción FirmaBase64Invalida, se obtuvo: '.($this->excepcionCapturada?->getMessage() ?? 'ninguna')
        );
    }

    #[Then('el sistema rechaza la operación por estado inválido')]
    public function elSistemaRechazaLaOperacionPorEstadoInvalido(): void
    {
        Assert::assertNull($this->ultimaRespuesta);
        Assert::assertInstanceOf(
            TransicionDeEstadoInvalidaException::class,
            $this->excepcionCapturada,
            'Se esperaba excepción TransicionDeEstadoInvalidaException, se obtuvo: '.($this->excepcionCapturada?->getMessage() ?? 'ninguna')
        );
    }
}
