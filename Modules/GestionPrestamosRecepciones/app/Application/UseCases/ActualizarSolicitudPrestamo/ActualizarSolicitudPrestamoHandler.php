<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class ActualizarSolicitudPrestamoHandler
{
    public function __construct(
        private readonly SolicitudPrestamoRepositoryInterface $repo,
        private readonly EventPublisherPort                   $publisher,
        private readonly TransactionManagerPort               $transactionManager,
    )
    {
    }

    public function handle(ActualizarSolicitudPrestamoInput $input): ActualizarSolicitudPrestamoOutput
    {
        $id = SolicitudPrestamoId::fromString($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudPrestamoNoEncontradaException::conId($id);
        }

        $items = array_map(
            fn(array $item) => ItemPrestamo::crear(
                id: isset($item['id'])
                    ? ItemPrestamoId::fromString($item['id'])
                    : ItemPrestamoId::generate(),
                especimenCodigoExterno: $item['especimen_codigo_externo'],
                cantidadSolicitada: $item['cantidad_solicitada'],
            ),
            $input->items
        );

        $solicitud->actualizar(
            tituloEstudio: $input->tituloEstudio,
            institucionAdscripcion: $input->institucionAdscripcion,
            lineaInvestigacion: $input->lineaInvestigacion,
            propositoPrestamo: $input->propositoPrestamo,
            duracionPropuestaMeses: $input->duracionPropuestaMeses,
            items: $items,
            justificacionExtendida: $input->justificacionExtendida,
        );

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return ActualizarSolicitudPrestamoOutput::fromPrimitives($solicitud);
    }
}
