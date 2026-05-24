<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

final class AceptarSugerenciaTaxonomicaHandler
{
    public function __construct(
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(AceptarSugerenciaTaxonomicaInput $input): AceptarSugerenciaTaxonomicaOutput
    {
        $matrizId = MatrizEspeciesId::from($input->matrizId);
        $matriz = $this->matrizRepo->buscarPorId($matrizId);

        if ($matriz === null) {
            throw new \DomainException(
                sprintf('No se encontró la matriz de especies con ID "%s"', $input->matrizId)
            );
        }

        // Capturar la especie original antes de la corrección
        $registros = $matriz->registros();
        $especieOriginal = $registros[$input->registroId]->nombreCientifico();

        $matriz->aceptarSugerencia($input->registroId, $input->especieCorregida);

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new AceptarSugerenciaTaxonomicaOutput(
            estadoRegistro: EstadoRegistroEspecimen::CorregidoPorSugerencia,
            estadoMatriz: $matriz->estado(),
            especieOriginal: $especieOriginal,
            especieCorregida: $input->especieCorregida,
        );
    }
}
