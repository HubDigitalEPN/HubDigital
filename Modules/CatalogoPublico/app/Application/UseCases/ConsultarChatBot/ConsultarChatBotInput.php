<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

final readonly class ConsultarChatBotInput
{
    public function __construct(
        public string $pregunta,
    ) {}
}
