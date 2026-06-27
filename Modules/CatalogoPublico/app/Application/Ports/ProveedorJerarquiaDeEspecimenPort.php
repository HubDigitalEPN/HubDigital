<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

interface ProveedorJerarquiaDeEspecimenPort
{
    /**
     * Jerarquía taxonómica del espécimen divulgado, o null si no existe en la
     * tabla de divulgación.
     */
    public function obtener(string $occurrenceID): ?JerarquiaTaxonomica;
}
