<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo\SolicitarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo\SolicitarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LaravelEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\ResultadoProrrogaMailable;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes\FakeInvestigadorEmailAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support\EstadoProrrogaCompartido;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support\ProrrogaTestState;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: solicitud_prorroga_prestamo.feature
 * Capability: TramitacionSolicitudesInvestigador
 *
 * El paso «Dado que existe un préstamo en estado prórroga solicitada» (escenario de
 * notificación) lo provee GestionProrrogasPrestamoContext a través del estado
 * compartido {@see ProrrogaTestState}.
 */
final class SolicitudProrrogaPrestamoContext extends BaseContext
{
    use EstadoProrrogaCompartido;

    private string $investigadorId = '00000000-0000-4000-8000-000000000001';

    private string $curadorId = 'cur-001';

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    public function __construct()
    {
        self::bootApp();
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    private function sembrarPrestamoEnEstado(EstadoPrestamo $estado): void
    {
        $ahora = new DateTimeImmutable;
        ProrrogaTestState::$fechaFinOriginal = $ahora->modify('+3 months');

        $prestamo = Prestamo::reconstituir(
            id: ProrrogaTestState::$prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            estado: $estado,
            iniciadoEn: $ahora,
            fechaFin: ProrrogaTestState::$fechaFinOriginal,
        );
        ProrrogaTestState::$prestamoRepo->guardar($prestamo);
        ProrrogaTestState::$prestamoId = $prestamo->id();
    }

    // ── Solicitar / no permitir ────────────────────────────────────────────────

    #[Given('que existe un préstamo activo del investigador para prórroga')]
    public function queExisteUnPrestamoActivoDelInvestigadorParaProrroga(): void
    {
        $this->sembrarPrestamoEnEstado(EstadoPrestamo::Activo);
    }

    #[Given('que existe un préstamo en estado vencido asociado al investigador')]
    public function queExisteUnPrestamoEnEstadoVencidoAsociadoAlInvestigador(): void
    {
        $this->sembrarPrestamoEnEstado(EstadoPrestamo::Vencido);
    }

    #[When('el investigador registra una solicitud de prórroga con una nueva fecha y justificación')]
    public function elInvestigadorRegistraUnaSolicitudConFechaYJustificacion(): void
    {
        $this->ejecutarSolicitud(
            nuevaFechaFin: ProrrogaTestState::$fechaFinOriginal->modify('+3 months')->format(\DateTimeInterface::ATOM),
            justificacion: 'Requiero ampliar el plazo para finalizar la identificación de los ejemplares.',
        );
    }

    #[When('el investigador intenta registrar una solicitud de prórroga sin justificación')]
    public function elInvestigadorIntentaRegistrarUnaSolicitudSinJustificacion(): void
    {
        $this->ejecutarSolicitud(
            nuevaFechaFin: ProrrogaTestState::$fechaFinOriginal->modify('+3 months')->format(\DateTimeInterface::ATOM),
            justificacion: '',
        );
    }

    #[When('el investigador intenta registrar una solicitud de prórroga')]
    public function elInvestigadorIntentaRegistrarUnaSolicitudDeProrroga(): void
    {
        $this->ejecutarSolicitud(
            nuevaFechaFin: (new DateTimeImmutable('+6 months'))->format(\DateTimeInterface::ATOM),
            justificacion: 'Solicito continuar el estudio durante un periodo adicional.',
        );
    }

    private function ejecutarSolicitud(string $nuevaFechaFin, string $justificacion): void
    {
        try {
            $this->ultimaRespuesta = $this->resolverConReposCompartidos(SolicitarProrrogaPrestamoHandler::class)->handle(
                new SolicitarProrrogaPrestamoInput(
                    prestamoId: (string) ProrrogaTestState::$prestamoId,
                    investigadorId: $this->investigadorId,
                    nuevaFechaFin: $nuevaFechaFin,
                    justificacion: $justificacion,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo queda en estado prórroga solicitada')]
    public function elPrestamoQuedaEnEstadoProrrogaSolicitada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $persistido = ProrrogaTestState::$prestamoRepo->buscarPorId(ProrrogaTestState::$prestamoId);
        Assert::assertNotNull($persistido);
        Assert::assertTrue($persistido->estado()->equals(EstadoPrestamo::ProrrogaSolicitada));
    }

    #[Then('el préstamo permanece en estado activo')]
    public function elPrestamoPermaneceEnEstadoActivo(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción al solicitar sin justificación'
        );

        $persistido = ProrrogaTestState::$prestamoRepo->buscarPorId(ProrrogaTestState::$prestamoId);
        Assert::assertNotNull($persistido);
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::Activo),
            'El préstamo no debió cambiar de estado tras una solicitud inválida'
        );
    }

    #[Then('la solicitud no es permitida')]
    public function laSolicitudNoEsPermitida(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que la solicitud de prórroga sobre un préstamo vencido no fuera permitida'
        );
    }

    // ── Notificación del resultado ───────────────────────────────────────────────

    #[When('/^la prórroga es resuelta con resultado (.+)$/u')]
    public function laProrrogaEsResueltaConResultado(string $resultado): void
    {
        Assert::assertNotNull(ProrrogaTestState::$prestamoId);

        Mail::fake();
        $this->prepararResolucionConPublisherReal();

        try {
            if ($resultado === 'aprobada') {
                $this->ultimaRespuesta = self::$app->make(AprobarProrrogaPrestamoHandler::class)->handle(
                    new AprobarProrrogaPrestamoInput(
                        prestamoId: (string) ProrrogaTestState::$prestamoId,
                        curadorId: $this->curadorId,
                        nuevaFechaFin: ProrrogaTestState::$fechaFinOriginal->modify('+3 months')->format(\DateTimeInterface::ATOM),
                    )
                );
            } else {
                $this->ultimaRespuesta = self::$app->make(RechazarProrrogaPrestamoHandler::class)->handle(
                    new RechazarProrrogaPrestamoInput(
                        prestamoId: (string) ProrrogaTestState::$prestamoId,
                        curadorId: $this->curadorId,
                        comentario: 'No es posible extender el préstamo en este periodo.',
                    )
                );
            }
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el investigador recibe por correo la resolución de su prórroga (.+)$/u')]
    public function elInvestigadorRecibeNotificacionConResultado(string $resultado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        Mail::assertSent(
            ResultadoProrrogaMailable::class,
            fn (ResultadoProrrogaMailable $mail): bool => $mail->resultado === $resultado
        );
    }

    /**
     * Registra los repos compartidos junto con el publisher REAL (para que el evento
     * llegue al listener de notificación) y el puerto de correo falso.
     */
    private function prepararResolucionConPublisherReal(): void
    {
        self::$app->instance(PrestamoRepositoryInterface::class, ProrrogaTestState::$prestamoRepo);
        self::$app->instance(SolicitudProrrogaRepositoryInterface::class, ProrrogaTestState::$solicitudRepo);
        self::$app->instance(RecordatorioDevolucionRepositoryInterface::class, ProrrogaTestState::$recordatorioRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, self::$app->make(LaravelEventPublisherAdapter::class));
        self::$app->instance(InvestigadorEmailPort::class, new FakeInvestigadorEmailAdapter);
    }
}
