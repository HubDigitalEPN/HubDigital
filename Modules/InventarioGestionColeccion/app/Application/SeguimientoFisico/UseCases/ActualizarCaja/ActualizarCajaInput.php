<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarCaja;

final readonly class ActualizarCajaInput
{
    public function __construct(
        public string $cajaId,
        public ?string $nombre,
        public ?string $familiaTaxonomicaId,
        public ?int $capacidadMaxima,
    ) {}
}
