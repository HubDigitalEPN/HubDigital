<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoCerrado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacionDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryVerificacionEspecimenesRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: cierre_prestamo_devolucion.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class CierrePrestamoDevolucionContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemoryPrestamoRepository $prestamoRepo;

    private InMemoryVerificacionEspecimenesRepository $verificacionRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarDevolucionPrestamoHandler $registrarDevolucionHandler;

    private CerrarPrestamoHandler $cerrarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Prestamo $prestamoExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-001';

    private string $curadorId = 'cur-001';

    // ── Constructor — registra dependencias in-memory antes de resolver Handlers

    public function __construct()
    {
        self::bootApp();

        $this->prestamoRepo = new InMemoryPrestamoRepository;
        $this->verificacionRepo = new InMemoryVerificacionEspecimenesRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        self::$app->instance(PrestamoRepositoryInterface::class, $this->prestamoRepo);
        self::$app->instance(VerificacionEspecimenesRepositoryInterface::class, $this->verificacionRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);

        $this->registrarDevolucionHandler = $this->make(RegistrarDevolucionPrestamoHandler::class);
        $this->cerrarHandler = $this->make(CerrarPrestamoHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarPrestamoActivo(): Prestamo
    {
        $ahora = new DateTimeImmutable;

        $prestamo = Prestamo::reconstituir(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            estado: EstadoPrestamo::Activo,
            iniciadoEn: $ahora->modify('-1 month'),
            fechaFin: $ahora->modify('+2 months'),
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    private function sembrarPrestamoEnRevision(): Prestamo
    {
        $ahora = new DateTimeImmutable;

        $prestamo = Prestamo::reconstituir(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            estado: EstadoPrestamo::EnRevision,
            iniciadoEn: $ahora->modify('-2 months'),
            fechaFin: $ahora->modify('+1 month'),
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    // =========================================================================
    // ESCENARIO: Declarar la devolución de un préstamo
    // =========================================================================

    #[Given('que existe un préstamo activo asociado al investigador')]
    public function queExisteUnPrestamoActivoAsociadoAlInvestigador(): void
    {
        $prestamo = $this->sembrarPrestamoActivo();

        $persistido = $this->prestamoRepo->buscarPorId($prestamo->id());

        Assert::assertNotNull($persistido, 'El préstamo no fue encontrado tras sembrarlo');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::Activo),
            "Precondición fallida: se esperaba estado 'activo', se obtuvo: {$persistido->estado()->value}"
        );
        Assert::assertSame(
            $this->investigadorId,
            $persistido->investigadorId(),
            'El préstamo sembrado debe pertenecer al investigador del escenario'
        );
    }

    #[When('el investigador registra el envío de devolución del préstamo')]
    public function elInvestigadorRegistraElEnvioDeDevolucionDelPrestamo(): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No existe un préstamo en el escenario');
        Assert::assertTrue(
            $this->prestamoExistente->estado()->equals(EstadoPrestamo::Activo),
            'El préstamo debe estar activo antes de registrar la devolución'
        );

        try {
            $this->ultimaRespuesta = $this->registrarDevolucionHandler->handle(
                new RegistrarDevolucionPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    investigadorId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo pasa a estado en revisión')]
    public function elPrestamoQuedaEnEstadoEnRevision(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->estadoPrestamo->equals(EstadoPrestamo::EnRevision),
            "Se esperaba estado 'en_revision' en el output, se obtuvo: {$this->ultimaRespuesta->estadoPrestamo->value}"
        );

        $persistido = $this->prestamoRepo->buscarPorId($this->prestamoExistente->id());
        Assert::assertNotNull($persistido, 'El préstamo no fue encontrado en el repositorio');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::EnRevision),
            "Se esperaba estado persistido 'en_revision', se obtuvo: {$persistido->estado()->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Recibir notificación del resultado de la verificación
    // =========================================================================

    #[Given('que existe un préstamo del investigador en estado en revisión')]
    public function queExisteUnPrestamoDelInvestigadorEnEstadoEnRevision(): void
    {
        $prestamo = $this->sembrarPrestamoEnRevision();

        $persistido = $this->prestamoRepo->buscarPorId($prestamo->id());

        Assert::assertNotNull($persistido, 'El préstamo no fue encontrado tras sembrarlo');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::EnRevision),
            "Precondición fallida: se esperaba estado 'en_revision', se obtuvo: {$persistido->estado()->value}"
        );
        Assert::assertSame(
            $this->investigadorId,
            $persistido->investigadorId(),
            'El préstamo sembrado debe pertenecer al investigador del escenario'
        );
    }

    #[When('la devolución es verificada con resultado :resultado')]
    public function laDevolucionEsVerificadaConResultado(string $resultado): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No existe un préstamo en el escenario');
        Assert::assertTrue(
            $this->prestamoExistente->estado()->equals(EstadoPrestamo::EnRevision),
            'El préstamo debe estar en revisión para ser verificado por el curador'
        );
        Assert::assertContains(
            $resultado,
            ['sin novedades', 'con observación'],
            "El resultado '{$resultado}' no es un valor válido de verificación"
        );

        try {
            $this->ultimaRespuesta = $this->cerrarHandler->handle(
                new CerrarPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                    curadorId: $this->curadorId,
                    resultado: $resultado,
                    observacionGeneral: $resultado === 'con observación'
                        ? 'Novedad detectada en la verificación de la devolución'
                        : null,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el investigador recibe una notificación por correo con el resultado :resultado')]
    public function elInvestigadorRecibeUnaNotificacionPorCorreoConElResultado(string $resultado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La verificación lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $evento = null;

        foreach ($this->fakePublisher->publishedEvents() as $e) {
            if ($e instanceof PrestamoCerrado) {
                $evento = $e;
                break;
            }
        }

        Assert::assertNotNull(
            $evento,
            'Se esperaba que el evento PrestamoCerrado fuera publicado para notificar al investigador'
        );
        $resultadoVO = match ($resultado) {
            'sin novedades'   => ResultadoVerificacionDevolucion::SinNovedades,
            'con observación' => ResultadoVerificacionDevolucion::ConObservacion,
            default           => throw new \InvalidArgumentException("Resultado no reconocido en el escenario: '{$resultado}'"),
        };
        Assert::assertTrue(
            $evento->resultado->equals($resultadoVO),
            "El evento debe contener el resultado '{$resultado}' de la verificación"
        );
        Assert::assertSame(
            $this->investigadorId,
            $evento->investigadorId,
            'El evento debe referenciar al investigador correcto para la notificación'
        );

        $verificacion = $this->verificacionRepo->buscarPorPrestamoYTipo(
            $this->prestamoExistente->id(),
            TipoVerificacion::Devolucion
        );
        Assert::assertNotNull(
            $verificacion,
            'Se esperaba que la VerificacionEspecimenes de tipo Devolucion fuera persistida en el repositorio'
        );
    }
}
