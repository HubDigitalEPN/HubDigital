<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;

/**
 * Caso de uso: listar los gabinetes activos de la colección.
 *
 * @see ListarGabineteOutput
 */
final class ListarGabineteHandler
{
    /**
     * @param  GabineteRepository  $gabineteRepo  Recupera los gabinetes activos.
     */
    public function __construct(
        private readonly GabineteRepository $gabineteRepo,
    ) {}

    /**
     * Recupera los gabinetes activos y los proyecta a items de salida (reutilizando el DTO de
     * creación de gabinete como read model).
     */
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
