<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\CodigoQr;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoQrId;

interface CodigoQrRepositoryInterface
{
    public function nextIdentity(): CodigoQrId;

    public function guardar(CodigoQr $codigoQr): void;

    /** Devuelve el QR vigente del espécimen, o null si aún no se ha generado. */
    public function buscarPorEspecimen(string $especimenId): ?CodigoQr;

    /** Resuelve un QR a partir del payload escaneado, o null si no existe. */
    public function buscarPorPayload(string $payload): ?CodigoQr;
}
