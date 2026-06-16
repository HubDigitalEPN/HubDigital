<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarVisitante;

final readonly class RegistrarVisitanteInput
{
    public function __construct(
        public string $nombre,
        public ?string $contacto = null,
    ) {}
}
