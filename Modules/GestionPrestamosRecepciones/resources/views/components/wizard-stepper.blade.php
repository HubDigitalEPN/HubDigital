@props([
    'pasos' => [],
    'pasoActual' => 1,
    'pasosCompletados' => [],
])

<div class="hub-wizard-stepper-scroll">
<ol class="hub-wizard-steps" style="--wizard-step-count: {{ count($pasos) }}" aria-label="Pasos de la solicitud">
    @foreach($pasos as $index => $paso)
        @php
            $numero = $index + 1;
            $esActual = $numero === $pasoActual;
            $esCompletado = in_array($numero, $pasosCompletados);
            $estado = $esCompletado ? 'Completado' : ($esActual ? 'En curso' : 'Pendiente');
        @endphp

        <li class="hub-wizard-step {{ $esActual ? 'is-current' : ($esCompletado ? '' : 'is-pending') }} {{ $esCompletado ? 'is-complete' : '' }}"
            aria-label="Paso {{ $numero }}: {{ $paso['label'] }}. {{ $estado }}" @if($esActual) aria-current="step" @endif>
            <span class="hub-wizard-step__marker" aria-hidden="true">
                @if($esCompletado)
                    <flux:icon name="check" class="size-4" />
                @elseif($esActual)
                    <flux:icon name="lock-closed" class="size-4" />
                @else
                    <span class="hub-wizard-step__dots">@for($dot = 0; $dot < 9; $dot++)<i></i>@endfor</span>
                @endif
            </span>
            <span class="hub-wizard-step__label" title="{{ $paso['label'] }}">({{ $numero }}) {{ $paso['label'] }}</span>
        </li>

    @endforeach
</ol>
</div>
