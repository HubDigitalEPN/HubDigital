<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitud;

final class RegistrarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(RegistrarSolicitudPrestamoInput $input): RegistrarSolicitudPrestamoOutput
    {
        $id              = $this->repo->nextIdentity();
        $numeroSolicitud = NumeroSolicitud::generate();

        $items = array_map(
            fn (array $item) => ItemPrestamo::crear(
                id:                     ItemPrestamoId::generate(),
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada:     (int) $item['cantidad_solicitada'],
            ),
            $input->items
        );

        $solicitud = SolicitudPrestamo::crear(
            id:                     $id,
            numeroSolicitud:        $numeroSolicitud,
            investigadorId:         $input->investigadorId,
            tituloEstudio:          $input->tituloEstudio,
            institucionAdscripcion: $input->institucionAdscripcion,
            lineaInvestigacion:     $input->lineaInvestigacion,
            propositoPrestamo:      $input->propositoPrestamo,
            duracionPropuestaMeses: $input->duracionPropuestaMeses,
            items:                  $items,
            justificacionExtendida: $input->justificacionExtendida,
        );

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return RegistrarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
