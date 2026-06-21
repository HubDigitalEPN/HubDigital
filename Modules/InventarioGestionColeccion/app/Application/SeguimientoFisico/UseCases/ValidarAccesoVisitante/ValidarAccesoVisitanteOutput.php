<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ValidarAccesoVisitante;

final readonly class ValidarAccesoVisitanteOutput
{
    public function __construct(
        public bool $valido,
        public ?string $visitanteId = null,
        public ?string $nombre = null,
    ) {}
}
