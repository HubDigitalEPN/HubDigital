<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante;

final readonly class ActualizarEntidadDepositanteInput
{
    public function __construct(
        public string $entidadId,
        public string $nombre,
        public ?string $tipo = null,
        public ?string $contacto = null,
    ) {}
}
