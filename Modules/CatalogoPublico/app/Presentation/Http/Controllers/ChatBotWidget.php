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
            $consulta = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\OrtografiaConsultaChat::class)->corregir($pregunta);
            $historial = array_column(array_filter(array_slice($this->mensajes, 0, -1), static fn ($m) => ($m['rol'] ?? '') === 'visitante'), 'texto');
            $output = $asistente->responder($contexto->continuarConsulta($consulta), $handler, $previous['node_id'] ?? null, $previous['variants'] ?? [], $previous['entities'] ?? [], $seleccionPortal, $previous['solicitud_pendiente'] ?? null, historial: array_slice($historial, -2));
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
            'tabla' => $output['tabla'] ?? null,
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
        // La orientación y el contexto conservan sus criterios; sólo se adaptan
        // las acciones visibles al mismo permiso que aplica el catálogo.
        $mensajesVisibles = $this->mensajes;
        if (! \App\Support\DescargasCatalogo::permitidas()) {
            foreach ($mensajesVisibles as &$mensaje) {
                if ($mensaje['rol'] !== 'chatbot') continue;
                $mensaje['opciones'] = array_values(array_filter(array_map(
                    fn (array $opcion): ?array => $this->opcionPublica($opcion),
                    $mensaje['opciones'] ?? [],
                )));
            }
            unset($mensaje);
        }

        return view('catalogopublico::livewire.chat-bot-widget', compact('mensajesVisibles'));
    }

    private function opcionPublica(array $opcion): ?array
    {
        if (isset($opcion['url'])) {
            $base = strtok($opcion['url'], '?#');
            if ($base === route('portal.lista-especies')) return null;
            if (str_contains($base, '/portal/descargas/')) return null;
        }
        $etiqueta = Str::lower(Str::ascii($opcion['label'] ?? ''));
        if (preg_match('/descarg|export/u', $etiqueta)) return null;

        return $opcion;
    }
}
