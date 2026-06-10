<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEntregaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoEnvio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Registra la verificación de la entrega de un préstamo.
 *
 * {@see RegistrarVerificacionEntregaInput}
 * {@see RegistrarVerificacionEntregaOutput}
 */
final class RegistrarVerificacionEntregaHandler
{
    public function __construct(
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @param RegistrarVerificacionEntregaInput $input
     * @return RegistrarVerificacionEntregaOutput
     * @throws PrestamoNoEncontradoException
     */
    public function handle(RegistrarVerificacionEntregaInput $input): RegistrarVerificacionEntregaOutput
    {
        $prestamoId = PrestamoId::fromString($input->prestamoId);
        $prestamo = $this->prestamoRepo->buscarPorId($prestamoId);

        if ($prestamo === null) {
            throw PrestamoNoEncontradoException::conId($prestamoId);
        }

        $estadoEnvio = EstadoEnvio::from($input->estadoEnvio);

        $observaciones = array_map(
            fn (array $obs): ObservacionEspecimen => new ObservacionEspecimen(
                itemPrestamoId: $obs['itemPrestamoId'],
                descripcion: $obs['descripcion'],
            ),
            $input->observaciones,
        );

        $ahora = new DateTimeImmutable;

        $prestamo->registrarVerificacion($ahora);

        $verificacion = VerificacionEntregaPrestamo::registrar(
            id: $this->verificacionRepo->nextIdentity(),
            prestamoId: $prestamoId,
            estadoEnvio: $estadoEnvio,
            observaciones: $observaciones,
        );

        $this->transactionManager->executeTransactional(function () use ($prestamo, $verificacion): void {
            $this->prestamoRepo->guardar($prestamo);
            $this->verificacionRepo->guardar($verificacion);
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return RegistrarVerificacionEntregaOutput::fromVerificacion($verificacion);
    }
}
