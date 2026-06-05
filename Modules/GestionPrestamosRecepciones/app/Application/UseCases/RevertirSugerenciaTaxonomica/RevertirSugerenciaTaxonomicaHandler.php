<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RevertirSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

final class RevertirSugerenciaTaxonomicaHandler
{
    public function __construct(
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(RevertirSugerenciaTaxonomicaInput $input): RevertirSugerenciaTaxonomicaOutput
    {
        $matrizId = MatrizEspeciesId::from($input->matrizId);
        $matriz = $this->matrizRepo->buscarPorId($matrizId);

        if ($matriz === null) {
            throw new \DomainException(
                sprintf('No se encontró la matriz de especies con ID "%s"', $input->matrizId)
            );
        }

        $matriz->revertirSugerencia($input->registroId);

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $registros = $matriz->registros();
        $registro = $registros[$input->registroId];

        return new RevertirSugerenciaTaxonomicaOutput(
            registroId: $input->registroId,
            estadoRegistro: $registro->estado(),
            estadoMatriz: $matriz->estado(),
            especieOriginal: $registro->nombreCientifico(),
        );
    }
}
