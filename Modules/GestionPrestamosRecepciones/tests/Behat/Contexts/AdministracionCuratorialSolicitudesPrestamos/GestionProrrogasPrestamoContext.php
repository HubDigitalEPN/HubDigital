<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support\EstadoProrrogaCompartido;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support\ProrrogaTestState;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: gestion_prorrogas_prestamo.feature
 * Capability: AdministracionCuratorialSolicitudesPrestamos
 *
 * Es además el dueño del paso «Dado que existe un préstamo en estado prórroga
 * solicitada», compartido con el escenario de notificación del investigador a través
 * de {@see ProrrogaTestState}.
 */
final class GestionProrrogasPrestamoContext extends BaseContext
{
    use EstadoProrrogaCompartido;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $curadorId = 'cur-001';

    private string $investigadorId = '00000000-0000-4000-8000-000000000001';

    public function __construct()
    {
        self::bootApp();
    }

    #[Given('que existe un préstamo en estado prórroga solicitada')]
    public function queExisteUnPrestamoEnEstadoProrrogaSolicitada(): void
    {
        $ahora = new DateTimeImmutable;
        ProrrogaTestState::$fechaFinOriginal = $ahora->modify('+3 months');

        $prestamo = Prestamo::reconstituir(
            id: ProrrogaTestState::$prestamoRepo->nextIdentity(),
            actaPrestamoId: ActaPrestamoId::generate(),
            codigoPrestamo: CodigoPrestamo::fromParts(2026, random_int(1, 99999)),
            investigadorId: $this->investigadorId,
            estado: EstadoPrestamo::ProrrogaSolicitada,
            iniciadoEn: $ahora,
            fechaFin: ProrrogaTestState::$fechaFinOriginal,
        );
        ProrrogaTestState::$prestamoRepo->guardar($prestamo);
        ProrrogaTestState::$prestamoId = $prestamo->id();

        $solicitud = SolicitudProrroga::solicitar(
            id: ProrrogaTestState::$solicitudRepo->nextIdentity(),
            prestamoId: $prestamo->id(),
            fechaPropuesta: $ahora->modify('+6 months'),
            justificacion: 'Necesito más tiempo para concluir el análisis morfológico.',
            solicitadaEn: $ahora,
        );
        ProrrogaTestState::$solicitudRepo->guardar($solicitud);

        Assert::assertTrue($prestamo->estado()->equals(EstadoPrestamo::ProrrogaSolicitada));
    }

    #[When('el curador registra la aprobación de la prórroga con una nueva fecha de vencimiento')]
    public function elCuradorRegistraLaAprobacionDeLaProrroga(): void
    {
        Assert::assertNotNull(ProrrogaTestState::$prestamoId);

        $nuevaFechaFin = ProrrogaTestState::$fechaFinOriginal->modify('+3 months');

        try {
            $this->ultimaRespuesta = $this->resolverConReposCompartidos(AprobarProrrogaPrestamoHandler::class)->handle(
                new AprobarProrrogaPrestamoInput(
                    prestamoId: (string) ProrrogaTestState::$prestamoId,
                    curadorId: $this->curadorId,
                    nuevaFechaFin: $nuevaFechaFin->format(\DateTimeInterface::ATOM),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo queda en estado activo con la nueva fecha de vencimiento')]
    public function elPrestamoQuedaEnEstadoActivoConLaNuevaFecha(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $persistido = ProrrogaTestState::$prestamoRepo->buscarPorId(ProrrogaTestState::$prestamoId);
        Assert::assertNotNull($persistido);
        Assert::assertTrue($persistido->estado()->equals(EstadoPrestamo::Activo));
        Assert::assertGreaterThan(
            ProrrogaTestState::$fechaFinOriginal->getTimestamp(),
            $persistido->fechaFin()->getTimestamp(),
            'La fecha de vencimiento no fue extendida con la prórroga'
        );
    }

    #[When('el curador registra el rechazo de la prórroga con un comentario')]
    public function elCuradorRegistraElRechazoConComentario(): void
    {
        Assert::assertNotNull(ProrrogaTestState::$prestamoId);

        try {
            $this->ultimaRespuesta = $this->resolverConReposCompartidos(RechazarProrrogaPrestamoHandler::class)->handle(
                new RechazarProrrogaPrestamoInput(
                    prestamoId: (string) ProrrogaTestState::$prestamoId,
                    curadorId: $this->curadorId,
                    comentario: 'El plazo solicitado excede la política de préstamos vigente.',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo queda en estado activo con la fecha de vencimiento original')]
    public function elPrestamoQuedaEnEstadoActivoConLaFechaOriginal(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $persistido = ProrrogaTestState::$prestamoRepo->buscarPorId(ProrrogaTestState::$prestamoId);
        Assert::assertNotNull($persistido);
        Assert::assertTrue($persistido->estado()->equals(EstadoPrestamo::Activo));
        Assert::assertSame(
            ProrrogaTestState::$fechaFinOriginal->format('Y-m-d H:i:s'),
            $persistido->fechaFin()->format('Y-m-d H:i:s'),
            'La fecha de vencimiento original no debió cambiar al rechazar la prórroga'
        );
    }

    #[When('el curador intenta registrar el rechazo de la prórroga sin comentario')]
    public function elCuradorIntentaRegistrarElRechazoSinComentario(): void
    {
        Assert::assertNotNull(ProrrogaTestState::$prestamoId);

        try {
            $this->ultimaRespuesta = $this->resolverConReposCompartidos(RechazarProrrogaPrestamoHandler::class)->handle(
                new RechazarProrrogaPrestamoInput(
                    prestamoId: (string) ProrrogaTestState::$prestamoId,
                    curadorId: $this->curadorId,
                    comentario: '',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el préstamo permanece en estado prórroga solicitada')]
    public function elPrestamoPermaneceEnEstadoProrrogaSolicitada(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción al rechazar sin comentario'
        );

        $persistido = ProrrogaTestState::$prestamoRepo->buscarPorId(ProrrogaTestState::$prestamoId);
        Assert::assertNotNull($persistido);
        Assert::assertTrue(
            $persistido->estado()->equals(EstadoPrestamo::ProrrogaSolicitada),
            'El préstamo no debió cambiar de estado tras un rechazo inválido'
        );
    }
}
