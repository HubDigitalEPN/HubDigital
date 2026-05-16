<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;

interface EntidadDepositanteRepositoryInterface
{
    public function nextIdentity(): EntidadDepositanteId;

    public function guardar(EntidadDepositante $entidad): void;

    public function buscarPorId(EntidadDepositanteId $id): ?EntidadDepositante;

    public function buscarPorNombre(string $nombre): ?EntidadDepositante;

    /** @return EntidadDepositante[] */
    public function buscarTodas(): array;
}
