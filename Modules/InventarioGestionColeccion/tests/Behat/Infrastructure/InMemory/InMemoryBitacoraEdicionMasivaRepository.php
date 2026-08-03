<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DetalleEdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;

final class InMemoryBitacoraEdicionMasivaRepository implements BitacoraEdicionMasivaRepositoryInterface
{
    /** @var array<string, EdicionMasiva> */
    private array $ediciones = [];

    /** @var array<string, DetalleEdicionMasiva[]> */
    private array $detalles = [];

    private int $secuencia = 0;

    public function nextIdentity(): string
    {
        // Determinista: los tests comparan ids y un uuid aleatorio los volvería
        // imposibles de aseverar.
        return sprintf('edicion-%08d', ++$this->secuencia);
    }

    public function guardar(EdicionMasiva $edicion): void
    {
        $this->ediciones[$edicion->id()] = $edicion;
    }

    /** @param DetalleEdicionMasiva[] $detalles */
    public function guardarDetalles(array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $this->detalles[$detalle->edicionId()][] = $detalle;
        }
    }

    public function buscarPorId(string $id): ?EdicionMasiva
    {
        return $this->ediciones[$id] ?? null;
    }

    /** @return DetalleEdicionMasiva[] */
    public function detallesDe(string $edicionId): array
    {
        return $this->detalles[$edicionId] ?? [];
    }

    /** @return EdicionMasiva[] */
    public function listarRecientes(int $limite = 20): array
    {
        $todas = array_values($this->ediciones);
        usort($todas, fn (EdicionMasiva $a, EdicionMasiva $b) => $b->creadoEn() <=> $a->creadoEn());

        return array_slice($todas, 0, $limite);
    }

    /** @param DetalleEdicionMasiva[] $detalles */
    public function actualizarEstadoDetalles(array $detalles): void
    {
        // Las entidades ya viven en el store por referencia: su estado se mutó
        // in situ y no hay nada que volcar.
    }
}
