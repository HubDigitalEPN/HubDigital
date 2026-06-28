<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\CodigoQr;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CodigoQrRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoQrId;

final class InMemoryCodigoQrRepository implements CodigoQrRepositoryInterface
{
    /** @var array<string, CodigoQr> */
    private array $store = [];

    public function nextIdentity(): CodigoQrId
    {
        return CodigoQrId::generar();
    }

    public function guardar(CodigoQr $codigoQr): void
    {
        $this->store[(string) $codigoQr->id()] = $codigoQr;
    }

    public function buscarPorEspecimen(string $especimenId): ?CodigoQr
    {
        foreach ($this->store as $codigoQr) {
            if ($codigoQr->especimenId() === $especimenId) {
                return $codigoQr;
            }
        }

        return null;
    }

    public function buscarPorPayload(string $payload): ?CodigoQr
    {
        foreach ($this->store as $codigoQr) {
            if ($codigoQr->payload() === $payload) {
                return $codigoQr;
            }
        }

        return null;
    }
}
