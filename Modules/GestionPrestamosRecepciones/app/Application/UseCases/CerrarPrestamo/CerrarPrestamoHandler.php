<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacionDevolucion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Caso de uso: El curador registra el resultado de la verificación de devolución y cierra el préstamo.
 *
 * Dependiendo del resultado, el préstamo transiciona a Cerrado (sin novedades)
 * o a CerradoConObservacion (con observación). Cuando hay novedades, se persiste una
 * verificación de especímenes de tipo Devolución con las novedades por espécimen y
 * la nota general; el evento de dominio queda solo para el historial.
 */
final class CerrarPrestamoHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly VerificacionEspecimenesRepositoryInterface $verificacionRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @throws PrestamoNoEncontradoException Si el préstamo no existe.
     * @throws InvalidArgumentException Si el resultado no es reconocido.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException Si el préstamo no está en revisión.
     */
    public function handle(CerrarPrestamoInput $input): CerrarPrestamoOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);

        $resultado = match ($input->resultado) {
            'sin novedades'   => ResultadoVerificacionDevolucion::SinNovedades,
            'con observación' => ResultadoVerificacionDevolucion::ConObservacion,
            default           => throw new InvalidArgumentException("Resultado de verificación no reconocido: '{$input->resultado}'"),
        };

        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $condicionEspecimen = $resultado->condicionResultante();

        $observaciones = array_map(
            fn (array $obs): ObservacionEspecimen => new ObservacionEspecimen(
                itemPrestamoId: $obs['itemPrestamoId'],
                descripcion: $obs['descripcion'],
            ),
            $input->observaciones,
        );

        $verificacion = VerificacionEspecimenes::registrar(
            id: $this->verificacionRepo->nextIdentity(),
            prestamoId: $prestamoId,
            tipo: TipoVerificacion::Devolucion,
            resultado: $resultado === ResultadoVerificacionDevolucion::ConObservacion
                ? ResultadoVerificacion::ConNovedades
                : ResultadoVerificacion::SinNovedades,
            observaciones: $observaciones,
            observacionGeneral: $input->observacionGeneral,
        );

        $prestamo->cerrar(
            curadorId: $input->curadorId,
            resultado: $resultado,
            ahora: new DateTimeImmutable,
            observacion: $verificacion->observacionGeneral(),
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo, $verificacion): void {
            $this->prestamoRepo->guardar($prestamo);
            $this->verificacionRepo->guardar($verificacion);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return CerrarPrestamoOutput::fromPrestamo($prestamo, $condicionEspecimen);
    }
}
