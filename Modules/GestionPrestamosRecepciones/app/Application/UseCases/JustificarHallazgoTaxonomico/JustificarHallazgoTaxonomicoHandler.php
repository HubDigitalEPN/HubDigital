<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Justifica un hallazgo taxonómico en una matriz de especies.
 *
 * {@see JustificarHallazgoTaxonomicoInput}
 * {@see JustificarHallazgoTaxonomicoOutput}
 */
final class JustificarHallazgoTaxonomicoHandler
{
    public function __construct(
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * @param JustificarHallazgoTaxonomicoInput $input
     * @return JustificarHallazgoTaxonomicoOutput
     * @throws \DomainException
     */
    public function __invoke(JustificarHallazgoTaxonomicoInput $input): JustificarHallazgoTaxonomicoOutput
    {
        $matrizId = MatrizEspeciesId::from($input->matrizId);
        $matriz = $this->matrizRepo->buscarPorId($matrizId);

        if ($matriz === null) {
            throw new \DomainException(
                sprintf('No se encontró la matriz de especies con ID "%s"', $input->matrizId)
            );
        }

        $matriz->justificarRegistro($input->registroId, $input->motivoJustificacion);

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new JustificarHallazgoTaxonomicoOutput(
            estadoRegistro: $matriz->estadoRegistro($input->registroId),
            estadoMatriz: $matriz->estado(),
            registroId: $input->registroId,
        );
    }
}
