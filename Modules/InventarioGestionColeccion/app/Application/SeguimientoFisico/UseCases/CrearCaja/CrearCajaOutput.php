<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja;

/**
 * DTO de salida con los datos de la caja recién creada, incluido su estado físico inicial.
 */
final readonly class CrearCajaOutput
{
    public function __construct(
        public string $id,
        public string $codigo,
        public string $codigoRfid,
        public bool $esEspecial,
        public ?string $observacion,
        public ?string $nombre,
        public string $estado,
    ) {}
}
