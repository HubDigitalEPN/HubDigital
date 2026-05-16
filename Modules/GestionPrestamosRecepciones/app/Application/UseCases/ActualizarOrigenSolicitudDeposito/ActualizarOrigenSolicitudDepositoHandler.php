<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class ActualizarOrigenSolicitudDepositoHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(ActualizarOrigenSolicitudDepositoInput $input): ActualizarOrigenSolicitudDepositoOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->declararOrigenRecoleccion($input->origenRecoleccion);
        $solicitud->declararSituacionRegulatoria($input->situacionRegulatoria);

        if (! empty($input->provinciaOrigen)) {
            $solicitud->declararProvincia($input->provinciaOrigen);
        }

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return ActualizarOrigenSolicitudDepositoOutput::fromEntity($solicitud);
    }
}
