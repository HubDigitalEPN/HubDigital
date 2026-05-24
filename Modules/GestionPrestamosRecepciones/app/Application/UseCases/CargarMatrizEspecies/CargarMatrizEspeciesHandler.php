<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class CargarMatrizEspeciesHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $solicitudRepo,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(CargarMatrizEspeciesInput $input): CargarMatrizEspeciesOutput
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));

        if ($solicitud === null) {
            throw new \DomainException(
                sprintf('No se encontró la solicitud con ID "%s"', $input->solicitudId)
            );
        }

        $matrizId = $this->matrizRepo->nextIdentity();

        $matriz = MatrizEspecies::crear(
            id: $matrizId,
            solicitudId: $input->solicitudId,
            camposDwCPresentes: $input->camposDwCPresentes,
            tipoTramite: $solicitud->tipoTramite(),
        );

        $matriz->validarCamposDwC($input->camposDwCExigidosPorCatalogo);

        foreach ($input->registros as $datosRegistro) {
            $nombreCientifico = $datosRegistro['scientificName'] ?? '';
            $matriz->agregarRegistroEspecimen($nombreCientifico);
        }

        $validacionTipograficaAplicada = $solicitud->tipoTramite() !== 'Donación';

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new CargarMatrizEspeciesOutput(
            matrizId: (string) $matriz->id(),
            estadoMatriz: $matriz->estado(),
            validacionTipograficaAplicada: $validacionTipograficaAplicada,
            totalRegistros: count($input->registros),
        );
    }
}
