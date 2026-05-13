<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;

final class ListarEntidadesDepositantesHandler
{
    public function __construct(
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(): ListarEntidadesDepositantesOutput
    {
        $entidades = $this->entidadRepo->buscarTodas();

        $items = array_map(
            fn (EntidadDepositante $e) => new ListarEntidadesDepositantesItemOutput(
                id: (string) $e->id(),
                nombre: $e->nombre(),
                tipo: $e->tipo()->value,
                contacto: $e->contacto(),
            ),
            $entidades,
        );

        return new ListarEntidadesDepositantesOutput($items);
    }
}
