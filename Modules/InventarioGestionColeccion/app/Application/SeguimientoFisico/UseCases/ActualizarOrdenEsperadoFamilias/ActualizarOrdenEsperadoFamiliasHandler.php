<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarOrdenEsperadoFamilias;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;

final class ActualizarOrdenEsperadoFamiliasHandler
{
    public function __construct(
        private readonly OrdenEsperadoFamiliasRepository $ordenFamiliasRepo,
    ) {}

    public function handle(ActualizarOrdenEsperadoFamiliasInput $input): ActualizarOrdenEsperadoFamiliasOutput
    {
        $orden = OrdenEsperadoFamilias::desde($input->familias);

        $this->ordenFamiliasRepo->guardar($orden);

        return new ActualizarOrdenEsperadoFamiliasOutput($orden->familias());
    }
}
