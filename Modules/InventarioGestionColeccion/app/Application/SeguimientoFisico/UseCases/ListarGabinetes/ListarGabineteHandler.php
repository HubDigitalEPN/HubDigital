<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;

final class ListarGabineteHandler
{
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    public function handle(): ListarGabineteOutput
    {
        $gabinetes = $this->gabineteRepo->buscarActivos();

        $items = array_map(
            fn ($g) => new CrearGabineteOutput(
                id: (string) $g->id(),
                codigo: (string) $g->codigo(),
                nombre: $g->nombre(),
                totalRanuras: $g->totalRanuras(),
                activo: $g->activo(),
            ),
            $gabinetes,
        );

        return new ListarGabineteOutput($items);
    }
}
