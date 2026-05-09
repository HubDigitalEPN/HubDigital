<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\SincronizacionEsp32;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\SincronizacionEsp32Id;

interface SincronizacionEsp32Repository
{
    public function nextIdentity(): SincronizacionEsp32Id;

    public function guardar(SincronizacionEsp32 $sincronizacion): void;

    public function buscarUltimaPorGabinete(GabineteId $gabineteId): ?SincronizacionEsp32;
}
