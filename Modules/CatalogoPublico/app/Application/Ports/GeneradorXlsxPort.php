<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface GeneradorXlsxPort
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<array<string,string>>  $filas
     */
    public function generar(array $encabezados, iterable $filas): string;
}
