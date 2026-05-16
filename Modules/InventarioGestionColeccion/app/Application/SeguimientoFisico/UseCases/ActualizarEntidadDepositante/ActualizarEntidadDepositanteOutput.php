<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante;

final readonly class ActualizarEntidadDepositanteOutput
{
    public function __construct(
        public string $id,
        public string $nombre,
        public string $tipo,
        public string $contacto,
    ) {}
}
