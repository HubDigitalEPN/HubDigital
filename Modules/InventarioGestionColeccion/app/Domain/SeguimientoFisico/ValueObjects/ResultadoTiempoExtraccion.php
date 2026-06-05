<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

enum ResultadoTiempoExtraccion
{
    case DentroDelLimite;
    case ProximaAlLimite;
    case Excedida;
}
