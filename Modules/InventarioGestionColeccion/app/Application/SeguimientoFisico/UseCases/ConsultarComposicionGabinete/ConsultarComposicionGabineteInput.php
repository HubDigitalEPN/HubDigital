<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete;

/**
 * DTO de entrada con el gabinete cuya composición taxonómica se quiere consultar.
 */
final readonly class ConsultarComposicionGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
