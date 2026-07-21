<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\CertificadoCuradorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\FirmadorPdfPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaCriptograficamentePorCurador;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeCertificadoCuradorAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeFirmadorPdfAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryActaPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: gestion_acta_prestamo.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 */
final class GestionActaPrestamoContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemorySolicitudPrestamoRepository $solicitudRepo;

    private InMemoryActaPrestamoRepository $actaRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    private FakeFirmadorPdfAdapter $fakeFirmador;

    private FakeCertificadoCuradorAdapter $fakeCertificados;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private EnviarActaPrestamoHandler $enviarActaHandler;

    private ValidarActaFirmadaHandler $validarActaHandler;

    private DevolverActaParaRefirmarHandler $devolverActaHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?ActaPrestamo $actaExistente = null;

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $curadorId = 'cur-001';

    private string $investigadorId = 'inv-001';

    private array $datosSolicitud = [
        'titulo_estudio' => 'Revisión taxonómica del género Morpho en Ecuador',
        'institucion_adscripcion' => 'Universidad Central del Ecuador',
        'linea_investigacion' => 'Entomología sistemática',
        'proposito_prestamo' => 'Estudio comparativo de morfología alar',
        'duracion_propuesta_meses' => 3,
        'items' => [
            ['especimen_codigo_externo' => 'ESP-001', 'cantidad_solicitada' => 2],
            ['especimen_codigo_externo' => 'ESP-002', 'cantidad_solicitada' => 1],
        ],
    ];

    // ── Constructor — registra dependencias in-memory antes de resolver Handlers

    public function __construct()
    {
        self::bootApp();

        $this->solicitudRepo = new InMemorySolicitudPrestamoRepository;
        $this->actaRepo = new InMemoryActaPrestamoRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;
        $this->fakeFirmador = new FakeFirmadorPdfAdapter;
        $this->fakeCertificados = new FakeCertificadoCuradorAdapter;

        self::$app->instance(SolicitudPrestamoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(ActaPrestamoRepositoryInterface::class, $this->actaRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);
        self::$app->instance(FirmadorPdfPort::class, $this->fakeFirmador);
        self::$app->instance(CertificadoCuradorPort::class, $this->fakeCertificados);

        $this->enviarActaHandler = $this->make(EnviarActaPrestamoHandler::class);
        $this->validarActaHandler = $this->make(ValidarActaFirmadaHandler::class);
        $this->devolverActaHandler = $this->make(DevolverActaParaRefirmarHandler::class);
    }

    // ── Helper de fixture ─────────────────────────────────────────────────────

    private function sembrarSolicitudAprobada(): SolicitudPrestamo
    {
        $items = array_map(
            fn (array $item) => ItemPrestamo::crear(
                id: ItemPrestamoId::generate(),
                especimenId: (string) \Illuminate\Support\Str::uuid(),
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada: $item['cantidad_solicitada'],
            ),
            $this->datosSolicitud['items'],
        );

        $solicitud = SolicitudPrestamo::crear(
            id: $this->solicitudRepo->nextIdentity(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            alcancePrestamo: AlcancePrestamo::Nacional,
            tituloEstudio: $this->datosSolicitud['titulo_estudio'],
            institucionAdscripcion: $this->datosSolicitud['institucion_adscripcion'],
            lineaInvestigacion: $this->datosSolicitud['linea_investigacion'],
            propositoPrestamo: $this->datosSolicitud['proposito_prestamo'],
            duracionPropuestaMeses: $this->datosSolicitud['duracion_propuesta_meses'],
            items: $items,
        );

        $solicitud->enviar();
        $solicitud->aprobar(curadorId: $this->curadorId);
        $solicitud->pullEvents();
        $this->solicitudRepo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    private function sembrarActaEnEstado(EstadoActa $estado): ActaPrestamo
    {
        $solicitud = $this->sembrarSolicitudAprobada();

        $ahora = new DateTimeImmutable;
        $meses = $solicitud->duracionPropuestaMeses() ?? 3;
        $fechaFin = $ahora->modify("+{$meses} months");
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

        if ($estado === EstadoActa::PendienteFirma || $estado === EstadoActa::PendienteValidacion) {
            $acta->marcarEnviada($solicitud->investigadorId());
        }

        if ($estado === EstadoActa::PendienteValidacion) {
            $acta->subirFirma(
                pdfFirmadoRuta: 'actas/firmadas/'.(string) $solicitud->id().'-firmada.pdf',
                documentoIdentidadRuta: 'documentos-identidad/'.(string) $solicitud->id().'-id.pdf',
            );
        }

        $acta->pullEvents();
        $this->actaRepo->guardar($acta);
        $this->actaExistente = $acta;

        return $acta;
    }

    // =========================================================================
    // ESCENARIO: Enviar el acta de préstamo al investigador para su firma
    // =========================================================================

    #[Given('que el curador tiene un acta en estado pendiente de envío')]
    public function queExisteUnActaEnEstadoPendienteDeEnvio(): void
    {
        $acta = $this->sembrarActaEnEstado(EstadoActa::PendienteEnvio);
        $persistida = $this->actaRepo->buscarPorId($acta->id());

        Assert::assertNotNull($persistida, 'El acta no fue encontrada tras sembrarla');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoActa::PendienteEnvio),
            "Precondición fallida: se esperaba estado 'pendiente_envio', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotEmpty($persistida->pdfRuta(), 'El acta debe tener una ruta de PDF generada');
    }

    #[When('el curador envía el acta al investigador')]
    public function elCuradorEnviaElActaAlInvestigador(): void
    {
        Assert::assertNotNull($this->actaExistente, 'No existe un acta en el escenario');
        Assert::assertTrue(
            $this->actaExistente->estado()->equals(EstadoActa::PendienteEnvio),
            'El acta debe estar en estado pendiente_envio antes de enviarla'
        );

        try {
            $this->ultimaRespuesta = $this->enviarActaHandler->handle(
                new EnviarActaPrestamoInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el acta queda en estado pendiente de firma')]
    public function elActaQuedaEnEstadoPendienteDeFirma(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoActa::PendienteFirma->value,
            $this->ultimaRespuesta->estadoActa,
            "Se esperaba estado 'pendiente_firma', se obtuvo: {$this->ultimaRespuesta->estadoActa}"
        );

        $persistida = $this->actaRepo->buscarPorId($this->actaExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoActa::PendienteFirma),
            'El estado persistido en el repositorio no es PendienteFirma'
        );
    }

    #[Then('el curador confirma que la notificación con el acta fue enviada al investigador')]
    public function elCuradorConfirmaQuelaNotificacionConElActaFueEnviadaAlInvestigador(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionEnviada,
            'Se esperaba que la notificación al investigador fuera enviada'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->pdfRuta,
            'La notificación debe incluir la ruta del PDF del acta'
        );
    }

    // =========================================================================
    // ESCENARIO: Validar el acta firmada por el investigador
    // ESCENARIO: Devolver el acta por firma inválida
    // (Dado compartido — ambos parten de PendienteValidacion)
    // =========================================================================

    #[Given('que el curador tiene un acta en estado pendiente de validación')]
    public function queExisteUnActaEnEstadoPendienteDeValidacion(): void
    {
        $acta = $this->sembrarActaEnEstado(EstadoActa::PendienteValidacion);
        $persistida = $this->actaRepo->buscarPorId($acta->id());

        Assert::assertNotNull($persistida, 'El acta no fue encontrada tras sembrarla');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoActa::PendienteValidacion),
            "Precondición fallida: se esperaba estado 'pendiente_validacion', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotEmpty($persistida->pdfFirmadoRuta(), 'El acta debe tener un PDF firmado subido');
        Assert::assertNotNull($persistida->firmadaSubidaEn(), 'El acta debe registrar cuándo fue subida la firma');
    }

    // =========================================================================
    // ESCENARIO: Validar el acta firmada por el investigador
    // =========================================================================

    #[When('el curador valida el acta firmada')]
    public function elCuradorValidaElActaFirmada(): void
    {
        Assert::assertNotNull($this->actaExistente, 'No existe un acta en el escenario');
        Assert::assertTrue(
            $this->actaExistente->estado()->equals(EstadoActa::PendienteValidacion),
            'El acta debe estar en estado pendiente_validacion antes de validarla'
        );

        try {
            $this->ultimaRespuesta = $this->validarActaHandler->handle(
                new ValidarActaFirmadaInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el acta queda en estado validada')]
    public function elActaQuedaEnEstadoValidada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(
            EstadoActa::Validada->value,
            $this->ultimaRespuesta->estadoActa,
            "Se esperaba estado 'validada', se obtuvo: {$this->ultimaRespuesta->estadoActa}"
        );

        $persistida = $this->actaRepo->buscarPorId($this->actaExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoActa::Validada),
            'El estado persistido en el repositorio no es Validada'
        );
        Assert::assertNotNull($persistida->validadaEn(), "Se esperaba que 'validada_en' quedara registrado");
        Assert::assertSame($this->curadorId, $persistida->validadaPor(), 'Se esperaba que el curador validador quedara registrado');
    }

    #[Then('se crea un préstamo en estado activo')]
    public function seCreaUnPrestamoEnEstadoActivo(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->prestamoCreado,
            'Se esperaba que el préstamo activo fuera creado al validar el acta'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->prestamoId,
            'Se esperaba que el préstamo tuviera un ID generado'
        );
    }

    #[Then('el acta queda firmada digitalmente por el curador')]
    public function elActaQuedaFirmadaDigitalmentePorElCurador(): void
    {
        $persistida = $this->actaRepo->buscarPorId($this->actaExistente->id());
        Assert::assertNotNull($persistida);
        Assert::assertNotEmpty(
            $persistida->pdfFirmadoCuradorRuta(),
            'Se esperaba que el acta tuviera la ruta del PDF firmado por el curador'
        );
        Assert::assertNotNull(
            $persistida->firmaCuradorMetadata(),
            'Se esperaba que el acta registrara la evidencia de la firma del curador'
        );

        $evento = null;
        foreach ($this->fakePublisher->publishedEvents() as $e) {
            if ($e instanceof ActaFirmadaCriptograficamentePorCurador) {
                $evento = $e;
                break;
            }
        }

        Assert::assertNotNull(
            $evento,
            'Se esperaba que el evento ActaFirmadaCriptograficamentePorCurador fuera publicado'
        );
        Assert::assertNotEmpty($evento->commonName, 'El evento debe incluir el nombre del firmante');
    }

    // =========================================================================
    // ESCENARIO: Devolver el acta por firma inválida
    // =========================================================================

    #[When('el curador devuelve el acta por motivos de firma con un comentario')]
    public function elCuradorDevuelveElActaPorMotivosDeFirmaConUnComentario(): void
    {
        Assert::assertNotNull($this->actaExistente, 'No existe un acta en el escenario');
        Assert::assertTrue(
            $this->actaExistente->estado()->equals(EstadoActa::PendienteValidacion),
            'El acta debe estar en estado pendiente_validacion antes de devolverla'
        );

        $motivo = 'La firma no es válida, debe usar firma electrónica certificada por el BCE.';
        Assert::assertNotEmpty($motivo, 'El motivo de devolución no puede estar vacío');

        try {
            $this->ultimaRespuesta = $this->devolverActaHandler->handle(
                new DevolverActaParaRefirmarInput(
                    actaId: (string) $this->actaExistente->id(),
                    curadorId: $this->curadorId,
                    motivo: $motivo,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el curador confirma que el investigador fue notificado con el motivo de la devolución')]
    public function elCuradorConfirmaQueElInvestigadorFueNotificadoConElMotivoDeLaDevolucion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La devolución lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $evento = null;

        foreach ($this->fakePublisher->publishedEvents() as $e) {
            if ($e instanceof ActaDevueltaPorFirmaInvalida) {
                $evento = $e;
                break;
            }
        }

        Assert::assertNotNull(
            $evento,
            'Se esperaba que el evento ActaDevueltaPorFirmaInvalida fuera publicado'
        );
        Assert::assertNotEmpty($evento->motivo, 'El evento debe contener el motivo de la devolución');
    }
}
