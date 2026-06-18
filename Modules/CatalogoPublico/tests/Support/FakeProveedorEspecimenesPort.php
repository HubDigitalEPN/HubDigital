<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;

final class FakeProveedorEspecimenesPort implements ProveedorEspecimenesPort
{
    /** @var array<string, DatosEspecimenProveedor> */
    private array $especimenes = [];

    public function agregar(DatosEspecimenProveedor $datos): void
    {
        $this->especimenes[$datos->occurrenceId] = $datos;
    }

    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor
    {
        return $this->especimenes[$occurrenceId] ?? null;
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerTodos(): array
    {
        return array_values($this->especimenes);
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return DatosEspecimenProveedor[]
     */
    public function buscarPorOccurrenceIds(array $occurrenceIds): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $id): ?DatosEspecimenProveedor => $this->especimenes[$id] ?? null,
                $occurrenceIds
            )
        ));
    }

    /** @return DatosEspecimenProveedor[] */
    public function buscarPorNombreCientifico(string $scientificName): array
    {
        return array_values(array_filter(
            $this->especimenes,
            fn (DatosEspecimenProveedor $datos): bool => $datos->scientificName === $scientificName
        ));
    }
}
