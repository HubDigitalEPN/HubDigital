<div
    class="portal-chat-shell pointer-events-none fixed inset-x-0 bottom-4 z-[9999] flex justify-end px-4 sm:bottom-6 sm:px-6"
    x-data="{ abierto: false }"
    x-on:keydown.escape.window="if (abierto && !$event.defaultPrevented && !document.querySelector('dialog[open]')) { $event.preventDefault(); abierto = false; $nextTick(() => $refs.trigger.focus()) }"
    x-on:chat-respuesta.window="$nextTick(() => { $refs.messages.scrollTop = $refs.messages.scrollHeight; $refs.input.focus() })"
>
    <div class="pointer-events-none flex w-full max-w-[25rem] flex-col items-end gap-2">
        <section
            id="chat-bot-panel"
            x-cloak
            x-show="abierto"
            x-transition.opacity.duration.100ms
            aria-label="Asistente HubDigital"
            class="pointer-events-auto flex h-[min(34rem,calc(100dvh-2rem))] w-full flex-col overflow-hidden rounded-xl border border-blue-navy/15 bg-white shadow-2xl sm:h-[min(34rem,calc(100dvh-7rem))]"
        >
            <header class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-2 gap-y-1 bg-blue-navy px-3 py-2 text-white sm:px-4">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/10" aria-hidden="true">
                        <flux:icon name="chat-bubble-left-right" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold leading-tight">Asistente HubDigital</p>
                        <p class="text-xs text-white/75">Portal, colección y fuentes públicas</p>
                    </div>
                </div>
                <button type="button" x-on:click="abierto = false; $nextTick(() => $refs.trigger.focus())"
                    class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-white transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    aria-label="Cerrar chat">
                    <flux:icon name="x-mark" class="size-5" />
                </button>
                <button type="button" wire:click="nuevaConversacion" wire:loading.attr="disabled" wire:target="nuevaConversacion"
                    class="col-span-2 justify-self-start cursor-pointer rounded-lg px-2 py-1 text-xs font-semibold text-white/90 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white" title="Borrar el contexto de este chat">Nueva conversación</button>
            </header>

            <div x-ref="messages" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-[#F5F8FC] px-3 py-4" aria-live="polite" aria-relevant="additions text">
                @forelse($mensajes as $indice => $mensaje)
                    @if($mensaje['rol'] === 'visitante')
                        <div class="flex justify-end" wire:key="chat-user-{{ $indice }}">
                            <div class="max-w-[86%] whitespace-pre-wrap break-words rounded-2xl rounded-br-sm bg-blue-navy px-3.5 py-2.5 text-sm leading-5 text-white shadow-sm">{{ $mensaje['texto'] }}</div>
                        </div>
                    @else
                        <div class="flex items-end gap-2" wire:key="chat-assistant-{{ $indice }}">
                            <span class="mb-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/10 text-science-blue" aria-hidden="true">
                                <flux:icon name="sparkles" class="size-4" />
                            </span>
                            <div class="max-w-[88%] min-w-0 rounded-2xl rounded-bl-sm border border-blue-navy/10 bg-white px-3.5 py-2.5 text-sm leading-5 text-blue-navy shadow-sm">
                                <p class="whitespace-pre-wrap break-words">{{ $mensaje['texto'] }}</p>
                                @if(! empty($mensaje['opciones']))
                                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                                        @foreach($mensaje['opciones'] as $opcion)
                                            @if(isset($opcion['pregunta']))
                                                <button type="button" wire:click="sugerir(@js($opcion['pregunta']))" wire:loading.attr="disabled" wire:target="enviar,sugerir"
                                                    class="cursor-pointer rounded-full border border-science-blue/25 bg-[#F5F8FC] px-2.5 py-1.5 text-xs font-medium text-science-blue transition hover:border-science-blue hover:bg-science-blue/10">
                                                    {{ $opcion['label'] }}
                                                </button>
                                            @else
                                                <a href="{{ $opcion['url'] }}" class="rounded-full border border-science-blue/25 bg-[#F5F8FC] px-2.5 py-1.5 text-xs font-medium !text-science-blue transition hover:border-science-blue hover:bg-science-blue/10">
                                                    {{ $opcion['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if(! empty($mensaje['node_id']))
                                    <div class="mt-2 border-t border-blue-navy/10 pt-1.5 text-[11px] text-text-secondary">
                                        @if(empty($mensaje['valorado']))
                                            <span>¿Te sirvió?</span>
                                            <button type="button" wire:click="valorar({{ $indice }}, true)" class="ml-2 cursor-pointer font-semibold text-science-blue hover:underline">Sí</button>
                                            <button type="button" wire:click="valorar({{ $indice }}, false)" class="ml-2 cursor-pointer font-semibold text-science-blue hover:underline">No</button>
                                        @else
                                            Gracias por tu respuesta.
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="flex items-end gap-2">
                        <span class="mb-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-science-blue/10 text-science-blue" aria-hidden="true"><flux:icon name="sparkles" class="size-4" /></span>
                        <div class="max-w-[88%] rounded-2xl rounded-bl-sm border border-blue-navy/10 bg-white px-3.5 py-2.5 text-sm leading-5 text-blue-navy shadow-sm">
                            ¡Hola! Puedo ayudarte con el portal, consultar cantidades de la colección y buscar información general en fuentes públicas. Escribe tu pregunta o explora el menú.
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pl-9">
                        @foreach(['Menú', '¿Cuántas especies hay en la colección?', '¿Cómo aplico los filtros del mapa?'] as $sugerencia)
                            <button type="button" wire:click="sugerir(@js($sugerencia))" wire:loading.attr="disabled" wire:target="enviar,sugerir"
                                class="cursor-pointer rounded-full border border-science-blue/25 bg-white px-2.5 py-1.5 text-xs font-medium text-science-blue transition hover:border-science-blue hover:bg-science-blue/10">{{ $sugerencia }}</button>
                        @endforeach
                    </div>
                @endforelse
                <div wire:loading wire:target="enviar,sugerir" class="pl-9 text-xs text-text-secondary" role="status">Consultando…</div>
            </div>

            <p wire:offline class="border-t border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">Sin conexión. Cuando vuelva, puedes reenviar tu pregunta.</p>

            <form wire:submit="enviar" class="flex items-end gap-2 border-t border-blue-navy/10 bg-white p-3">
                <input x-ref="input" type="text" wire:model="pregunta" maxlength="500" required
                    placeholder="Escribe tu pregunta…" aria-label="Escribe tu pregunta al asistente"
                    class="min-h-11 min-w-0 flex-1 rounded-xl border border-blue-navy/20 bg-white px-3 text-sm text-blue-navy placeholder:text-text-secondary focus:border-science-blue focus:outline-none focus:ring-2 focus:ring-science-blue/20" />
                <button type="submit" wire:loading.attr="disabled" wire:target="enviar,sugerir"
                    class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-blue-navy text-white transition hover:bg-[#244872] disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue"
                    aria-label="Enviar pregunta">
                    <span wire:loading.remove wire:target="enviar,sugerir"><flux:icon name="paper-airplane" class="size-5" /></span>
                    <span wire:loading wire:target="enviar,sugerir"><flux:icon name="arrow-path" class="size-5 animate-spin" /></span>
                </button>
            </form>
        </section>

        <button x-ref="trigger" id="chat-bot-trigger" type="button"
            x-on:click="abierto = true; $nextTick(() => $refs.input.focus())"
            x-show="!abierto"
            class="pointer-events-auto flex size-11 cursor-pointer items-center justify-center rounded-full bg-blue-navy text-white shadow-lg transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2 sm:size-14"
            aria-controls="chat-bot-panel" aria-label="Abrir asistente HubDigital" :aria-expanded="abierto.toString()">
            <flux:icon name="chat-bubble-left-right" class="size-6" />
        </button>
    </div>
</div>
