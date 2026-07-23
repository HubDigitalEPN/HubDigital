@php
    /**
     * Renderiza el valor legible de una columna de la ficha.
     * - null / '' → «—»
     * - endemic (bool) → Sí / No
     * - resto → string
     */
    $valorFicha = function (array $ficha, string $clave) {
        $v = $ficha[$clave] ?? null;
        if (is_bool($v)) {
            return $v ? 'Sí' : 'No';
        }
        if ($v === null || $v === '') {
            return '—';
        }
        return (string) $v;
    };
    $porGrupo = collect($grupos)->groupBy('grupo');
@endphp

<div>
    <flux:modal wire:model="abierto" class="w-full max-w-3xl">
        <div class="space-y-4">
            <header class="flex flex-col gap-1">
                <flux:heading size="lg" class="font-serif">
                    Ficha del espécimen
                </flux:heading>
                @if($codigo)
                    <p class="font-mono text-sm text-text-secondary">{{ $codigo }}</p>
                @endif
            </header>

            @if($errorMessage)
                <div class="rounded-lg bg-error/10 p-3 text-sm text-error">{{ $errorMessage }}</div>
            @elseif($noEncontrado)
                <div class="rounded-lg bg-warning/10 p-3 text-sm text-warning">
                    No se encontró el espécimen solicitado.
                </div>
            @else
                <div class="max-h-[70vh] space-y-5 overflow-y-auto pr-1">
                    @foreach($etiquetasGrupo as $claveGrupo => $tituloGrupo)
                        @if($porGrupo->has($claveGrupo))
                            <section>
                                <h3 class="mb-2 border-b border-border pb-1 text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                    {{ $tituloGrupo }}
                                </h3>
                                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                                    @foreach($porGrupo->get($claveGrupo) as $col)
                                        <div class="flex flex-col gap-0.5 border-b border-border/40 py-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                            <dt class="flex items-center gap-1.5 text-xs text-text-secondary">
                                                <span class="inline-block size-1.5 shrink-0 rounded-full
                                                    @if($col['prioridad']==='critica') bg-error
                                                    @elseif($col['prioridad']==='recomendada') bg-warning
                                                    @else bg-border @endif"></span>
                                                {{ $col['etiqueta'] }}
                                            </dt>
                                            <dd class="break-words text-sm text-text-primary sm:text-right">
                                                {{ $valorFicha($ficha, $col['clave']) }}
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        @endif
                    @endforeach

                    @if(!empty($ficha['identificadores']))
                        <section>
                            <h3 class="mb-2 border-b border-border pb-1 text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                Identificadores
                            </h3>
                            <ul class="space-y-1 text-sm text-text-primary">
                                @foreach($ficha['identificadores'] as $ident)
                                    <li class="font-mono text-xs">
                                        {{ $ident['tipo'] ?? '' }}: {{ $ident['valor'] ?? '' }}
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="cerrar" variant="ghost">Cerrar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
