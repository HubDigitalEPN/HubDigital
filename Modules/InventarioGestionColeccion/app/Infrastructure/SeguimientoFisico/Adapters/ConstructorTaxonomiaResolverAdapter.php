<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ResolverTaxonomiaDwCPort;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConstructorTaxonomiaImport;

/**
 * Resuelve la jerarquía declarada reutilizando el constructor del importador masivo.
 *
 * **Por qué precarga el catálogo entero.** Medido contra esta base: traer los 4.003
 * taxones cuesta 1,9 s en una sola consulta, mientras que una consulta puntual cuesta
 * 320 ms por el coste fijo de red. Resolver un lote de trece filas sin precarga son hasta
 * ciento diecisiete consultas, unos treinta y siete segundos. La precarga no es un
 * derroche para lotes pequeños: es veinte veces más rápida.
 *
 * **Por qué se precarga tarde.** Ese segundo y pico se paga dentro de la petición del
 * curador, así que solo se paga cuando de verdad hay algo que resolver. Un depósito cuya
 * taxonomía está entera sin validar no toca la base ni una vez.
 */
final class ConstructorTaxonomiaResolverAdapter implements ResolverTaxonomiaDwCPort
{
    private bool $precargado = false;

    public function __construct(
        private readonly ConstructorTaxonomiaImport $constructor,
    ) {}

    public function resolver(array $jerarquia): ?string
    {
        if (! $this->precargado) {
            $this->constructor->precargarTaxonomiaExistente();
            $this->precargado = true;
        }

        return $this->constructor->resolverDeFila($jerarquia)?->__toString();
    }
}
