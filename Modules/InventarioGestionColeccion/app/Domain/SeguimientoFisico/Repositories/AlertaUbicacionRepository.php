<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

interface AlertaUbicacionRepository
{
    public function nextIdentity(): AlertaUbicacionId;

    public function guardar(AlertaUbicacion $alerta): void;

    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion;
}
