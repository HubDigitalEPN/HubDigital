<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

final class InMemoryMuestraColectaRepository implements MuestraColectaRepositoryInterface
{
    /** @var array<string, MuestraColecta> */
    private array $store = [];

    public function nextIdentity(): MuestraColectaId
    {
        return MuestraColectaId::generar();
    }

    public function guardar(MuestraColecta $muestra): void
    {
        $this->store[(string) $muestra->id()] = $muestra;
    }

    public function buscarPorId(MuestraColectaId $id): ?MuestraColecta
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorCodigoMuestra(string $codigo): ?MuestraColecta
    {
        foreach ($this->store as $m) {
            if ($m->codigoMuestra() === $codigo) {
                return $m;
            }
        }

        return null;
    }

    /** @return MuestraColecta[] */
    public function buscarPorEstado(EstadoRevision $estado): array
    {
        return array_values(array_filter(
            $this->store,
            fn (MuestraColecta $m) => $m->estadoRevision()->equals($estado)
        ));
    }

    /** @return MuestraColecta[] */
    public function buscarTodas(): array
    {
        return array_values($this->store);
    }

    public function contarTodas(): int
    {
        return count($this->store);
    }

    public function guardarBatch(array $muestras): void
    {
        foreach ($muestras as $muestra) {
            $this->guardar($muestra);
        }
    }

    /** @return MuestraColecta[] */
    public function listarParaRevision(int $limit, int $offset): array
    {
        $pendientes = array_values(array_filter(
            $this->store,
            fn (MuestraColecta $m) => $m->estadoRevision()->value === 'pendiente' && $m->motivoRevision() !== null
        ));
        usort($pendientes, fn ($a, $b) => strcmp((string) $a->codigoMuestra(), (string) $b->codigoMuestra()));

        return array_slice($pendientes, $offset, $limit);
    }

    public function contarParaRevision(): int
    {
        return count(array_filter(
            $this->store,
            fn (MuestraColecta $m) => $m->estadoRevision()->value === 'pendiente' && $m->motivoRevision() !== null
        ));
    }
}
