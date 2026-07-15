<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResincronizarClasificaciones;

final readonly class ResincronizarClasificacionesOutput
{
    public function __construct(
        public int $traysRecalculados,
        public int $traysSinClasificacion,
        public int $cajasRecalculadas,
        public int $cajasSinClasificacion,
    ) {}
}
