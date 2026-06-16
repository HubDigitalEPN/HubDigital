<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

final readonly class RegistrarVisitanteOutput
{
    public function __construct(
        public VisitanteId $id,
        public string $nombre,
        public ?string $contacto,
        public int $versionAcceso,
    ) {}
}
