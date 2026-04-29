<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\SincronizacionEsp32;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\SincronizacionEsp32Id;

interface SincronizacionEsp32Repository
{
    public function nextIdentity(): SincronizacionEsp32Id;

    public function guardar(SincronizacionEsp32 $sincronizacion): void;

    public function buscarUltimaPorGabinete(GabineteId $gabineteId): ?SincronizacionEsp32;
}
