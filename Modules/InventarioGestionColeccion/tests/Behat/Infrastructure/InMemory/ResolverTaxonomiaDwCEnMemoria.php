<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ResolverTaxonomiaDwCPort;

/**
 * Resolutor taxonómico de mentira: devuelve un identificador estable por jerarquía.
 *
 * Los escenarios que lo usan comprueban *a quién* se engancha el espécimen y, sobre todo,
 * *cuándo* se decide engancharlo; el recorrido real del árbol taxonómico ya lo cubren las
 * pruebas del importador del catálogo.
 */
final class ResolverTaxonomiaDwCEnMemoria implements ResolverTaxonomiaDwCPort
{
    /** @var array<string, string> */
    private array $resueltos = [];

    private int $llamadas = 0;

    public function resolver(array $jerarquia): ?string
    {
        $this->llamadas++;

        $clave = implode('|', array_filter($jerarquia, static fn ($v): bool => $v !== null && $v !== ''));

        if ($clave === '') {
            return null;
        }

        return $this->resueltos[$clave] ??= 'taxon-'.substr(md5($clave), 0, 8);
    }

    /** Cuántas veces se pidió resolver: delata si se resuelve lo que no se debe. */
    public function llamadas(): int
    {
        return $this->llamadas;
    }
}
