<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarVisitantes;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;

final class ListarVisitantesHandler
{
    public function __construct(
        private readonly VisitanteRepositoryInterface $visitanteRepo,
    ) {}

    public function handle(): ListarVisitantesOutput
    {
        $items = array_map(
            fn (Visitante $v): VisitanteResumen => new VisitanteResumen(
                id: (string) $v->id(),
                nombre: $v->nombre(),
                contacto: $v->contacto(),
                versionAcceso: $v->versionAcceso(),
                puedeReubicar: $v->puedeReubicar(),
                registradoEn: $v->registradoEn(),
            ),
            $this->visitanteRepo->buscarTodos(),
        );

        return new ListarVisitantesOutput(items: $items);
    }
}
