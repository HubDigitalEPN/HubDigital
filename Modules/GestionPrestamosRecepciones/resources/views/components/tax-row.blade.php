@props([
    'registroId',
    'catalogoId' => null,
    'especieIngresada',
    'estado',
    'especieSugerida' => null,
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

    $motivosDisponibles = ['Es una especie nueva', 'No listada en catálogo'];

    $gridClasses = 'grid grid-cols-1 md:grid-cols-[140px_1.1fr_1.6fr] gap-3 md:gap-3.5 items-start md:items-center px-4 py-4 md:py-3.5';

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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
            <div class="flex flex-col gap-1 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-success/10 border-success/30 text-success">
                    <flux:icon name="check" class="size-3" />
                    Validado Técnicamente
                </span>
                <span class="text-xs text-text-secondary/70">Coincide con el catálogo de referencia</span>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-bio-green font-medium">{{ $especieCorregida }}</span>
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
                <span class="text-xs text-text-secondary/70">Validado Técnicamente · catálogo de referencia</span>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-secondary line-through">{{ $especieIngresada }}</span>
            <div class="flex flex-col gap-2 items-start w-full">
                <div class="flex items-start gap-1.5 text-xs text-text-primary leading-snug">
                    <flux:icon name="sparkles" class="size-3.5 text-warning shrink-0 mt-0.5" />
                    <span>
                        Posible inconsistencia tipográfica — ¿quiso decir
                        <em class="font-serif font-semibold not-italic">{{ $especieSugerida }}</em>?
                    </span>
                </div>
                <flux:button
                    variant="primary"
                    size="sm"
                    icon="check"
                    wire:click="aceptarSugerencia('{{ $registroId }}', '{{ addslashes($especieSugerida) }}')"
                    wire:loading.attr="disabled"
                >
                    Aceptar sugerencia
                </flux:button>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
            <div class="flex flex-col gap-2 items-start w-full">
                <div class="flex items-start gap-1.5 text-xs text-text-primary leading-snug">
                    <flux:icon name="exclamation-triangle" class="size-3.5 text-error shrink-0 mt-0.5" />
                    <span>Especie no registrada en el catálogo. Justifica el hallazgo para continuar.</span>
                </div>
                <flux:select wire:change="justificarHallazgo('{{ $registroId }}', $event.target.value)" class="max-w-xs">
                    <flux:select.option value="">Selecciona un motivo de justificación...</flux:select.option>
                    @foreach($motivosDisponibles as $motivo)
                        <flux:select.option value="{{ $motivo }}">{{ $motivo }}</flux:select.option>
                    @endforeach
                </flux:select>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
            <div class="flex flex-col gap-1.5 items-start">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border bg-warning/10 border-warning/30 text-warning">
                    <flux:icon name="exclamation-triangle" class="size-3" />
                    Validación Manual por Curaduría
                </span>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-text-secondary/70">Motivo:</span>
                    <flux:select wire:change="cambiarJustificacion('{{ $registroId }}', $event.target.value)" class="max-w-xs">
                        @foreach($motivosDisponibles as $motivo)
                            <flux:select.option value="{{ $motivo }}" :selected="$motivo === $motivoJustificacion">{{ $motivo }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
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
            <span class="font-mono text-xs text-text-secondary"><span class="md:hidden font-sans font-semibold uppercase tracking-wide text-[10px]">Catálogo: </span>{{ $catalogoId }}</span>
            <span class="text-sm font-serif italic text-text-primary">{{ $especieIngresada }}</span>
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
