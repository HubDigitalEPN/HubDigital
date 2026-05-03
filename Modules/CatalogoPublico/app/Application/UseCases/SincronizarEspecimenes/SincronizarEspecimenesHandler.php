<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes;

use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;

final class SincronizarEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function handle(SincronizarEspecimenesInput $input): SincronizarEspecimenesOutput
    {
        $actualizados = [];

        foreach ($input->especimenes as $datoEspecimen) {
            $occurrenceID = $datoEspecimen['occurrenceID'];

            $configuracion = $datoEspecimen['configuracion'] === null
                ? ConfiguracionVisibilidad::todosHabilitados()
                : ConfiguracionVisibilidad::desde($datoEspecimen['configuracion']);

            $divulgable = $this->repoDivulgable->buscarPorOccurrenceID($occurrenceID);

            if ($divulgable === null) {
                $id = $this->repoDivulgable->nextIdentity();
                $divulgable = EspecimenDivulgable::sincronizar(
                    id: $id,
                    occurrenceID: $occurrenceID,
                    configuracion: $configuracion,
                );
            } else {
                $divulgable->actualizarConfiguracion($configuracion);
            }

            $this->transactionManager->executeTransactional(function () use ($divulgable): void {
                $this->repoDivulgable->guardar($divulgable);
                foreach ($divulgable->pullEvents() as $event) {
                    $this->eventPublisher->publish($event);
                }
            });

            $actualizados[] = $occurrenceID;
        }

        return SincronizarEspecimenesOutput::fromPrimitives($actualizados);
    }
}
