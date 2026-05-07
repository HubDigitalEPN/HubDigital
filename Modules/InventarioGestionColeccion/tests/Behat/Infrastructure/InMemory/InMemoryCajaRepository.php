<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

final class InMemoryCajaRepository implements CajaRepository
{
    /** @var array<string, Caja> */
    private array $store = [];

    public function nextIdentity(): CajaId
    {
        return CajaId::generar();
    }

    public function guardar(Caja $caja): void
    {
        $this->store[(string) $caja->id()] = $caja;
    }

    public function buscarPorId(CajaId $id): ?Caja
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja
    {
        foreach ($this->store as $caja) {
            if ((string) $caja->codigo() === (string) $codigo) {
                return $caja;
            }
        }

        return null;
    }

    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja
    {
        foreach ($this->store as $caja) {
            if ($caja->codigoRfid() !== null && (string) $caja->codigoRfid() === (string) $rfid) {
                return $caja;
            }
        }

        return null;
    }

    /** @return Caja[] */
    public function buscarTodas(): array
    {
        return array_values($this->store);
    }
}
