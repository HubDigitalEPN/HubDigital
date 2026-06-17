<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

/**
 * Caso de uso: Acepta una sugerencia taxonómica realizada sobre un registro de la matriz de especies.
 * 
 * {@see AceptarSugerenciaTaxonomicaInput}
 * {@see AceptarSugerenciaTaxonomicaOutput}
 */
final class AceptarSugerenciaTaxonomicaHandler
{
    /**
     * Crea una nueva instancia de AceptarSugerenciaTaxonomicaHandler.
     * @param MatrizEspeciesRepositoryInterface $matrizRepo Repositorio de matrices de especies.
     * @param TransactionManagerPort $transactionManager Puerto para la gestión de transacciones.
     * @param EventPublisherPort $eventPublisher Puerto para la publicación de eventos.
     */
    public function __construct(
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    /**
     * Ejecuta el caso de uso.
     * @param AceptarSugerenciaTaxonomicaInput $input
     * @return AceptarSugerenciaTaxonomicaOutput
     * @throws \DomainException Si no se encuentra la matriz de especies.
     */
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
        $especieOriginal = $matriz->nombreCientificoDeRegistro($input->registroId);

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
