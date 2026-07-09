<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface ProveedorEspecimenesPort
{
    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor;

    /** @return DatosEspecimenProveedor[] */
    public function obtenerTodos(): array;

    /**
     * Cuenta especímenes con occurrence_id, excluyendo los IDs indicados.
     *
     * @param  list<int|string>  $especimenIdsExcluir  IDs de taxonomia.especimenes.id a excluir
     */
    public function contar(array $especimenIdsExcluir = []): int;

    /**
     * Página de especímenes con occurrence_id, ordenados por occurrence_id ascendente.
     *
     * @param  list<int|string>  $especimenIdsExcluir  IDs de taxonomia.especimenes.id a excluir
     * @return DatosEspecimenProveedor[]
     */
    public function obtenerPaginado(int $offset, int $limit, array $especimenIdsExcluir = []): array;

    /**
     * @param  list<string>  $occurrenceIds
     * @return DatosEspecimenProveedor[]
     */
    public function buscarPorOccurrenceIds(array $occurrenceIds): array;

    /** @return DatosEspecimenProveedor[] */
    public function buscarPorNombreCientifico(string $scientificName): array;
}
