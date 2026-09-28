<div
    class="hub-transactional-ui hub-depositos-ui hub-workspace space-y-3 pb-8"
    x-data="{
        domainError: null,
        tipoTramite: $wire.entangle('tipoTramite'),
        declaracionAceptada: $wire.entangle('declaracionAceptada'),
        matrizCargada: $wire.entangle('matrizCargada'),
        solicitudFirmada: $wire.entangle('solicitudFirmada'),
    }"
    x-on:domain-error.window="domainError = $event.detail.message; setTimeout(() => domainError = null, 6000)"
>
    {{-- Domain error global --}}
    <div x-show="domainError" x-transition class="flex items-start gap-3 rounded-lg border border-error bg-error/5 p-4" role="alert" aria-live="assertive">
        <flux:icon name="x-circle" class="size-5 text-error shrink-0 mt-0.5" />
        <p class="text-sm text-error font-medium" x-text="domainError"></p>
    </div>

    {{-- Corrección de un rechazo subsanable: recordatorio de las observaciones --}}
    @if($modoCorreccion && $paso < 9)
        <div class="rounded-lg border border-warning/40 bg-warning/5 p-3 flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="size-5 text-warning shrink-0 mt-0.5" />
            <div class="min-w-0">
                <p class="text-sm font-medium text-text-primary">Estás corrigiendo una solicitud devuelta por la curaduría</p>
                @if($comentarioCurador)
                    <p class="text-sm text-text-secondary mt-0.5"><span class="font-medium text-text-primary">Observaciones:</span> {{ $comentarioCurador }}</p>
                @endif
                <p class="text-xs text-text-secondary mt-1">Corrige lo indicado y vuelve a enviar la solicitud para revisión.</p>
            </div>
        </div>
    @endif

    @if($paso < 9)
        {{-- Borrador restaurado --}}
        @if($borradorRestaurado && ! $modoCorreccion && $paso < 8)
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blue-navy/10 pb-3">
            <div class="min-w-0 max-w-3xl">
                <flux:heading size="lg" level="1" class="font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('general.titulo') }}</flux:heading>
                <flux:text class="mt-1 text-xs leading-5 text-text-secondary">
                    {{ \App\Support\WizardCopy::text('general.intro') }}
                </flux:text>
            </div>
            <div class="flex items-center gap-3">
                @if($numeroSolicitud)
                    <div class="border-l-2 border-bio-green pl-3 text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-text-secondary">Expediente</p>
                        <p class="mt-0.5 font-mono text-xs font-semibold text-blue-navy">{{ $numeroSolicitud }}</p>
                    </div>
                @endif
                <x-gestionprestamosrecepciones::deposito-status-badge :estado="$modoCorreccion ? 'Requiere Corrección' : 'En Borrador'" />
                @if($borradorRestaurado && ! $modoCorreccion && $paso < 8)
                    <flux:modal.trigger name="confirmar-descartar-borrador">
                        <flux:button variant="ghost" size="sm" icon="document-minus">Eliminar borrador</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        {{-- Stepper --}}
        <div aria-label="Progreso de la solicitud" class="hub-wizard-progress overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
            <div class="flex items-center justify-between px-3 pt-2 sm:hidden">
                @php
                    $pasoVisible = $paso <= 4 ? $paso : $paso - 1;
                    $etiquetaPaso = ['Trámite', 'Origen', 'Archivos y firmas', 'Datos', 'Identidad', 'Detalle', 'Envío'][$pasoVisible - 1] ?? '';
                @endphp
                <p class="text-sm font-semibold text-blue-navy">Paso {{ $pasoVisible }} de 7 · {{ $etiquetaPaso }}</p>
            </div>
            <x-gestionprestamosrecepciones::wizard-stepper
                :pasos="[
                    ['label' => 'Trámite',    'sub' => 'Modalidad'],
                    ['label' => 'Origen',     'sub' => 'Procedencia'],
                    ['label' => 'Archivos y firmas', 'sub' => 'Validación'],
                    ['label' => 'Datos', 'sub' => 'Formulario'],
                    ['label' => 'Identidad',  'sub' => 'Solicitante'],
                    ['label' => 'Detalle',    'sub' => 'Taxonomía'],
                    ['label' => 'Envío',      'sub' => 'Firma'],
                ]"
                :pasoActual="$paso <= 4 ? $paso : $paso - 1"
                :pasosCompletados="array_values(array_unique(array_map(fn ($numero) => $numero <= 4 ? $numero : $numero - 1, array_filter($pasosCompletados, fn ($numero) => $numero !== 5))))"
            />
        </div>
    @endif

    {{-- Superficie principal de trabajo --}}
    <div class="overflow-hidden rounded-xl border border-blue-navy/15 bg-surface shadow-sm">
        <div>

            {{-- Step content --}}
            <div class="min-w-0 p-4 sm:p-5 lg:p-6" wire:key="paso-{{ $paso }}">
                @if($paso === 1)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-tramite')
                @elseif($paso === 2)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-origen')
                @elseif($paso === 3)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-documentos')
                @elseif($paso === 4)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-datos')
                @elseif($paso === 6)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-identidad')
                @elseif($paso === 7)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-matriz')
                @elseif($paso === 8 || $paso === 9)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-envio')
                @endif

            </div>
        </div>

        {{-- Footer navigation --}}
        @if($paso < 9)
            <div class="flex items-center justify-between gap-3 border-t border-blue-navy/10 bg-[#F8FAFC] px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
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
                            x-bind:disabled="!tipoTramite"
                        >
                            <flux:icon wire:loading wire:target="avanzarPaso1" name="arrow-path" class="animate-spin size-4 mr-1" />
                            {{ \App\Support\WizardCopy::text('tramite.accion') }}
                        </flux:button>
                    @elseif($paso === 2)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            x-on:click.prevent="$wire.guardarOrigenDesdeFormulario(document.getElementById('provincia-recoleccion')?.value ?? '', document.getElementById('localidad-recoleccion')?.value ?? '', document.querySelector('[data-situacion][aria-pressed=true]')?.dataset.situacion ?? '')"
                            wire:loading.attr="disabled"
                            wire:target="guardarOrigenDesdeFormulario"
                        >
                            <flux:icon wire:loading wire:target="guardarOrigenDesdeFormulario" name="arrow-path" class="animate-spin size-4 mr-1" />
                            {{ \App\Support\WizardCopy::text('origen.accion') }}
                        </flux:button>
                    @elseif($paso === 3)
                        @if(!$intervencionCuratoriaActiva && !$extraccionProcesando)
                            @php
                                $firmasListas = collect($documentosRequeridos)->every(
                                    fn ($documento) => ($validacionArchivos[$documento] ?? null) === 'valido'
                                        && in_array($firmasElectronicas[$documento] ?? null, ['firmado', 'firmado_sin_revocacion'], true)
                                );
                            @endphp
                            <flux:button
                                variant="primary"
                                icon-trailing="arrow-right"
                                wire:click="guardarPasoTres"
                                :disabled="!$firmasListas"
                            >
                                {{ $analisisDocumentalCompletado ? 'Continuar a datos' : (empty($documentosRequeridos) ? \App\Support\WizardCopy::text('documentos.accion_sin_archivos') : \App\Support\WizardCopy::text('documentos.accion_con_archivos')) }}
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
                            {{ \App\Support\WizardCopy::text('datos.accion') }}
                        </flux:button>
                    @elseif($paso === 6)
                        <flux:button variant="primary" icon-trailing="arrow-right" wire:click="guardarPasoIdentidad" wire:loading.attr="disabled" wire:target="guardarPasoIdentidad">
                            Continuar al detalle
                        </flux:button>
                    @elseif($paso === 7)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="guardarPasoCinco"
                            wire:loading.attr="disabled"
                            wire:target="guardarPasoCinco"
                        >
                            <flux:icon wire:loading wire:target="guardarPasoCinco" name="arrow-path" class="animate-spin size-4 mr-1" />
                            {{ \App\Support\WizardCopy::text('detalle.accion') }}
                        </flux:button>
                    @elseif($paso === 8)
                        <flux:button
                            variant="primary"
                            icon-trailing="paper-airplane"
                            wire:click="enviarSolicitud"
                            wire:loading.attr="disabled"
                            wire:target="enviarSolicitud"
                            x-bind:disabled="!declaracionAceptada || !solicitudFirmada"
                        >
                            <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin size-4 mr-1" />
                            {{ \App\Support\WizardCopy::text('envio.accion') }}
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
                showToast(data) {
                    this.message = data.message;
                    this.variant = data.variant || 'warning';
                    this.show = true;
                }
            }"
            x-on:show-toast.window="showToast($event.detail)"
            x-on:close-toast.window="show = false"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-full"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-full"
            x-bind:class="variant === 'error'
                ? 'border-error/50 bg-error/5 ring-1 ring-error/20'
                : variant === 'success'
                    ? 'border-science-blue/50 bg-science-blue/5 ring-1 ring-science-blue/20'
                    : 'border-warning/50 bg-warning/5 ring-1 ring-warning/20'"
            class="rounded-xl border bg-surface px-3 py-2.5 flex items-start gap-2.5"
            style="display: none; position: fixed; top: 1rem; right: 1rem; z-index: 9999; width: 19rem; max-width: calc(100dvi - 2rem); box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 4px 16px rgba(0,0,0,0.1);"
        >
            <div x-show="variant === 'error'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-6 rounded-full bg-error/10">
                    <svg class="size-4 text-error" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div x-show="variant === 'success'" class="flex-shrink-0 mt-0.5">
                <flux:icon name="check-circle" class="size-6 text-science-blue" />
            </div>
            <div x-show="variant !== 'error' && variant !== 'success'" class="flex-shrink-0 mt-0.5">
                <div class="flex items-center justify-center size-6 rounded-full bg-warning/10">
                    <svg class="size-4 text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p x-show="variant === 'error'" class="text-xs font-bold text-error">Error de validación</p>
                <p x-show="variant === 'success'" class="text-xs font-bold text-science-blue">Datos completos</p>
                <p x-show="variant !== 'error' && variant !== 'success'" class="text-xs font-bold text-warning">Atención</p>
                <p class="text-xs text-text-primary mt-0.5 leading-snug font-normal" x-text="message"></p>
            </div>
            <button type="button" aria-label="Cerrar aviso" x-on:click="show = false" class="flex-shrink-0 p-1 rounded-md text-text-secondary hover:text-text-primary hover:bg-bg-main transition-colors">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endteleport
</div>
