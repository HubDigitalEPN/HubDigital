<div
    class="space-y-6"
    x-data="{
        domainError: null,
        tipoTramite: $wire.entangle('tipoTramite'),
        origenRecoleccion: $wire.entangle('origenRecoleccion'),
        situacionRegulatoria: $wire.entangle('situacionRegulatoria'),
        declaracionAceptada: $wire.entangle('declaracionAceptada'),
        limiteAlcanzado: $wire.entangle('limiteAlcanzado'),
    }"
    x-on:domain-error.window="domainError = $event.detail.message; setTimeout(() => domainError = null, 6000)"
>
    {{-- Domain error global --}}
    <div x-show="domainError" x-transition class="rounded-lg border border-error bg-error/5 p-4 flex items-start gap-3">
        <flux:icon name="x-circle" class="size-5 text-error shrink-0 mt-0.5" />
        <p class="text-sm text-error font-medium" x-text="domainError"></p>
    </div>

    @if($paso < 6)
        {{-- Breadcrumbs --}}
        <flux:breadcrumbs>
            <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
                Mis Solicitudes
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Nueva Solicitud de Depósito</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <flux:heading size="xl" level="1">Nueva Solicitud de Depósito</flux:heading>
                <flux:text class="text-text-secondary mt-1">
                    Registra tu solicitud de depósito o donación de especímenes entomológicos.
                </flux:text>
            </div>
            @if($numeroSolicitud)
                <span class="font-mono text-xs bg-bg-main border border-border rounded-md px-2 py-1 text-text-secondary self-start">
                    {{ $numeroSolicitud }}
                </span>
            @endif
        </div>

        {{-- Stepper --}}
        <x-gestionprestamosrecepciones::wizard-stepper
            :pasos="[
                ['label' => 'Trámite',    'sub' => 'Tipo y límite anual'],
                ['label' => 'Origen',     'sub' => 'Procedencia y permisos'],
                ['label' => 'Documentos', 'sub' => 'Carga oficial'],
                ['label' => 'Datos',      'sub' => 'Resumen y validación'],
                ['label' => 'Envío',      'sub' => 'Confirmación'],
            ]"
            :pasoActual="$paso"
            :pasosCompletados="$pasosCompletados"
        />
    @endif

    {{-- Card wrapper --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">

        {{-- Step content --}}
        <div class="p-6">
            @if($paso === 1)
                @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-tramite')
            @elseif($paso === 2)
                @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-origen')
            @elseif($paso === 3)
                @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-documentos')
            @elseif($paso === 4)
                @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-datos')
            @elseif($paso === 5 || $paso === 6)
                @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-envio')
            @endif
        </div>

        {{-- Footer navigation --}}
        @if($paso < 6)
            <div class="px-6 py-4 border-t border-border bg-bg-main rounded-b-lg flex items-center justify-between gap-3">
                <div>
                    @if($paso > 1)
                        <flux:button variant="ghost" wire:click="retroceder" icon="arrow-left">
                            Atrás
                        </flux:button>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if($paso === 1)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="avanzarPaso1"
                            wire:loading.attr="disabled"
                            wire:target="avanzarPaso1"
                            x-bind:disabled="!tipoTramite || limiteAlcanzado"
                        >
                            <flux:icon wire:loading wire:target="avanzarPaso1" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Continuar
                        </flux:button>
                    @elseif($paso === 2)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoDos"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoDos"
                            x-bind:disabled="!origenRecoleccion || !situacionRegulatoria"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoDos" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Guardar y continuar
                        </flux:button>
                    @elseif($paso === 3)
                        @if(!$intervencionCuratoriaActiva)
                            <flux:button
                                variant="primary"
                                icon-trailing="arrow-right"
                                wire:click="guardarPasoTres"
                                wire:loading.attr="disabled"
                                wire:target="guardarPasoTres"
                            >
                                <flux:icon wire:loading wire:target="guardarPasoTres" name="arrow-path" class="animate-spin size-4 mr-1" />
                                Validar documentos
                            </flux:button>
                        @endif
                    @elseif($paso === 4)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoCuatro"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoCuatro"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoCuatro" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Revisar y enviar
                        </flux:button>
                    @elseif($paso === 5)
                        <flux:button
                            variant="primary"
                            icon-trailing="paper-airplane"
                            wire:click="enviarSolicitud"
                            wire:loading.attr="disabled"
                            wire:target="enviarSolicitud"
                            x-bind:disabled="!declaracionAceptada"
                        >
                            <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin size-4 mr-1" />
                            Enviar solicitud
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif

    </div>
</div>
