<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryConfiguracionGlobalRecordatoriosRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryRecordatorioDevolucionRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: habilitacion_envio_internacional.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 */
final class HabilitacionEnvioInternacionalContext extends BaseContext
{
    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private InMemoryPrestamoRepository $prestamoRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    private HabilitarEnvioInternacionalHandler $habilitarHandler;

    private ValidarActaFirmadaHandler $validarActaHandler;

    private IniciarPrestamoHandler $iniciarPrestamoHandler;

    private ?ActaPrestamo $actaExistente = null;

    private ?Prestamo $prestamoExistente = null;

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $curadorId = 'cur-001';

    private string $investigadorId = 'inv-001';

    public function __construct()
    {
        self::bootApp();

        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->prestamoRepo = new InMemoryPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(PrestamoRepositoryInterface::class, $this->prestamoRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(ConfiguracionGlobalRecordatoriosRepositoryInterface::class, new InMemoryConfiguracionGlobalRecordatoriosRepository);
        self::$app->instance(RecordatorioDevolucionRepositoryInterface::class, new InMemoryRecordatorioDevolucionRepository);

        $this->habilitarHandler = $this->make(HabilitarEnvioInternacionalHandler::class);
        $this->validarActaHandler = $this->make(ValidarActaFirmadaHandler::class);
        $this->iniciarPrestamoHandler = $this->make(IniciarPrestamoHandler::class);
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    private function sembrarSolicitudAprobada(AlcancePrestamo $alcance): SolicitudPrestamo
    {
        $items = [
            ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenId: (string) \Illuminate\Support\Str::uuid(),
                especimenCodigoExterno: 'ESP-001',
                cantidadSolicitada: 2,
            ),
        ];

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            alcancePrestamo: $alcance,
            tituloEstudio: 'Estudio de Morpho azul en Ecuador',
            institucionAdscripcion: 'Universidad Central del Ecuador',
            lineaInvestigacion: 'Entomología sistemática',
            propositoPrestamo: 'Revisión taxonómica comparativa',
            duracionPropuestaMeses: 6,
            items: $items,
        );

        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $solicitud->pullEvents();
        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    private function sembrarActaConPrestamoPendienteMinisterio(): void
    {
        $solicitud = $this->sembrarSolicitudAprobada(AlcancePrestamo::Internacional);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+6 months');
        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Internacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        $acta->marcarEnviada($this->investigadorId);
        $acta->subirFirma('actas/firmadas/test-firmada.pdf', 'identidad/test-cedula.pdf');
        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;

        $prestamo = Prestamo::iniciar(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: $acta->id(),
            codigoPrestamo: $acta->codigoPrestamo(),
            investigadorId: $this->investigadorId,
            alcancePrestamo: AlcancePrestamo::Internacional,
            iniciadoEn: $ahora,
            fechaFin: $fechaFin,
        );

        $prestamo->pullEvents();
        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;
    }

    // ── Given steps ───────────────────────────────────────────────────────────

    #[Given('que existe un préstamo internacional pendiente de documento del ministerio')]
    public function queExisteUnPrestamoInternacionalPendienteDeDocumentoDelMinisterio(): void
    {
        $this->sembrarActaConPrestamoPendienteMinisterio();

        Assert::assertEquals(
            EstadoPrestamo::PendienteDocumentoMinisterio->value,
            $this->prestamoExistente->estado()->value,
        );
    }

    #[Given('que existe un acta de alcance nacional en estado validada')]
    public function queExisteUnActaDeAlcanceNacionalEnEstadoValidada(): void
    {
        $solicitud = $this->sembrarSolicitudAprobada(AlcancePrestamo::Nacional);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');
        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        $acta->marcarEnviada($this->investigadorId);
        $acta->subirFirma('actas/firmadas/test-firmada.pdf', 'identidad/test-cedula.pdf');
        $acta->validarConFirmaCurador($this->curadorId, 'actas-firmadas-curador/test.pdf');
        $acta->pullEvents();
        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;
    }

    #[Given('que existe una solicitud de alcance nacional aprobada con su acta en estado pendiente de validación')]
    public function queExisteUnaSolicitudNacionalAprobadaConActaEnPendienteValidacion(): void
    {
        $solicitud = $this->sembrarSolicitudAprobada(AlcancePrestamo::Nacional);

        $ahora = new DateTimeImmutable;
        $fechaFin = $ahora->modify('+3 months');
        $pdfRuta = 'actas/'.(string) $solicitud->id().'.pdf';

        $acta = ActaPrestamo::emitir(
            id: $this->actaRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            solicitudPrestamoId: $solicitud->id(),
            tipoPrestamo: TipoPrestamo::Temporal,
            alcancePrestamo: AlcancePrestamo::Nacional,
            fechaInicio: $ahora,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
        );

        $acta->marcarEnviada($this->investigadorId);
        $acta->subirFirma('actas/firmadas/test-firmada.pdf', 'identidad/test-cedula.pdf');
        $acta->pullEvents();
        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;

        Assert::assertEquals(EstadoActa::PendienteValidacion->value, $acta->estado()->value);
    }

    // ── When steps ────────────────────────────────────────────────────────────

    #[When('el curador sube el documento de aprobación del Ministerio del Ambiente')]
    public function elCuradorSubeElDocumentoDeAprobacionDelMinisterioDelAmbiente(): void
    {
        try {
            $this->ultimaRespuesta = $this->habilitarHandler->handle(
                new HabilitarEnvioInternacionalInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                    documentoRuta: 'exportaciones/aprobacion-ministerio-2026.pdf',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el curador intenta habilitar el envío sin proporcionar una ruta de documento')]
    public function elCuradorIntentaHabilitarElEnvioSinDocumento(): void
    {
        try {
            $this->ultimaRespuesta = $this->habilitarHandler->handle(
                new HabilitarEnvioInternacionalInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                    documentoRuta: '',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el curador intenta adjuntar un documento de exportación al acta nacional')]
    public function elCuradorIntentaAdjuntarDocumentoExportacionAlActaNacional(): void
    {
        try {
            $this->actaExistente->adjuntarDocumentoExportacion('exportaciones/doc.pdf');
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el curador valida el acta firmada e inicia el préstamo')]
    public function elCuradorValidaElActaFirmada(): void
    {
        try {
            $this->ultimaRespuesta = $this->validarActaHandler->handle(
                new ValidarActaFirmadaInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                    pdfFirmadoCuradorRuta: 'actas-firmadas-curador/'.(string) $this->actaExistente->id().'.pdf',
                )
            );

            $this->iniciarPrestamoHandler->handle(
                new IniciarPrestamoInput(
                    actaPrestamoId: (string) $this->actaExistente->id(),
                    solicitudId: (string) $this->solicitudExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // ── Then steps ────────────────────────────────────────────────────────────

    #[Then('el préstamo pasa al estado en tránsito')]
    public function elPrestamoBasaAlEstadoEnTransito(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción: '.$this->excepcionCapturada?->getMessage());

        $prestamoActualizado = $this->prestamoRepo->buscarPorActaId($this->actaExistente->id());

        Assert::assertNotNull($prestamoActualizado);
        Assert::assertEquals(EstadoPrestamo::EnTransito->value, $prestamoActualizado->estado()->value);
    }

    #[Then('el documento queda adjunto al acta')]
    public function elDocumentoQuedaAdjuntoAlActa(): void
    {
        $actaActualizada = $this->actaRepo->buscarPorId($this->actaExistente->id());

        Assert::assertNotNull($actaActualizada);
        Assert::assertNotNull($actaActualizada->documentoExportacionRuta());
        Assert::assertNotEmpty($actaActualizada->documentoExportacionRuta());
    }

    #[Then('el sistema rechaza la operación')]
    public function elSistemaRechazaLaOperacion(): void
    {
        Assert::assertNotNull($this->excepcionCapturada, 'Se esperaba una excepción pero no se lanzó ninguna.');
    }

    #[Then('el préstamo queda en estado en tránsito')]
    public function elPrestamoQuedaEnEstadoEnTransitoNacional(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción: '.$this->excepcionCapturada?->getMessage());

        $prestamo = $this->prestamoRepo->buscarPorActaId($this->actaExistente->id());

        Assert::assertNotNull($prestamo);
        Assert::assertEquals(EstadoPrestamo::EnTransito->value, $prestamo->estado()->value);
    }
}
