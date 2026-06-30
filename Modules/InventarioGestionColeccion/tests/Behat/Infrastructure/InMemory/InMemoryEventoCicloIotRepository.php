<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;

final class InMemoryEventoCicloIotRepository implements EventoCicloIotRepository
{
    /** @var EventoCicloIot[] */
    private array $store = [];

    public function guardar(EventoCicloIot $evento): void
    {
        $this->store[] = $evento;
    }

    public function buscarUltimoPorAgregadoYTipo(string $agregadoId, string $tipoEvento): ?EventoCicloIot
    {
        foreach (array_reverse($this->store) as $evento) {
            if ($evento->agregadoId() === $agregadoId && $evento->tipoEvento() === $tipoEvento) {
                return $evento;
            }
        }

        return null;
    }

    public function buscarPorAgregado(string $tipoAgregado, string $agregadoId): array
    {
        $eventos = array_values(array_filter(
            $this->store,
            fn (EventoCicloIot $e): bool => $e->tipoAgregado() === $tipoAgregado && $e->agregadoId() === $agregadoId,
        ));

        usort($eventos, fn (EventoCicloIot $a, EventoCicloIot $b): int => $a->ocurridoEn() <=> $b->ocurridoEn());

        return $eventos;
    }
}
