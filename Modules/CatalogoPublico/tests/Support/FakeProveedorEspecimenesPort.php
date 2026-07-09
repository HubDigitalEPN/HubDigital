<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\FiltrosPendientes;
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

    public function contar(array $especimenIdsExcluir = [], ?FiltrosPendientes $filtros = null): int
    {
        return count($this->pendientesOrdenados($especimenIdsExcluir, $filtros));
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerPaginado(int $offset, int $limit, array $especimenIdsExcluir = [], ?FiltrosPendientes $filtros = null): array
    {
        return array_slice(
            $this->pendientesOrdenados($especimenIdsExcluir, $filtros),
            max(0, $offset),
            $limit
        );
    }

    /** @return list<string> */
    public function obtenerColectoresPendientes(array $especimenIdsExcluir = []): array
    {
        $colectores = array_filter(array_map(
            fn (DatosEspecimenProveedor $d): ?string => $d->recordedBy,
            $this->pendientesOrdenados($especimenIdsExcluir, null)
        ));

        $unicos = array_values(array_unique($colectores));
        sort($unicos);

        return $unicos;
    }

    /**
     * @param  list<int|string>  $especimenIdsExcluir
     * @return list<DatosEspecimenProveedor>
     */
    private function pendientesOrdenados(array $especimenIdsExcluir, ?FiltrosPendientes $filtros): array
    {
        $excluir = array_flip(array_map('strval', $especimenIdsExcluir));

        $pendientes = array_filter(
            $this->especimenes,
            function (DatosEspecimenProveedor $d) use ($excluir, $filtros): bool {
                if (isset($excluir[(string) $d->especimenId])) {
                    return false;
                }
                if ($filtros === null) {
                    return true;
                }
                if ($filtros->busquedaCatalogo !== null
                    && stripos($d->occurrenceId, $filtros->busquedaCatalogo) === false) {
                    return false;
                }
                if ($filtros->busquedaTaxonomia !== null
                    && stripos($d->scientificName ?? '', $filtros->busquedaTaxonomia) === false) {
                    return false;
                }
                if ($filtros->fechaDesde !== null
                    && ($d->eventDate === null || $d->eventDate < $filtros->fechaDesde)) {
                    return false;
                }
                if ($filtros->fechaHasta !== null
                    && ($d->eventDate === null || $d->eventDate > $filtros->fechaHasta)) {
                    return false;
                }
                if ($filtros->colector !== null && $d->recordedBy !== $filtros->colector) {
                    return false;
                }

                return true;
            }
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
