<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarTrazabilidadEspecimen;

/**
 * DTO de un movimiento dentro de la línea de tiempo de un espécimen. Puede referirse al propio
 * espécimen o a un contenedor que lo alberga (unit tray, caja).
 */
final readonly class MovimientoTrazabilidadOutput
{
    public function __construct(
        public string $tipo,
        public ?string $origen,
        public ?string $destino,
        public \DateTimeImmutable $ocurridoEn,
        public string $responsable,
    ) {}
}
