<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Component;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotInput;

final class ChatBotWidget extends Component
{
    public bool $abierto = false;

    public string $pregunta = '';

    public bool $procesando = false;

    /** @var list<array{rol: 'visitante'|'chatbot', texto: string, referencias?: list<string>}> */
    public array $mensajes = [];

    public function alternar(): void
    {
        $this->abierto = ! $this->abierto;
    }

    public function enviar(ConsultarChatBotHandler $handler): void
    {
        $pregunta = trim($this->pregunta);

        if ($pregunta === '') {
            return;
        }

        $this->mensajes[] = ['rol' => 'visitante', 'texto' => $pregunta];
        $this->pregunta = '';
        $this->procesando = true;

        $output = $handler->handle(new ConsultarChatBotInput($pregunta));

        $this->mensajes[] = [
            'rol' => 'chatbot',
            'texto' => $output->respuesta,
            'referencias' => $output->especimenesReferenciados,
        ];

        $this->procesando = false;
    }

    public function render(): View
    {
        return view('catalogopublico::livewire.chat-bot-widget');
    }
}
