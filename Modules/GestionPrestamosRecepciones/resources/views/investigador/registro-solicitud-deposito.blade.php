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
    <div wire:loading.delay.long wire:target="generarPdfSolicitud,continuarDesdeSolicitud,avanzarPaso1,guardarOrigenDesdeFormulario,guardarPasoDos,guardarPasoTres,guardarPasoCuatro,guardarPasoCinco,enviarSolicitud" class="hub-processing-overlay" style="display: none" role="status" aria-live="polite">
        <div x-show="!firmaOcupada" class="hub-processing-card"><span class="hub-processing-spinner" aria-hidden="true"></span><span>Procesando…</span></div>
    </div>
    <div x-show="firmaOcupada" x-cloak class="hub-processing-overlay" role="status" aria-live="polite" tabindex="-1">
        <div class="hub-processing-card"><span class="hub-processing-spinner" aria-hidden="true"></span><span>Procesando…</span></div>
    </div>

    {{-- Corrección de un rechazo subsanable: recordatorio de las observaciones --}}
    <flux:modal name="confirmar-contenido-incierto" class="w-full max-w-lg" :dismissible="false" :closable="false" aria-labelledby="titulo-contenido-incierto">
        <div class="space-y-5">
            <flux:heading size="lg" level="2" id="titulo-contenido-incierto">No se pudo identificar el contenido</flux:heading>
            <flux:text>{{ $confirmacionContenidoPendiente['motivo'] ?? '' }}</flux:text>
            <p class="text-sm text-text-primary">¿Deseas subirlo de todas formas?</p>

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
                        <p class="hub-request-meta__label text-[10px] font-semibold uppercase tracking-[0.12em]">Solicitud N.º</p>
                        <p class="hub-request-meta__number mt-0.5 font-mono text-xs font-semibold" title="{{ $numeroSolicitud }}">{{ preg_match('/-(\d+)$/', $numeroSolicitud, $partesNumero) ? $partesNumero[1] : $numeroSolicitud }}</p>
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
                    ['label' => 'Subir datos', 'sub' => 'Excel · Darwin Core'],
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
        <div x-data="{show: false, timer: null, abrir() {clearTimeout(this.timer); this.show = true; this.timer = setTimeout(() => this.cerrar(), 3500);}, cerrar() {this.show = false; clearTimeout(this.timer);}, destroy() {clearTimeout(this.timer);}}"
            x-on:hub-firma-completada.window="abrir()" x-on:keydown.escape.window="if(show) cerrar()"
            x-show="show" x-cloak x-transition.opacity
            class="hub-signature-success-overlay" data-hub-signature-success>
            <section class="hub-signature-success-card" role="status" aria-live="polite" aria-atomic="true" aria-labelledby="firma-completada-titulo" aria-describedby="firma-completada-detalle">
                <div class="hub-signature-success-emblem" aria-hidden="true"><span><flux:icon name="check" class="size-7" /></span></div>
                <h2 id="firma-completada-titulo">Documento firmado</h2>
                <p id="firma-completada-detalle">Tu PDF está firmado y validado. El guardado seguro continúa automáticamente.</p>
                <button type="button" x-on:click="cerrar()">Cerrar</button>
            </section>
        </div>
    @endteleport
</div>
