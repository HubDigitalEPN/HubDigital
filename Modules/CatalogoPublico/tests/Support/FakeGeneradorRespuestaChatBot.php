<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;

final class FakeGeneradorRespuestaChatBot implements GeneradorRespuestaChatBotPort
{
    public const string RESPUESTA_CANONICA = 'RESPUESTA_CANONICA_DEL_LLM';

    public ?ContextoLLM $ultimoContexto = null;

    public int $llamadas = 0;

    public function generar(ContextoLLM $contexto): string
    {
        $this->ultimoContexto = $contexto;
        $this->llamadas++;

        return self::RESPUESTA_CANONICA;
    }
}
