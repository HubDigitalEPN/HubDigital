<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

/**
 * DTO de entrada con el gabinete cuya ocupación de ranuras se quiere consultar.
 */
final readonly class ConsultarOcupacionGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
