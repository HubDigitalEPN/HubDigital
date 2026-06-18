<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

/**
 * Proveedor volátil para Behat. La jerarquía taxonómica se siembra una vez en
 * los antecedentes y la visibilidad se lee dinámicamente del repositorio de
 * divulgables en cada consulta — espejo del INNER JOIN con
 * divulgacion.especimenes_divulgables del adaptador Eloquent.
 */
final class InMemoryProveedorEspecimenesParaArbol implements ProveedorEspecimenesParaArbolPort
{
    /** @var array<string, JerarquiaTaxonomica> occurrenceID => jerarquía */
    private array $jerarquias = [];

    public function __construct(
        private readonly InMemoryEspecimenDivulgableRepository $repoDivulgable,
    ) {}

    public function agregar(string $occurrenceID, JerarquiaTaxonomica $jerarquia): void
    {
        $this->jerarquias[$occurrenceID] = $jerarquia;
    }

    /** @return list<EspecimenParaArbol> */
    public function obtenerTodos(?FiltrosBusqueda $filtros = null): array
    {
        $result = [];

        foreach ($this->jerarquias as $occurrenceID => $jerarquia) {
            $divulgable = $this->repoDivulgable->buscarPorOccurrenceID($occurrenceID);

            // Sólo aparecen especímenes con fila en la tabla de divulgación.
            if ($divulgable === null) {
                continue;
            }

            $result[] = EspecimenParaArbol::crear(
                occurrenceID: $occurrenceID,
                jerarquia: $jerarquia,
                genusVisible: $divulgable->genusVisible(),
                scientificNameVisible: $divulgable->scientificNameVisible(),
            );
        }

        return $result;
    }
}
