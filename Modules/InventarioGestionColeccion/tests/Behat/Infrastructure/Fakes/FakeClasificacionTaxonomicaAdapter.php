<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ClasificacionTaxonomicaPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;

final class FakeClasificacionTaxonomicaAdapter implements ClasificacionTaxonomicaPort
{
    /** @var array<string, ClasificacionTaxonomica> taxonId => clasificación */
    private array $porTaxon = [];

    public function registrar(string $taxonId, ClasificacionTaxonomica $clasificacion): void
    {
        $this->porTaxon[$taxonId] = $clasificacion;
    }

    public function resolverParaTaxon(string $taxonId): ?ClasificacionTaxonomica
    {
        return $this->porTaxon[$taxonId] ?? null;
    }
}
