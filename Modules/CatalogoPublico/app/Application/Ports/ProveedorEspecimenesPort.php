<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface ProveedorEspecimenesPort
{
    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor;

    /** @return DatosEspecimenProveedor[] */
    public function obtenerTodos(): array;
}
