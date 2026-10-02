<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\QueryException;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AnaliticaChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;

final class ChatBotWidget extends Component
{
    public string $pregunta = '';

    /** @var list<array{rol: 'visitante'|'chatbot', texto: string, referencias?: list<string>}> */
    public array $mensajes = [];

    public function enviar(AsistentePortal $asistente, ConsultarChatBotHandler $handler, ContextoChat $contexto, AnaliticaChat $analitica, ?array $seleccionPortal = null): void
    {
        $pregunta = trim($this->pregunta);

        if ($pregunta === '') {
            return;
        }
        if (strlen($pregunta) > 2048 || mb_strlen($pregunta) > 500) {
            $this->pregunta = '';
            $this->mensajes[] = ['rol' => 'chatbot', 'texto' => 'La pregunta es demasiado larga. Resume tu consulta en 500 caracteres o menos.'];
            $this->mensajes = array_slice($this->mensajes, -20);
            return;
        }
        $limit = auth()->check() ? 60 : 30;
        $identity = auth()->check() ? 'user:'.auth()->id() : 'session:'.session()->getId();
        $key = 'portal-chat:'.hash('sha256', $identity);
        $ipKey = 'portal-chat-ip:'.hash('sha256', request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, $limit) || RateLimiter::tooManyAttempts($ipKey, 300)) {
            $this->pregunta = '';
            $this->mensajes[] = ['rol' => 'chatbot', 'texto' => 'Has enviado muchas preguntas seguidas. Espera un minuto y vuelve a intentarlo.'];
            $this->mensajes = array_slice($this->mensajes, -20);
            return;
        }
        RateLimiter::hit($key, 60);
        RateLimiter::hit($ipKey, 60);

        $this->mensajes[] = ['rol' => 'visitante', 'texto' => $pregunta];
        $this->pregunta = '';
        $start = microtime(true);
        try {
            $previous = $contexto->obtener();
            $output = $asistente->responder($pregunta, $handler, $previous['node_id'] ?? null, $previous['variants'] ?? [], $previous['entities'] ?? [], $seleccionPortal);
            $output['fuente'] ??= 'legacy';
            $contexto->guardar($previous, $output);
        } catch (QueryException $error) {
            report($error);
            $output = ['texto' => 'El servicio de datos no está disponible en este momento. Inténtalo de nuevo en unos minutos.',
                'opciones' => [['label' => 'Reintentar', 'pregunta' => $pregunta]], 'fuente' => 'error'];
        } catch (\Throwable $error) {
            report($error);
            $output = ['texto' => 'Ocurrió un error al responder. Puedes reintentar la pregunta.',
                'opciones' => [['label' => 'Reintentar', 'pregunta' => $pregunta]], 'fuente' => 'error'];
        }
        try {
            $output['message_id'] = $analitica->registrarMensaje($output, round((microtime(true) - $start) * 1000, 2));
        } catch (\Throwable $error) {
            report($error);
        }

        $feedbackToken = null;
        if (isset($output['node_id'])) {
            $feedbackToken = Str::random(24);
            $feedback = session()->get('portal_chat_feedback', []);
            $feedback[$feedbackToken] = ['node_id' => (int) $output['node_id'], 'variant_id' => $output['variant_id'] ?? null];
            session()->put('portal_chat_feedback', array_slice($feedback, -20, null, true));
        }
        $this->mensajes[] = [
            'rol' => 'chatbot',
            'texto' => $output['texto'],
            'opciones' => $output['opciones'] ?? [],
            'node_id' => $output['node_id'] ?? null,
            'variant_id' => $output['variant_id'] ?? null,
            'message_id' => $output['message_id'] ?? null,
            'feedback_token' => $feedbackToken,
            'valorado' => false,
        ];
        $this->mensajes = array_slice($this->mensajes, -20);
        $this->dispatch('chat-respuesta');
    }

    public function sugerir(string $pregunta, AsistentePortal $asistente, ConsultarChatBotHandler $handler, ContextoChat $contexto, AnaliticaChat $analitica, ?array $seleccionPortal = null): void
    {
        $this->pregunta = $pregunta;
        $this->enviar($asistente, $handler, $contexto, $analitica, $seleccionPortal);
    }

    public function nuevaConversacion(ContextoChat $contexto): void
    {
        $contexto->reiniciar();
        session()->forget(['portal_chat_feedback', 'portal_chat_conversation_until']);
        $this->reset('mensajes', 'pregunta');
    }

    public function valorar(int $indice, bool $util, ConocimientoPortal $conocimiento, AnaliticaChat $analitica): void
    {
        $mensaje = $this->mensajes[$indice] ?? null;
        if (! is_array($mensaje) || $mensaje['rol'] !== 'chatbot' || empty($mensaje['feedback_token']) || ! empty($mensaje['valorado'])) {
            return;
        }
        $feedback = session()->get('portal_chat_feedback', []);
        $token = $mensaje['feedback_token'];
        if (! isset($feedback[$token])) {
            return;
        }
        $entry = $feedback[$token];
        unset($feedback[$token]);
        session()->put('portal_chat_feedback', $feedback);
        $conocimiento->valorar((int) $entry['node_id'], isset($entry['variant_id']) ? (int) $entry['variant_id'] : null, $util);
        $analitica->registrarFeedback($util);
        $this->mensajes[$indice]['valorado'] = true;
    }

    public function render(): View
    {
        return view('catalogopublico::livewire.chat-bot-widget');
    }
}
