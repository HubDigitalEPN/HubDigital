<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;

interface ProveedorEspecimenesParaArbolPort
{
    /** @return list<EspecimenParaArbol> */
    public function obtenerTodos(): array;
}
