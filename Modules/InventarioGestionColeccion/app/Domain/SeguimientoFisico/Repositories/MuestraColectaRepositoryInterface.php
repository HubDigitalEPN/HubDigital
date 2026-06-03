<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

interface MuestraColectaRepositoryInterface
{
    public function nextIdentity(): MuestraColectaId;

    public function guardar(MuestraColecta $muestra): void;

    public function buscarPorId(MuestraColectaId $id): ?MuestraColecta;

    public function buscarPorCodigoMuestra(string $codigo): ?MuestraColecta;

    /** @return MuestraColecta[] */
    public function buscarPorEstado(EstadoRevision $estado): array;

    /** @return MuestraColecta[] */
    public function buscarTodas(): array;

    public function contarTodas(): int;

    /**
     * Inserta múltiples muestras en una sola transacción (importador masivo).
     *
     * @param  MuestraColecta[]  $muestras
     */
    public function guardarBatch(array $muestras): void;
}
