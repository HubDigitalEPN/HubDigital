<div
    class="hub-transactional-ui hub-depositos-ui hub-workspace space-y-3 pb-8"
    x-data="{
        firmaOcupada: false,
        tipoTramite: $wire.entangle('tipoTramite'),
        declaracionAceptada: $wire.entangle('declaracionAceptada'),
        matrizCargada: $wire.entangle('matrizCargada'),
        solicitudFirmada: $wire.entangle('solicitudFirmada'),
    }"
    x-on:hub-firma-ocupada.window="firmaOcupada = $event.detail.busy"
>
    <div wire:loading.delay.long wire:target.except="actualizarFirmas,verificarExtraccion,buscarLocalidadesOrigenLov,buscarLocalidadesDatoLov,enviarPreguntaAsistencia,sugerirPreguntaAsistencia,eliminarDocumento,_startUpload,_finishUpload,confirmarCargaContenidoIncierto,cancelarCargaContenidoIncierto" class="hub-processing-overlay" style="display: none" role="status" aria-live="polite">
        <div x-show="!firmaOcupada" class="hub-processing-card"><span class="hub-processing-spinner" aria-hidden="true"></span><span>Procesando…</span></div>
    </div>
    <div x-show="firmaOcupada" x-cloak x-trap.inert.noscroll="firmaOcupada" class="hub-processing-overlay" role="status" aria-live="polite" tabindex="-1">
        <div class="hub-processing-card"><span class="hub-processing-spinner" aria-hidden="true"></span><span>Procesando…</span></div>
    </div>

    {{-- Corrección de un rechazo subsanable: recordatorio de las observaciones --}}
    <flux:modal name="confirmar-contenido-incierto" class="w-full max-w-lg" :dismissible="false" :closable="false" aria-labelledby="titulo-contenido-incierto">
        <div class="space-y-5">
            <flux:heading size="lg" level="2" id="titulo-contenido-incierto">No se pudo identificar el contenido</flux:heading>
            <flux:text>{{ $confirmacionContenidoPendiente['motivo'] ?? '' }}</flux:text>
            <p class="text-sm text-text-primary">¿Deseas subirlo de todas formas?</p>
            <p class="text-sm text-text-secondary">Se comprobará su firma digital. Si es válida, podrás continuar y el curador revisará el contenido al recibir la solicitud.</p>
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button wire:click="cancelarCargaContenidoIncierto" wire:loading.attr="disabled">Elegir otro archivo</flux:button>
                <flux:button variant="primary" wire:click="confirmarCargaContenidoIncierto" wire:loading.attr="disabled">Subir de todas formas</flux:button>
            </div>
        </div>
    </flux:modal>

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
            <flux:modal name="confirmar-descartar-borrador" class="max-w-sm" aria-labelledby="titulo-descartar-borrador">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg" level="2" id="titulo-descartar-borrador">Descartar borrador</flux:heading>
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
                <flux:heading size="lg" level="1" class="hub-request-page-title font-display tracking-tight">{{ \App\Support\WizardCopy::text('general.titulo') }}</flux:heading>
            </div>
            <div class="hub-request-meta flex items-center gap-3">
                @if($numeroSolicitud)
                    <div class="border-l-2 border-bio-green pl-3 text-right">
                        <p class="hub-request-meta__label text-[10px] font-semibold uppercase tracking-[0.12em]">Expediente</p>
                        <p class="hub-request-meta__number mt-0.5 font-mono text-xs font-semibold">{{ $numeroSolicitud }}</p>
                    </div>
                @endif
                <x-gestionprestamosrecepciones::deposito-status-badge :estado="$modoCorreccion ? 'Requiere Corrección' : 'En Borrador'" />
                @if($borradorRestaurado && ! $modoCorreccion && $paso < 8)
                    <flux:modal.trigger name="confirmar-descartar-borrador">
                        <flux:button variant="ghost" size="sm" icon="trash" aria-label="Eliminar borrador" title="Eliminar borrador" class="cursor-pointer" />
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        {{-- Stepper --}}
        <div aria-label="Progreso de la solicitud" class="hub-wizard-progress overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
            <x-gestionprestamosrecepciones::wizard-stepper
                accion-navegacion="volverAPasoCompletado"
                :pasos="[
                    ['label' => 'Solicitud', 'sub' => 'Firma PDF'],
                    ['label' => 'Trámite',    'sub' => 'Modalidad'],
                    ['label' => 'Origen',     'sub' => 'Procedencia'],
                    ['label' => 'Archivos y firmas', 'sub' => 'Validación'],
                    ['label' => 'Datos', 'sub' => 'Formulario'],
                    ['label' => 'Detalle',    'sub' => 'Taxonomía'],
                    ['label' => 'Envío',      'sub' => 'Revisión'],
                ]"
                :pasoActual="$this->pasoVisible()"
                :pasosCompletados="$this->pasosCompletadosVisibles()"
            />
        </div>
    @endif

    {{-- Superficie principal de trabajo --}}
    <div class="overflow-hidden rounded-xl border border-blue-navy/15 bg-surface shadow-sm">
        <div>

            {{-- Step content --}}
            <div class="min-w-0 p-4 sm:p-5 lg:p-6" wire:key="paso-{{ $paso }}">
                @if($paso === 0)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-solicitud')
                @elseif($paso === 1)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-tramite')
                @elseif($paso === 2)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-origen')
                @elseif($paso === 3)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-documentos')
                @elseif($paso === 4)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-datos')
                @elseif($paso === 7)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-matriz')
                @elseif($paso === 8 || $paso === 9)
                    @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-envio')
                @endif

            </div>
        </div>

        {{-- Footer navigation --}}
        @if($paso > 0 && $paso < 9)
            <div class="flex items-center justify-between gap-3 border-t border-blue-navy/10 bg-[#F8FAFC] px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
                <div>
                    @if($paso > 0 && !$extraccionProcesando)
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
                            {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($tipoTramite === \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite::Donacion->value ? 2 : $paso) }}
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
                            {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($paso) }}
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
                                {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($paso) }}
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
                            {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($paso) }}
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
                            {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($paso) }}
                        </flux:button>
                    @elseif($paso === 8)
                        <flux:button
                            variant="primary"
                            icon-trailing="arrow-right"
                            wire:click="enviarSolicitud"
                            wire:loading.attr="disabled"
                            wire:target="enviarSolicitud"
                            x-bind:disabled="!declaracionAceptada || !solicitudFirmada"
                        >
                            <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin size-4 mr-1" />
                            {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente($paso) }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif

    </div>

    {{-- Toast teleportado al body — fuera del DOM de Livewire, sin morph --}}
    @teleport('body')
        <div x-data="{show: false, timer: null, abrir() {clearTimeout(this.timer); this.show = true; this.timer = setTimeout(() => this.cerrar(), 3500);}, cerrar() {this.show = false; clearTimeout(this.timer); this.$nextTick(() => document.querySelector('[data-oficio-signed-link]')?.focus());}, destroy() {clearTimeout(this.timer);}}"
            x-on:hub-firma-completada.window="abrir()" x-on:keydown.escape.window="if(show) cerrar()"
            x-show="show" x-cloak x-transition.opacity x-trap.inert.noscroll="show"
            class="hub-signature-success-overlay" data-hub-signature-success>
            <section class="hub-signature-success-card" role="alertdialog" aria-modal="true" aria-labelledby="firma-completada-titulo" aria-describedby="firma-completada-detalle">
                <div class="hub-signature-success-emblem" aria-hidden="true"><span><flux:icon name="check" class="size-7" /></span></div>
                <h2 id="firma-completada-titulo">Documento firmado</h2>
                <p id="firma-completada-detalle">Tu PDF está firmado y validado. El guardado seguro continúa automáticamente.</p>
                <button type="button" x-on:click="cerrar()">Cerrar</button>
            </section>
        </div>
    @endteleport
    @teleport('body')
        <div
            x-data="{
                show: false,
                message: '',
                variant: 'warning',
                timer: null,
                showToast(data) {
                    if (!['warning', 'error'].includes(data.variant || 'warning')) return;
                    clearTimeout(this.timer);
                    this.message = data.message;
                    this.variant = data.variant || 'warning';
                    this.show = true;
                    this.timer = setTimeout(() => this.show = false, 6000);
                },
                destroy() {
                    clearTimeout(this.timer);
                }
            }"
            x-on:show-toast.window="showToast($event.detail)"
            x-on:domain-error.window="showToast({message: $event.detail.message, variant: 'warning'})"
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
            data-hub-floating-notice role="alert" aria-live="assertive"
            style="display: none; position: fixed; top: var(--hub-floating-notice-top, 6rem); right: 1rem; z-index: 9999; width: 19rem; max-width: calc(100dvi - 2rem); box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 4px 16px rgba(0,0,0,0.1);"
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
