<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja;

final readonly class CrearCajaInput
{
    public function __construct(
        public string $codigo,
        public string $codigoRfid,
        public ?string $nombre = null,
        public ?string $familiaTaxonomicaId = null,
        public ?int $capacidadMaxima = null,
    ) {}
}
