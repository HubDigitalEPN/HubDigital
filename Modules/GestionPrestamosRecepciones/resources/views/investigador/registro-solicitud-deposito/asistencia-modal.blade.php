<flux:modal name="asistencia-depositos" :closable="false" class="hub-doc-chat-modal" aria-labelledby="asistente-documental-titulo">
    <div class="hub-doc-chat">
        <header class="hub-doc-chat-heading" role="none">
            <span class="hub-doc-chat-avatar" aria-hidden="true"><flux:icon name="chat-bubble-left-right" class="size-5" /></span>
            <div class="hub-doc-chat-heading-text">
                <flux:heading level="2" id="asistente-documental-titulo" class="hub-doc-chat-title">Asistente documental</flux:heading>
                <p>Orientación para tu expediente</p>
            </div>
            <flux:modal.close><button type="button" class="hub-doc-chat-close" aria-label="Cerrar asistente documental"><flux:icon name="x-mark" class="size-5" /></button></flux:modal.close>
        </header>

        <p class="hub-doc-chat-context">Paso 4 · Archivos y firmas <span>Pregunta por lo que debes adjuntar o por un aviso de carga.</span></p>

        <div role="log" aria-live="polite" aria-relevant="additions" aria-label="Conversación con el asistente documental"
             x-on:asistencia-mensaje-enviado.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
             class="hub-doc-chat-log">
            @if($mensajesAsistencia === [])
                <div class="hub-doc-chat-message hub-doc-chat-message--assistant">
                    <span class="hub-doc-chat-author">Asistente</span>
                    <p>¡Hola! Te ayudo con los documentos de esta solicitud. Puedes escribir tu pregunta o elegir una de las opciones de abajo.</p>
                </div>
            @endif
            @foreach($mensajesAsistencia as $indice => $mensaje)
                <div wire:key="asistencia-{{ $indice }}" class="hub-doc-chat-message {{ $mensaje['rol'] === 'usuario' ? 'hub-doc-chat-message--user' : 'hub-doc-chat-message--assistant' }}">
                    <span class="hub-doc-chat-author">{{ $mensaje['rol'] === 'usuario' ? 'Tú' : 'Asistente' }}</span>
                    <p>{{ $mensaje['texto'] }}</p>
                </div>
            @endforeach
            <div wire:loading wire:target="enviarPreguntaAsistencia,sugerirPreguntaAsistencia" class="hub-doc-chat-typing" role="status">
                <span aria-hidden="true">•••</span> Preparando respuesta…
            </div>
        </div>

        <footer class="hub-doc-chat-footer">
            <div class="hub-doc-chat-suggestions" aria-label="Preguntas rápidas">
                <button type="button" wire:click="sugerirPreguntaAsistencia('requeridos')">Qué debo subir</button>
                @if(collect($documentosRequeridos)->contains(fn ($nombre) => str_contains(mb_strtolower($nombre), 'justific')))
                    <button type="button" wire:click="sugerirPreguntaAsistencia('justificacion')">Carta de justificación</button>
                @else
                    <button type="button" wire:click="sugerirPreguntaAsistencia('autorizacion')">Autorización</button>
                    <button type="button" wire:click="sugerirPreguntaAsistencia('movilizacion')">Movilización</button>
                @endif
                <button type="button" wire:click="sugerirPreguntaAsistencia('firmas')">Firmas</button>
                <button type="button" wire:click="sugerirPreguntaAsistencia('expediente')">Mismo expediente</button>
                <button type="button" wire:click="sugerirPreguntaAsistencia('formato')">PDF y tamaño</button>
            </div>
            <form wire:submit="enviarPreguntaAsistencia" class="hub-doc-chat-compose">
                <input wire:model="preguntaAsistencia" type="text" maxlength="240" required
                       aria-label="Escribe tu pregunta" placeholder="Escribe tu pregunta…" autocomplete="off" />
                <button type="submit" wire:loading.attr="disabled" wire:target="enviarPreguntaAsistencia,sugerirPreguntaAsistencia"
                        aria-label="Enviar pregunta" class="hub-doc-chat-send bg-science-blue text-white">
                    <flux:icon name="paper-airplane" class="size-5" />
                </button>
            </form>
            <p class="hub-doc-chat-hint">Hasta 240 caracteres · Puedes hacer preguntas de seguimiento.</p>
        </footer>
    </div>
</flux:modal>
