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
                Mis solicitudes
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Nueva solicitud de depósito</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- Borrador restaurado --}}
        @if($borradorRestaurado && $paso < 6)
            <div class="rounded-lg border border-science-blue/30 bg-science-blue/5 p-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon name="bookmark" class="size-5 text-science-blue shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-text-primary">Borrador pendiente recuperado</p>
                        <p class="text-xs text-text-secondary mt-0.5">Puedes continuar donde lo dejaste o descartar para empezar de nuevo.</p>
                    </div>
                </div>
                <flux:modal.trigger name="confirmar-descartar-borrador">
                    <flux:button
                        variant="outline"
                        size="sm"
                        icon="trash"
                        class="shrink-0"
                    >
                        Descartar
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="confirmar-descartar-borrador" class="max-w-sm">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">Descartar borrador</flux:heading>
                        <flux:text class="text-text-secondary mt-1">
                            Se eliminará el borrador y todos los documentos cargados. Esta acción no se puede deshacer.
                        </flux:text>
                    </div>
                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancelar</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="descartarBorrador" icon="trash">
                            Descartar
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <flux:heading size="xl" level="1" class="font-display">Nueva solicitud de depósito</flux:heading>
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
                    @if($paso > 1 && !$extraccionProcesando)
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
                        @if(!$intervencionCuratoriaActiva && !$extraccionProcesando)
                            <flux:button
                                variant="primary"
                                icon-trailing="arrow-right"
                                wire:click="guardarPasoTres"
                            >
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

    {{-- Toast teleportado al body — fuera del DOM de Livewire, sin morph --}}
    @teleport('body')
        <div
            x-data="{
                show: false,
                message: '',
                variant: 'warning',
                timer: null,
                showToast(data) {
                    this.message = data.message;
                    this.variant = data.variant || 'warning';
                    this.show = true;
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.show = false, 5000);
                }
            }"
            x-on:show-toast.window="showToast($event.detail)"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-full"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-full"
            x-bind:class="variant === 'error'
                ? 'border-error/50 bg-error/5 ring-1 ring-error/20'
                : 'border-warning/50 bg-warning/5 ring-1 ring-warning/20'"
            class="rounded-xl border bg-surface px-5 py-4 flex items-start gap-3"
            style="display: none; position: fixed; top: 1.25rem; right: 1.5rem; z-index: 9999; width: 22rem; max-width: calc(100vw - 3rem); box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 4px 16px rgba(0,0,0,0.1);"
        >
            <div x-show="variant === 'error'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-full bg-error/10">
                    <svg class="size-5 text-error" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div x-show="variant !== 'error'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-8 rounded-full bg-warning/10">
                    <svg class="size-5 text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p x-show="variant === 'error'" class="text-sm font-bold text-error">Error de validación</p>
                <p x-show="variant !== 'error'" class="text-sm font-bold text-warning">Atención</p>
                <p class="text-sm text-text-primary mt-1 leading-snug font-normal" x-text="message"></p>
            </div>
            <button x-on:click="show = false" class="flex-shrink-0 p-1 rounded-md text-text-secondary hover:text-text-primary hover:bg-bg-main transition-colors">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endteleport
</div>
