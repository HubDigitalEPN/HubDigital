<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\LimiteAnualDepositosAlcanzado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

/**
 * Registra una nueva solicitud de depósito.
 *
 * {@see RegistrarSolicitudDepositoInput}
 * {@see RegistrarSolicitudDepositoOutput}
 */
final class RegistrarSolicitudDepositoHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * @param RegistrarSolicitudDepositoInput $input
     * @return RegistrarSolicitudDepositoOutput
     * @throws LimiteAnualDepositosAlcanzado
     */
    public function __invoke(RegistrarSolicitudDepositoInput $input): RegistrarSolicitudDepositoOutput
    {
        $this->repo->eliminarBorradoresDe($input->investigadorId);

        if ($input->tipoTramite === TipoTramite::Deposito->value) {
            $conteo = $this->repo->contarPorInvestigadorYTipoEnAnioActual(
                $input->investigadorId,
                $input->tipoTramite
            );

            if ($conteo >= 3) {
                throw LimiteAnualDepositosAlcanzado::paraInvestigador($input->investigadorId);
            }
        }

        $id = $this->repo->nextIdentity();
        $numero = $this->repo->nextNumero();
        $solicitud = SolicitudDeposito::crear(
            id: $id,
            numero: $numero,
            investigadorId: $input->investigadorId,
            tipoTramite: $input->tipoTramite,
        );

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return RegistrarSolicitudDepositoOutput::fromEntity($solicitud);
    }
}
