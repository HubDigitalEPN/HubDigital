<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarHuerfanosDeposito;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Señala el material de depósito cuyo trámite de origen ya no existe.
 *
 * Ocurre cuando se borra una matriz o una solicitud después de que sus especímenes
 * cruzaron a la colección: la fila del inventario sobrevive sin nada que la ate a su
 * procedencia. Hay trece así.
 *
 * No se borran. En una colección científica el rastro de qué estuvo bajo custodia es
 * patrimonio documental, y además el acta de recepción emitida sigue apuntando a esos
 * especímenes. Lo que procede es que el curador los vea en su cola y decida.
 *
 * Idempotente: no vuelve a anotar lo ya anotado.
 */
final class MarcarHuerfanosDepositoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(MarcarHuerfanosDepositoInput $input): MarcarHuerfanosDepositoOutput
    {
        return new MarcarHuerfanosDepositoOutput(
            marcados: $this->especimenRepo->marcarHuerfanosDeDeposito($input->motivo),
        );
    }
}
