<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegenerarAccesoVisitante;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

final readonly class RegenerarAccesoVisitanteOutput
{
    public function __construct(
        public VisitanteId $id,
        public string $nombre,
        public int $versionAcceso,
    ) {}
}
