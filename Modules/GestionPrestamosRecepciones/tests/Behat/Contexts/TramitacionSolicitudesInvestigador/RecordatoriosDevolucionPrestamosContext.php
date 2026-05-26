<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo\EvaluarPlazoDevolucionPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo\EvaluarPlazoDevolucionPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryConfiguracionGlobalRecordatoriosRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: recordatorios_devolucion_prestamos.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class RecordatoriosDevolucionPrestamosContext extends BaseContext
{
    // ── Repositorios in-memory (acceso directo para @Given y @Then) ──────────

    private InMemoryPrestamoRepository $prestamoRepo;

    private InMemoryConfiguracionGlobalRecordatoriosRepository $configRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private EvaluarPlazoDevolucionPrestamoHandler $evaluarPlazoHandler;

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

        // 1. Crear instancias in-memory fresh para este escenario
        $this->prestamoRepo  = new InMemoryPrestamoRepository;
        $this->configRepo    = new InMemoryConfiguracionGlobalRecordatoriosRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        // 2. Interceptar el container para que los Handlers reciban estas instancias
        self::$app->instance(PrestamoRepositoryInterface::class, $this->prestamoRepo);
        self::$app->instance(ConfiguracionGlobalRecordatoriosRepositoryInterface::class, $this->configRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);

        // 3. Resolver Handler — ya usa las instancias in-memory
        $this->evaluarPlazoHandler = $this->make(EvaluarPlazoDevolucionPrestamoHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarPrestamoConFechaFin(DateTimeImmutable $fechaFin): Prestamo
    {
        $prestamo = Prestamo::iniciar(
            id:             $this->prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            investigadorId: $this->investigadorId,
            iniciadoEn:     new DateTimeImmutable('2026-01-01'),
            fechaFin:       $fechaFin,
        );

        $this->prestamoRepo->guardar($prestamo);
        $this->prestamoExistente = $prestamo;

        return $prestamo;
    }

    private function sembrarConfiguracion(int ...$diasAntes): void
    {
        $config = ConfiguracionGlobalRecordatorios::definir(
            id:        $this->configRepo->nextIdentity(),
            curadorId: $this->curadorId,
            diasAntes: array_values($diasAntes),
        );

        $this->configRepo->guardar($config);
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Recibir recordatorio de devolución según el plazo
    // =========================================================================

    #[Given('que existe un préstamo activo con condición :condicion')]
    public function queExisteUnPrestamoActivoConCondicion(string $condicion): void
    {
        $fechaFin = match ($condicion) {
            'próximo a la fecha de devolución configurada' => new DateTimeImmutable('+2 days'),
            'fuera del plazo de devolución'                => new DateTimeImmutable('yesterday'),
            default => throw new \InvalidArgumentException("Condición de préstamo no reconocida: '{$condicion}'"),
        };

        // Recordatorio configurado a 3 días antes → 2 días restantes cae dentro del umbral
        $this->sembrarConfiguracion(3);
        $prestamo = $this->sembrarPrestamoConFechaFin($fechaFin);

        $persistido = $this->prestamoRepo->buscarPorId($prestamo->id());
        Assert::assertNotNull($persistido, 'El préstamo no fue persistido correctamente');
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::Activo),
            "Se esperaba estado 'Activo', se obtuvo: {$persistido->estado()->name}"
        );
        Assert::assertSame(
            $this->investigadorId,
            $persistido->investigadorId(),
            'El préstamo no pertenece al investigador esperado'
        );
    }

    #[When('se evalúa el plazo de devolución del préstamo')]
    public function seEvaluaElPlazoDeDevolucionDelPrestamo(): void
    {
        Assert::assertNotNull($this->prestamoExistente, 'No hay préstamo sembrado para evaluar');

        try {
            $this->ultimaRespuesta = $this->evaluarPlazoHandler->handle(
                new EvaluarPlazoDevolucionPrestamoInput(
                    prestamoId: (string) $this->prestamoExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el investigador recibe un recordatorio por correo indicando que su préstamo está :estado')]
    public function elInvestigadorRecibeUnRecordatorioPorCorreo(string $estado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionEnviada,
            'Se esperaba que el recordatorio por correo fuera enviado al investigador'
        );
        Assert::assertSame(
            $estado,
            $this->ultimaRespuesta->estadoRecordatorio,
            "Se esperaba estado de recordatorio '{$estado}', se obtuvo: {$this->ultimaRespuesta->estadoRecordatorio}"
        );
    }
}
