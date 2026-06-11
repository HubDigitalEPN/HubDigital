<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

/**
 * DTO de salida con los datos de una alerta de ubicación para el listado.
 */
final readonly class ListarAlertasItemOutput
{
    public function __construct(
        public string $id,
        public string $cajaId,
        public string $tipo,
        public string $estado,
        public array $datosContexto,
        public \DateTimeImmutable $generadaEn,
    ) {}
}
