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

    public function contar(array $especimenIdsExcluir = []): int
    {
        return count($this->pendientesOrdenados($especimenIdsExcluir));
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerPaginado(int $offset, int $limit, array $especimenIdsExcluir = []): array
    {
        return array_slice(
            $this->pendientesOrdenados($especimenIdsExcluir),
            max(0, $offset),
            $limit
        );
    }

    /**
     * @param  list<int|string>  $especimenIdsExcluir
     * @return list<DatosEspecimenProveedor>
     */
    private function pendientesOrdenados(array $especimenIdsExcluir): array
    {
        $excluir = array_flip(array_map('strval', $especimenIdsExcluir));

        $pendientes = array_filter(
            $this->especimenes,
            fn (DatosEspecimenProveedor $d): bool => ! isset($excluir[(string) $d->especimenId])
        );

        usort(
            $pendientes,
            fn (DatosEspecimenProveedor $a, DatosEspecimenProveedor $b): int => strcmp($a->occurrenceId, $b->occurrenceId)
        );

        return array_values($pendientes);
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
