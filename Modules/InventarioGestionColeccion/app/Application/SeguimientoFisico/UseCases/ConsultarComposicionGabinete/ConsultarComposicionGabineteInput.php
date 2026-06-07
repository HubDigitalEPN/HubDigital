<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarComposicionGabinete;

final readonly class ConsultarComposicionGabineteInput
{
    public function __construct(
        public string $gabineteId,
    ) {}
}
