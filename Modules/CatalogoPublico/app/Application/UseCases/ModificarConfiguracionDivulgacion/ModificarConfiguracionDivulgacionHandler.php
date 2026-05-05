<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion;

use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use RuntimeException;

final class ModificarConfiguracionDivulgacionHandler
{
    public function __construct(
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function handle(ModificarConfiguracionDivulgacionInput $input): ModificarConfiguracionDivulgacionOutput
    {
        $modificados = [];

        foreach ($input->especimenes as $datoEspecimen) {
            $occurrenceID = $datoEspecimen['occurrenceID'];

            $configuracion = ConfiguracionVisibilidad::desde($datoEspecimen['configuracion']);

            $divulgable = $this->repoDivulgable->buscarPorOccurrenceID($occurrenceID);

            if ($divulgable === null) {
                throw new RuntimeException(
                    "El espécimen '{$occurrenceID}' no existe en la tabla de divulgación"
                );
            }

            $divulgable->actualizarConfiguracion($configuracion);

            $this->transactionManager->executeTransactional(function () use ($divulgable): void {
                $this->repoDivulgable->guardar($divulgable);
                foreach ($divulgable->pullEvents() as $event) {
                    $this->eventPublisher->publish($event);
                }
            });

            $modificados[] = $occurrenceID;
        }

        return ModificarConfiguracionDivulgacionOutput::fromPrimitives($modificados);
    }
}
