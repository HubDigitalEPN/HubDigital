<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

#[Temperature(0.2)]
final class ChatBotAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        private readonly string $instrucciones,
    ) {}

    public function instructions(): string
    {
        return $this->instrucciones;
    }

    public function messages(): iterable
    {
        return [];
    }
}
