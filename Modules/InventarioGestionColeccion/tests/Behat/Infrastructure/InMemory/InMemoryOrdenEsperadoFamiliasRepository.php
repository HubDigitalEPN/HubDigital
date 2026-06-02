<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;

final class InMemoryOrdenEsperadoFamiliasRepository implements OrdenEsperadoFamiliasRepository
{
    private OrdenEsperadoFamilias $orden;

    public function __construct()
    {
        $this->orden = OrdenEsperadoFamilias::vacio();
    }

    public function obtener(): OrdenEsperadoFamilias
    {
        return $this->orden;
    }

    public function guardar(OrdenEsperadoFamilias $orden): void
    {
        $this->orden = $orden;
    }
}
