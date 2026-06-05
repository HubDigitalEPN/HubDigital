<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class ListarRanurasGabineteHandler
{
    public function __construct(
        private readonly RanuraGabineteRepository $ranuraRepo,
    ) {}

    public function handle(ListarRanurasGabineteInput $input): ListarRanurasGabineteOutput
    {
        $gabineteId = GabineteId::desde($input->gabineteId);
        $ranuras = $this->ranuraRepo->buscarPorGabinete($gabineteId);

        $items = array_map(
            fn ($r) => new CrearRanuraGabineteOutput(
                id: (string) $r->id(),
                gabineteId: (string) $r->gabineteId(),
                numeroRanura: $r->numeroRanura(),
                activa: $r->activa(),
                cajaActualId: $r->cajaActualId() ? (string) $r->cajaActualId() : null,
            ),
            $ranuras,
        );

        return new ListarRanurasGabineteOutput($items);
    }
}
