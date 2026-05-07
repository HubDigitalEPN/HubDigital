<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\AlertaUbicacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoAlerta;

interface AlertaUbicacionRepository
{
    public function nextIdentity(): AlertaUbicacionId;

    public function guardar(AlertaUbicacion $alerta): void;

    public function buscarActivaPorCaja(CajaId $cajaId): ?AlertaUbicacion;

    public function buscarPorId(AlertaUbicacionId $id): ?AlertaUbicacion;

    /** @return AlertaUbicacion[] */
    public function buscarTodas(?EstadoAlerta $estado = null): array;
}
