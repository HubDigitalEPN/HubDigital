<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface GeneradorXlsxPort
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<array<string,string>>  $filas
     */
    public function generar(array $encabezados, array $filas): string;
}
