<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UbicacionCajaId;

final class InMemoryUbicacionCajaRepository implements UbicacionCajaRepository
{
    /** @var array<string, UbicacionCaja> */
    private array $store = [];

    public function nextIdentity(): UbicacionCajaId
    {
        return UbicacionCajaId::generar();
    }

    public function guardar(UbicacionCaja $ubicacion): void
    {
        $this->store[(string) $ubicacion->id()] = $ubicacion;
    }

    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja
    {
        foreach ($this->store as $ubicacion) {
            if ($ubicacion->cajaId()->equals($cajaId) && $ubicacion->estaActiva()) {
                return $ubicacion;
            }
        }

        return null;
    }

    public function buscarUltimaRetiradaPorCaja(CajaId $cajaId): ?UbicacionCaja
    {
        $ultima = null;

        foreach ($this->store as $ubicacion) {
            if (! $ubicacion->cajaId()->equals($cajaId)) {
                continue;
            }

            $retiradaEn = $ubicacion->retiradaEn();
            if ($retiradaEn === null) {
                continue;
            }

            if ($ultima === null || $retiradaEn > $ultima->retiradaEn()) {
                $ultima = $ubicacion;
            }
        }

        return $ultima;
    }
}
