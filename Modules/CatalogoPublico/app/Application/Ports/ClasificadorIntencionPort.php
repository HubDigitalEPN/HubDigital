<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;

interface ClasificadorIntencionPort
{
    public function clasificar(string $pregunta): IntencionConsulta;
}
