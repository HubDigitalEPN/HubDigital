<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

final class InMemoryRanuraGabineteRepository implements RanuraGabineteRepository
{
    /** @var array<string, RanuraGabinete> */
    private array $store = [];

    public function nextIdentity(): RanuraId
    {
        return RanuraId::generar();
    }

    public function guardar(RanuraGabinete $ranura): void
    {
        $this->store[(string) $ranura->id()] = $ranura;
    }

    public function eliminar(RanuraId $id): void
    {
        unset($this->store[(string) $id]);
    }

    public function buscarPorId(RanuraId $id): ?RanuraGabinete
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return RanuraGabinete[] */
    public function buscarPorGabinete(GabineteId $gabineteId): array
    {
        $ranuras = array_values(array_filter(
            $this->store,
            fn (RanuraGabinete $r) => $r->gabineteId()->equals($gabineteId),
        ));

        usort($ranuras, fn (RanuraGabinete $a, RanuraGabinete $b) => $a->numeroRanura() <=> $b->numeroRanura());

        return $ranuras;
    }

    public function buscarPorNumeroEnGabinete(GabineteId $gabineteId, int $numeroRanura): ?RanuraGabinete
    {
        foreach ($this->store as $ranura) {
            if ($ranura->gabineteId()->equals($gabineteId) && $ranura->numeroRanura() === $numeroRanura) {
                return $ranura;
            }
        }

        return null;
    }

    /** @return RanuraGabinete[] */
    public function buscarVecinasOcupadas(GabineteId $gabineteId, int $numeroRanura): array
    {
        $delGabinete = array_filter(
            $this->store,
            fn (RanuraGabinete $r) => $r->gabineteId()->equals($gabineteId) && $r->estaOcupada(),
        );

        $anteriores = array_filter($delGabinete, fn (RanuraGabinete $r) => $r->numeroRanura() < $numeroRanura);
        $siguientes = array_filter($delGabinete, fn (RanuraGabinete $r) => $r->numeroRanura() > $numeroRanura);

        $resultado = [];

        if ($anteriores !== []) {
            usort($anteriores, fn (RanuraGabinete $a, RanuraGabinete $b) => $b->numeroRanura() <=> $a->numeroRanura());
            $resultado[] = reset($anteriores);
        }

        if ($siguientes !== []) {
            usort($siguientes, fn (RanuraGabinete $a, RanuraGabinete $b) => $a->numeroRanura() <=> $b->numeroRanura());
            $resultado[] = reset($siguientes);
        }

        return $resultado;
    }
}
