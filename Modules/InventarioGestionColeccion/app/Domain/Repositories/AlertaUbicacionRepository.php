<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;

interface AlertaUbicacionRepository
{
    public function nextIdentity(): AlertaUbicacionId;

    public function guardar(AlertaUbicacion $alerta): void;

    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion;
}
