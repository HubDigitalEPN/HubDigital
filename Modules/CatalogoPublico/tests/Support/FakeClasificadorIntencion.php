<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;

final class FakeClasificadorIntencion implements ClasificadorIntencionPort
{
    /** @var array<string, IntencionConsulta> */
    private array $porPregunta = [];

    public int $llamadas = 0;

    public function registrar(string $pregunta, IntencionConsulta $intencion): void
    {
        $this->porPregunta[$pregunta] = $intencion;
    }

    public function clasificar(string $pregunta): IntencionConsulta
    {
        $this->llamadas++;

        if (isset($this->porPregunta[$pregunta])) {
            return $this->porPregunta[$pregunta];
        }

        return IntencionConsulta::fueraDelDominio();
    }
}
