<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;

final readonly class RegistrarEntidadDepositanteOutput
{
    public function __construct(
        public EntidadDepositanteId $id,
        public string $nombre,
        public string $tipo,
        public string $contacto,
    ) {}
}
