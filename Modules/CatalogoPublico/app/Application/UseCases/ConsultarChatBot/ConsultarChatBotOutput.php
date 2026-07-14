<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;

final readonly class ConsultarChatBotOutput
{
    /** @param list<string> $especimenesReferenciados */
    private function __construct(
        public string $respuesta,
        public bool $dentroDeDominio,
        public array $especimenesReferenciados,
        public ?ContextoLLM $contexto,
    ) {}

    public static function fueraDeDominio(string $respuesta): self
    {
        return new self(
            respuesta: $respuesta,
            dentroDeDominio: false,
            especimenesReferenciados: [],
            contexto: null,
        );
    }

    public static function conRespuesta(string $respuesta, ContextoLLM $contexto): self
    {
        return new self(
            respuesta: $respuesta,
            dentroDeDominio: true,
            especimenesReferenciados: $contexto->occurrenceIDs(),
            contexto: $contexto,
        );
    }
}
