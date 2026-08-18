<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverEntidadDepositante;

final readonly class ResolverEntidadDepositanteOutput
{
    public function __construct(
        public string $entidadId,
        public string $nombre,
        public string $tipo,
        public bool $creada,
    ) {}
}
