<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\ProveedorJerarquiaDeEspecimenPort;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

/**
 * Proveedor volátil de jerarquías para Behat. La estructura taxonómica se
 * siembra en los antecedentes — espejo del SELECT contra divulgacion.especimenes.
 */
final class FakeProveedorJerarquiaDeEspecimen implements ProveedorJerarquiaDeEspecimenPort
{
    /** @var array<string, JerarquiaTaxonomica> occurrenceID => jerarquía */
    private array $jerarquias = [];

    public function agregar(string $occurrenceID, JerarquiaTaxonomica $jerarquia): void
    {
        $this->jerarquias[$occurrenceID] = $jerarquia;
    }

    public function obtener(string $occurrenceID): ?JerarquiaTaxonomica
    {
        return $this->jerarquias[$occurrenceID] ?? null;
    }
}
