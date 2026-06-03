<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante;

final readonly class RegistrarEntidadDepositanteInput
{
    public function __construct(
        public string $nombre,
        public ?string $tipo = null,
        public ?string $contacto = null,
    ) {}
}
