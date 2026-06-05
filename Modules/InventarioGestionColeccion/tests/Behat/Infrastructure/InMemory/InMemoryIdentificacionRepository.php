<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;

final class InMemoryIdentificacionRepository implements IdentificacionRepositoryInterface
{
    /** @var array<string, Identificacion> */
    private array $store = [];

    public function nextIdentity(): IdentificacionId
    {
        return IdentificacionId::generar();
    }

    public function guardar(Identificacion $identificacion): void
    {
        $this->store[(string) $identificacion->id()] = $identificacion;
    }

    public function buscarPorId(IdentificacionId $id): ?Identificacion
    {
        return $this->store[(string) $id] ?? null;
    }

    /** @return Identificacion[] */
    public function listarPorEspecimen(EspecimenId $especimenId): array
    {
        $items = array_values(array_filter(
            $this->store,
            fn (Identificacion $i) => (string) $i->especimenId() === (string) $especimenId
        ));

        usort($items, function (Identificacion $a, Identificacion $b): int {
            if ($a->esActual() !== $b->esActual()) {
                return $a->esActual() ? -1 : 1;
            }
            $fechaA = $a->fechaDeterminacion()?->format('Y-m-d') ?? '';
            $fechaB = $b->fechaDeterminacion()?->format('Y-m-d') ?? '';

            return strcmp($fechaB, $fechaA);
        });

        return $items;
    }

    public function buscarActualDe(EspecimenId $especimenId): ?Identificacion
    {
        foreach ($this->store as $i) {
            if ((string) $i->especimenId() === (string) $especimenId && $i->esActual()) {
                return $i;
            }
        }

        return null;
    }

    public function desactualizarTodasDe(EspecimenId $especimenId): void
    {
        foreach ($this->store as $i) {
            if ((string) $i->especimenId() === (string) $especimenId && $i->esActual()) {
                $i->marcarComoSuperseded();
            }
        }
    }
}
