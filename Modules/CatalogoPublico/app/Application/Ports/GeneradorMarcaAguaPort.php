<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

interface GeneradorMarcaAguaPort
{
    /**
     * Devuelve el contenido binario de la imagen con la marca de agua aplicada.
     */
    public function aplicar(string $contenido, string $texto): string;
}
