<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;

interface GeneradorRespuestaChatBotPort
{
    public function generar(ContextoLLM $contexto): string;
}
