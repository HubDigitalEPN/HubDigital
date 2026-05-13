<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;

final class FakeGeneradorActaPdfAdapter implements GeneradorActaPdfPort
{
    private ?string $ultimaRuta = null;

    /** @param Especimen[] $especimenes */
    public function generar(EntidadDepositante $entidad, array $especimenes): string
    {
        $this->ultimaRuta = "/tmp/acta_entrega_{$entidad->id()}.pdf";

        return $this->ultimaRuta;
    }

    public function getUltimaRuta(): ?string
    {
        return $this->ultimaRuta;
    }
}
