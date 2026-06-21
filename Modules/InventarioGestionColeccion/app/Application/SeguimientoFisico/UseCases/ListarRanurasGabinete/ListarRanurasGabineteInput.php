<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete;

/**
 * DTO de entrada con el gabinete cuyas ranuras se quieren listar.
 */
final readonly class ListarRanurasGabineteInput
{
    public function __construct(public string $gabineteId) {}
}
