@php
    use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;
    use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoObservacionRecepcion;

    $enVerificacion = $recepcion->estadoRecepcion === 'En Verificación';
    $suspendida = $recepcion->estadoRecepcion === 'Recepción Suspendida';
    $verificado = in_array($recepcion->estadoRecepcion, ['Verificado Físicamente', 'Verificado con Observaciones'], true);
@endphp

<div class="p-4 sm:p-6 space-y-5"
    x-data
    @toast.window="$flux.toast($event.detail.message)"
    @domain-error.window="$flux.toast({ text: $event.detail.message, variant: 'danger' })">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.depositos') }}">
            Recepciones
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $recepcion->numeroSolicitud }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Recepción física</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display">Recepción física del lote</flux:heading>
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="font-mono text-xs text-text-secondary">{{ $recepcion->numeroSolicitud }}</p>
                <flux:badge color="zinc" size="sm">{{ $recepcion->tipoTramite }}</flux:badge>
                @if($recepcion->estadoRecepcion)
                    <x-gestionprestamosrecepciones::recepcion-status-badge :estado="$recepcion->estadoRecepcion" />
                @endif
            </div>
        </div>
    </div>

    {{-- Tiles de resumen --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 auto-rows-fr">
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">Código de lote (QR)</p>
            <p class="mt-1 font-mono text-base font-semibold text-blue-navy tracking-wide">{{ $recepcion->codigoQR ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">Investigador</p>
            <p class="mt-1 text-sm font-medium text-text-primary">{{ $nombreInvestigador }}</p>
        </div>
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">N.º de lotes</p>
            <p class="mt-1 text-sm font-medium text-text-primary">{{ $recepcion->nroLotes ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">N.º de individuos</p>
            <p class="mt-1 text-sm font-medium text-text-primary">{{ $recepcion->nroIndividuos ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">Grupo animal</p>
            <p class="mt-1 text-sm font-medium text-text-primary">{{ $recepcion->grupoAnimal ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
            <p class="text-xs text-text-secondary">Localidad</p>
            <p class="mt-1 text-sm font-medium text-text-primary">{{ $recepcion->localidad ?? '—' }}</p>
        </div>
    </div>

    {{-- Estado terminal: verificado (conforme o con observaciones) --}}
    @if($verificado)
        <flux:callout variant="success" icon="check-badge">
            <flux:callout.heading>Recepción finalizada</flux:callout.heading>
            <flux:callout.text>
                Los especímenes ingresan a la colección en estado
                <span class="font-semibold">{{ $recepcion->estadoColeccion }}</span>.
                @if($recepcion->actaEmitida) Se emitió el Acta Digital de Recepción. @endif
            </flux:callout.text>
            @if($recepcion->observaciones !== [])
                <ul class="mt-2 space-y-1 text-sm text-text-primary">
                    @foreach($recepcion->observaciones as $observacion)
                        <li class="flex items-start gap-2">
                            <flux:icon name="exclamation-triangle" class="size-4 text-warning shrink-0 mt-0.5" />
                            {{ $observacion }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:callout>
    @endif

    {{-- Estado terminal: suspendida --}}
    @if($suspendida)
        <flux:callout variant="warning" icon="arrow-path">
            <flux:callout.heading>Recepción suspendida</flux:callout.heading>
            <flux:callout.text>
                Anomalía subsanable: <span class="font-semibold">{{ $recepcion->motivoFallo }}</span>.
                Orden de acción correctiva: <span class="font-semibold">{{ $recepcion->accionCorrectiva }}</span>.
                El Código QR permanece vigente para reintentar la recepción del mismo lote.
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Lista de verificación + decisión (solo mientras está en verificación) --}}
    @if($enVerificacion)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                    <flux:icon name="clipboard-document-check" class="size-3.5" />
                </div>
                <flux:heading size="base" level="2" class="font-display">Lista de verificación de recepción</flux:heading>
            </div>

            {{-- Desktop: tabla --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-blue-navy">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white">Ítem de verificación</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Resultado conforme</th>
                            <th class="px-4 py-3 text-right font-medium text-white">Conformidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($items as $indice => $definicion)
                            <tr class="hover:bg-bg-main transition-colors">
                                <td class="px-4 py-3 font-medium text-text-primary">{{ $definicion['item'] }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $definicion['conforme'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end">
                                        <flux:switch wire:model="conforme.{{ $indice }}" label="Conforme" align="right" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Móvil: tarjetas --}}
            <div class="md:hidden divide-y divide-border">
                @foreach($items as $indice => $definicion)
                    <div class="p-4 space-y-3">
                        <dl class="space-y-2 text-sm">
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Ítem">
                                {{ $definicion['item'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Resultado conforme">
                                {{ $definicion['conforme'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        </dl>
                        <flux:switch wire:model="conforme.{{ $indice }}" label="Conforme" />
                    </div>
                @endforeach
            </div>

        </div>

        {{-- Zona de decisión --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
            <flux:heading size="base" level="2" class="font-display">Decisión de recepción</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Al aprobar, si todos los ítems están conformes la recepción se verifica por completo; si algún
                ítem no lo está, se registrará con observaciones. Usa "Suspender y devolver" cuando la anomalía es
                subsanable y el lote debe regresar al investigador.
            </flux:text>

            <div class="flex flex-col gap-2 pt-4 sm:flex-row sm:justify-end">
                <flux:button variant="danger" icon="arrow-uturn-left" class="w-full sm:w-auto"
                    wire:click="$set('showRechazoModal', true)">
                    Suspender y devolver
                </flux:button>
                <flux:button variant="primary" icon="check-circle" class="w-full sm:w-auto"
                    wire:click="aprobar" wire:loading.attr="disabled" wire:target="aprobar">
                    <flux:icon wire:loading wire:target="aprobar" name="arrow-path" class="animate-spin" />
                    Aprobar recepción
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Modal: suspender por anomalía subsanable --}}
    <flux:modal wire:model="showRechazoModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Suspender la recepción</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica el fallo de integridad subsanable. Se emitirá la orden de acción correctiva para el
                investigador y el Código QR seguirá vigente para reintentar la recepción.
            </flux:text>
            <flux:field>
                <flux:label>Fallo de integridad <span class="text-error">*</span></flux:label>
                <flux:select wire:model="motivoFallo" placeholder="Selecciona un fallo...">
                    @foreach(MotivoFalloRecepcion::cases() as $motivo)
                        <flux:select.option value="{{ $motivo->value }}">{{ $motivo->value }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="motivoFallo" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showRechazoModal', false)">Cancelar</flux:button>
                <flux:button variant="danger" icon="arrow-uturn-left" wire:click="rechazar"
                    wire:loading.attr="disabled" wire:target="rechazar">
                    <flux:icon wire:loading wire:target="rechazar" name="arrow-path" class="animate-spin" />
                    Suspender recepción
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: aceptar con observaciones --}}
    <flux:modal wire:model="showObservacionModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Aceptar con observaciones</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Indica la anomalía que no puede devolverse al investigador. Las observaciones quedarán registradas
                en el Acta Digital de Recepción y los especímenes ingresarán a la colección.
            </flux:text>
            <flux:field>
                <flux:label>Tipo de observación <span class="text-error">*</span></flux:label>
                <flux:select wire:model="tipoObservacion" placeholder="Selecciona una observación...">
                    @foreach(TipoObservacionRecepcion::cases() as $observacion)
                        <flux:select.option value="{{ $observacion->value }}">{{ $observacion->value }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tipoObservacion" />
            </flux:field>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showObservacionModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" icon="check-circle" wire:click="aceptarConObservaciones"
                    wire:loading.attr="disabled" wire:target="aceptarConObservaciones">
                    <flux:icon wire:loading wire:target="aceptarConObservaciones" name="arrow-path" class="animate-spin" />
                    Aceptar con observaciones
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
