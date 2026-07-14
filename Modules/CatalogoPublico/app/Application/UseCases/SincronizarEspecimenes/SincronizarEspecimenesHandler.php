<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes;

use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;

final class SincronizarEspecimenesHandler
{
    public function __construct(
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
        private readonly ProveedorEspecimenesPort $proveedorEspecimenes,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function handle(SincronizarEspecimenesInput $input): SincronizarEspecimenesOutput
    {
        if ($input->especimenes === []) {
            return SincronizarEspecimenesOutput::fromPrimitives([]);
        }

        $occurrenceIDs = array_values(array_map(
            fn (array $d): string => $d['occurrenceID'],
            $input->especimenes,
        ));

        // Batch: una sola query para traer todos los datos del proveedor…
        $datosPorOccurrenceId = [];
        foreach ($this->proveedorEspecimenes->buscarPorOccurrenceIds($occurrenceIDs) as $datos) {
            $datosPorOccurrenceId[$datos->occurrenceId] = $datos;
        }

        // …y otra para traer todos los divulgables existentes.
        $divulgablesPorOccurrenceId = [];
        foreach ($this->repoDivulgable->buscarPorOccurrenceIDs($occurrenceIDs) as $divulgable) {
            $occId = $this->occurrenceIdParaEspecimenId($divulgable->especimenId(), $datosPorOccurrenceId);
            if ($occId !== null) {
                $divulgablesPorOccurrenceId[$occId] = $divulgable;
            }
        }

        $divulgablesAPersistir = [];
        $actualizados = [];

        foreach ($input->especimenes as $datoEspecimen) {
            $occurrenceID = $datoEspecimen['occurrenceID'];
            $datos = $datosPorOccurrenceId[$occurrenceID] ?? null;

            if ($datos === null) {
                continue;
            }

            $configuracion = $datoEspecimen['configuracion'] === null
                ? ConfiguracionVisibilidad::todosHabilitados()
                : ConfiguracionVisibilidad::desde($datoEspecimen['configuracion']);

            $divulgable = $divulgablesPorOccurrenceId[$occurrenceID] ?? null;

            if ($divulgable === null) {
                $divulgable = EspecimenDivulgable::sincronizar(
                    id: $this->repoDivulgable->nextIdentity(),
                    especimenId: $datos->especimenId,
                    configuracion: $configuracion,
                );
            } else {
                $divulgable->actualizarConfiguracion($configuracion);
            }

            $divulgablesAPersistir[] = $divulgable;
            $actualizados[] = $occurrenceID;
        }

        // Una sola transacción para todo el lote: elimina N SELECTs de updateOrCreate
        // sin transacción y reduce N commits a uno.
        $this->transactionManager->executeTransactional(
            function () use ($divulgablesAPersistir): void {
                foreach ($divulgablesAPersistir as $divulgable) {
                    $this->repoDivulgable->guardar($divulgable);

                    foreach ($divulgable->pullEvents() as $event) {
                        $this->eventPublisher->publish($event);
                    }
                }
            }
        );

        return SincronizarEspecimenesOutput::fromPrimitives($actualizados);
    }

    /**
     * @param  array<string, DatosEspecimenProveedor>  $datosPorOccurrenceId
     */
    private function occurrenceIdParaEspecimenId(int|string $especimenId, array $datosPorOccurrenceId): ?string
    {
        foreach ($datosPorOccurrenceId as $occurrenceId => $datos) {
            if ((string) $datos->especimenId === (string) $especimenId) {
                return $occurrenceId;
            }
        }

        return null;
    }
}
