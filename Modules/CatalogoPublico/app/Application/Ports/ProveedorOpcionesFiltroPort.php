<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface ProveedorOpcionesFiltroPort
{
    /** @return list<string> */
    public function obtenerPreparaciones(): array;

    /** @return list<string> */
    public function obtenerBiomas(): array;

    /** @return list<string> */
    public function obtenerMetodosRecoleccion(): array;

    /** @return list<string> */
    public function obtenerColectores(): array;
}
