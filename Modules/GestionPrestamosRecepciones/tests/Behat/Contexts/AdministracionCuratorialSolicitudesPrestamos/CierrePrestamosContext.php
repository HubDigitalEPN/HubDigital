<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CondicionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryVerificacionEspecimenesRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: cierre_prestamos.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 */
final class CierrePrestamosContext extends BaseContext
{
    // ── Repositorios in-memory ────────────────────────────────────────────────

    private InMemoryPrestamoRepository $prestamoRepo;

    private InMemoryVerificacionEspecimenesRepository $verificacionRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private CerrarPrestamoHandler $cerrarHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?Prestamo $prestamoExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $curadorId = 'cur-001';

    private string $investigadorId = 'inv-001';

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

        $this->cerrarHandler = $this->make(CerrarPrestamoHandler::class);
    }

    // ── Helper de fixture ─────────────────────────────────────────────────────

    private function sembrarPrestamoEnRevision(): Prestamo
    {
        $ahora = new DateTimeImmutable;

        $prestamo = Prestamo::reconstituir(
            id: $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            investigadorId: $this->investigadorId,
            estado: EstadoPrestamo::EnRevision,
            iniciadoEn: $ahora->modify('-1 month'),
            fechaFin: $ahora->modify('+2 months'),
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Registrar el resultado de la devolución de un préstamo
    // =========================================================================

    #[Given('que existe un préstamo en estado pendiente de verificación')]
    public function queExisteUnPrestamoEnEstadoPendienteDeVerificacion(): void
    {
        $prestamo = $this->sembrarPrestamoEnRevision();

        $persistido = $this->prestamoRepo->buscarPorId($prestamo->id());

        Assert::assertNotNull($persistido, 'El préstamo no fue encontrado tras sembrarlo');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::EnRevision),
            "Precondición fallida: se esperaba estado 'en_revision' (pendiente de verificación), se obtuvo: {$persistido->estado()->value}"
        );
        Assert::assertSame(
            $this->investigadorId,
            $persistido->investigadorId(),
            'El préstamo sembrado debe pertenecer al investigador del escenario'
        );
    }

    #[When('el curador registra la verificación de devolución con resultado :resultado')]
    public function elCuradorRegistraLaVerificacionDeDevolucionConResultado(string $resultado): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No existe un préstamo en el escenario');
        Assert::assertTrue(
            $this->prestamoExistente->estado()->equals(EstadoPrestamo::EnRevision),
            'El préstamo debe estar en revisión antes de registrar la verificación de devolución'
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

    #[Then('el préstamo queda en estado :estado')]
    public function elPrestamoQuedaEnEstado(string $estado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);

        $estadoEsperado = match ($estado) {
            'cerrado'                 => EstadoPrestamo::Cerrado,
            'cerrado con observación' => EstadoPrestamo::CerradoConObservacion,
            default                   => throw new \InvalidArgumentException("Estado no reconocido en el escenario: '{$estado}'"),
        };

        Assert::assertTrue(
            $this->ultimaRespuesta->estadoPrestamo->equals($estadoEsperado),
            "Se esperaba estado '{$estadoEsperado->value}' en el output, se obtuvo: {$this->ultimaRespuesta->estadoPrestamo->value}"
        );

        $persistido = $this->prestamoRepo->buscarPorId($this->prestamoExistente->id());
        Assert::assertNotNull($persistido, 'El préstamo no fue encontrado en el repositorio tras el cierre');
        Assert::assertTrue(
            $persistido->estado()->equals($estadoEsperado),
            "Se esperaba estado persistido '{$estadoEsperado->value}', se obtuvo: {$persistido->estado()->value}"
        );
    }

    #[Then('el espécimen queda en condición :condicion_especimen')]
    public function elEspecimenQuedaEnCondicion(string $condicion_especimen): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);

        $condicionEsperada = match ($condicion_especimen) {
            'apto'    => CondicionEspecimen::Apto,
            'no apto' => CondicionEspecimen::NoApto,
            default   => throw new \InvalidArgumentException("Condición no reconocida en el escenario: '{$condicion_especimen}'"),
        };

        Assert::assertTrue(
            $this->ultimaRespuesta->condicionEspecimen->equals($condicionEsperada),
            "Se esperaba condición '{$condicionEsperada->value}', se obtuvo: {$this->ultimaRespuesta->condicionEspecimen->value}"
        );
    }
}
