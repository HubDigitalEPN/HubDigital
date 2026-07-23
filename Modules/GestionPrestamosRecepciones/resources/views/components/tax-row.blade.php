@props([
    'registroId',
    'catalogoId' => null,
    'especieIngresada',
    'estado',
    'especieSugerida' => null,
    'especiesSugeridas' => [],
    'especieCorregida' => null,
    'noCatalogado' => false,
    'motivoJustificacion' => null,
    'esDonacion' => false,
    'advertencias' => [],
])

@php
    $estadoValor = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen
        ? $estado->value
        : (string) $estado;

    $motivosDisponibles = ['Nombre no presente en el catálogo GBIF', 'Nombre verificado por el investigador'];

    $gridClasses = 'grid grid-cols-1 md:grid-cols-[1fr_1.5fr] gap-3 md:gap-3.5 items-start md:items-center px-4 py-4 md:py-3.5';

    $bgFila = match(true) {
        $esDonacion => '',
        ($estadoValor === 'Validado Técnicamente' || $estadoValor === 'Corregido por Sugerencia') && (bool) $especieCorregida => 'bg-success/5',
        $estadoValor === 'Validado Técnicamente' => 'bg-success/5',
        $estadoValor === 'Pendiente' && (bool) $especieSugerida => 'bg-warning/5',
        $estadoValor === 'Pendiente' && $noCatalogado => 'bg-error/5',
        $estadoValor === 'No Verificado' => 'bg-info/5',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => "border-b border-border last:border-b-0 {$bgFila}"]) }}>

    {{-- Donacion: todas las filas validadas automaticamente --}}
    @if($esDonacion)
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-blue-navy/5 border-blue-navy/20 text-blue-navy">
                    <flux:icon name="check" class="size-3" />
                    Validada Técnicamente
                </span>
                <span class="text-xs text-text-secondary/70">Identificación original conservada</span>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Validado Técnicamente (catalogado correctamente) --}}
    @elseif($estadoValor === 'Validado Técnicamente' && !$especieCorregida)
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-success/10 border-success/30 text-success">
                    <flux:icon name="check" class="size-3" />
                    Validado Técnicamente
                </span>
                <span class="text-xs text-text-secondary/70">Coincide con el catálogo taxonómico de GBIF</span>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Corregido por Sugerencia (ya acepto la correccion) --}}
    @elseif(($estadoValor === 'Validado Técnicamente' || $estadoValor === 'Corregido por Sugerencia') && $especieCorregida)
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-bio-green font-medium">{{ $especieCorregida }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1 items-start">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-success/10 border-success/30 text-success">
                        <flux:icon name="check" class="size-3" />
                        Corregido por Sugerencia
                    </span>
                    <button
                        wire:click="deshacerSugerencia('{{ $registroId }}')"
                        wire:loading.attr="disabled"
                        wire:target="deshacerSugerencia('{{ $registroId }}')"
                        class="text-xs text-science-blue hover:underline"
                    >
                        <span wire:loading.remove wire:target="deshacerSugerencia('{{ $registroId }}')">Deshacer</span>
                        <span wire:loading wire:target="deshacerSugerencia('{{ $registroId }}')">Deshaciendo...</span>
                    </button>
                </div>
                <span class="text-xs text-text-secondary/70">Validado Técnicamente · catálogo de GBIF</span>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Pendiente con sugerencia tipografica disponible --}}
    {{-- TODO: Agregar botón "Mantener original" que descarte la sugerencia sin cambiar el nombre,
         derivando el registro a Validación Manual por Curaduría con motivo "Nombre verificado por
         el investigador". Cubre el caso donde la sugerencia del catálogo es incorrecta y el
         investigador confirma que su nombre original es el correcto. --}}
    @elseif($estadoValor === 'Pendiente' && $especieSugerida)
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-secondary line-through">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            @php
                // Lista de candidatos (mejor primero); cae al único sugerido si no hay lista.
                $sugerencias = ! empty($especiesSugeridas)
                    ? $especiesSugeridas
                    : ($especieSugerida !== null ? [$especieSugerida] : []);
            @endphp
            <div class="flex flex-col gap-2 items-start w-full">
                <div class="flex items-start gap-1.5 text-xs text-text-primary leading-snug">
                    <flux:icon name="sparkles" class="size-3.5 text-warning shrink-0 mt-0.5" />
                    <span>
                        Posible inconsistencia tipográfica —
                        @if(count($sugerencias) > 1)
                            elige el nombre correcto del catálogo:
                        @else
                            ¿quiso decir
                            <em class="font-serif font-semibold not-italic">{{ $especieSugerida }}</em>?
                        @endif
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($sugerencias as $sugerencia)
                        <flux:button
                            variant="primary"
                            size="sm"
                            icon="check"
                            wire:click="aceptarSugerencia('{{ $registroId }}', '{{ addslashes($sugerencia) }}')"
                            wire:loading.attr="disabled"
                        >
                            <span class="font-serif italic">{{ $sugerencia }}</span>
                        </flux:button>
                    @endforeach
                </div>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Pendiente: no catalogado, sin justificacion --}}
    @elseif($estadoValor === 'Pendiente' && $noCatalogado)
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-2.5 items-start w-full">
                <div class="flex items-start gap-1.5 text-xs text-text-primary leading-snug">
                    <flux:icon name="exclamation-triangle" class="size-3.5 text-error shrink-0 mt-0.5" />
                    <span>
                        Este nombre no está en <strong>GBIF</strong>. Indica un motivo, deja un comentario para
                        curaduría, o ambos — con cualquiera de los dos podrás continuar.
                    </span>
                </div>
                <flux:select wire:model="motivosJustificacion.{{ $registroId }}" class="w-full max-w-xs">
                    <flux:select.option value="">Motivo (opcional)…</flux:select.option>
                    @foreach($motivosDisponibles as $motivo)
                        <flux:select.option value="{{ $motivo }}">{{ $motivo }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea
                    wire:model.blur="comentariosJustificacion.{{ $registroId }}"
                    rows="2"
                    class="w-full max-w-md"
                    placeholder="Comentario para el curador (opcional): explica por qué este nombre no figura en GBIF…"
                />
                <flux:button
                    variant="primary"
                    icon="check"
                    wire:click="justificar('{{ $registroId }}')"
                    wire:loading.attr="disabled"
                    wire:target="justificar('{{ $registroId }}')"
                    class="w-full sm:w-auto"
                >
                    Justificar
                </flux:button>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Validacion Manual por Curaduria (justificado) --}}
    @elseif($estadoValor === 'Validación Manual por Curaduría')
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-2.5 items-start w-full">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-warning/10 border-warning/30 text-warning">
                    <flux:icon name="exclamation-triangle" class="size-3" />
                    Validación Manual por Curaduría
                </span>
                <flux:select wire:model="motivosJustificacion.{{ $registroId }}" class="w-full max-w-xs">
                    @foreach($motivosDisponibles as $motivo)
                        <flux:select.option value="{{ $motivo }}">{{ $motivo }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea
                    wire:model.blur="comentariosJustificacion.{{ $registroId }}"
                    rows="2"
                    class="w-full max-w-md"
                    placeholder="Comentario para el curador (opcional)…"
                />
                <flux:button
                    variant="ghost"
                    icon="check"
                    wire:click="actualizarJustificacion('{{ $registroId }}')"
                    wire:loading.attr="disabled"
                    wire:target="actualizarJustificacion('{{ $registroId }}')"
                    class="w-full sm:w-auto"
                >
                    Actualizar justificación
                </flux:button>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- No Verificado: GBIF no respondió, no bloquea al usuario --}}
    @elseif($estadoValor === 'No Verificado')
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-info/10 border-info/30 text-info">
                    <flux:icon name="exclamation-circle" class="size-3" />
                    No Verificado
                </span>
                <span class="text-xs text-text-secondary/70">El servicio de validación taxonómica no respondió</span>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>

    {{-- Fallback: Pendiente genérico --}}
    @else
        <div class="{{ $gridClasses }}">
            <div class="flex flex-col gap-0.5">
                <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
                @if($catalogoId)
                    <span class="font-mono text-xs text-text-secondary/70">{{ $catalogoId }}</span>
                @endif
            </div>
            <div class="flex flex-col gap-1 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border border-border bg-bg-main text-text-secondary">
                    Pendiente
                </span>
                @foreach($advertencias as $adv)
                    <span class="inline-flex items-center gap-1 rounded border border-warning/40 bg-warning/5 px-2 py-0.5 text-xs text-warning">
                        <flux:icon name="exclamation-triangle" variant="outline" class="size-3 shrink-0" />
                        {{ $adv['campo'] }}: {{ $adv['mensaje'] }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

</div>
