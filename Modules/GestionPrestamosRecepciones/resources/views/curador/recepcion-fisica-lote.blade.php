@php
    use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;

    $enVerificacion = $recepcion->estadoRecepcion === 'En Verificación';
    $suspendida = $recepcion->estadoRecepcion === 'Recepción Suspendida';
    $verificado = in_array($recepcion->estadoRecepcion, ['Verificado Físicamente', 'Verificado con Observaciones'], true);
@endphp

<div class="hub-transactional-ui hub-depositos-ui hub-workspace p-4 sm:p-6 space-y-5"
    x-data
    @toast.window="$flux.toast($event.detail.message)">

    <flux:breadcrumbs class="hub-form-breadcrumbs">
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.receptor.depositos') }}">
            Recepciones
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $recepcion->numeroSolicitud }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Recepción física</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Encabezado --}}
    <header class="hub-page-header">
        <div>
            <h1 class="mt-1 hub-page-title">Recepción física del lote</h1>
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="font-mono text-xs text-text-secondary">{{ $recepcion->numeroSolicitud }}</p>
                <flux:badge color="zinc" size="sm">{{ $recepcion->tipoTramite }}</flux:badge>
                @if($recepcion->estadoRecepcion)
                    <x-gestionprestamosrecepciones::recepcion-status-badge :estado="$recepcion->estadoRecepcion" />
                @endif
            </div>
        </div>
    </header>

    {{-- Resumen único: datos breves relacionados, sin una tarjeta sobredimensionada por valor. --}}
    <section class="hub-panel overflow-hidden" aria-labelledby="resumen-entrega">
        <div class="border-b border-border px-4 py-3">
            <h2 id="resumen-entrega" class="text-sm font-semibold text-blue-navy">Resumen de la entrega</h2>
        </div>
        <dl class="grid grid-cols-2 divide-x divide-y divide-border sm:grid-cols-3 xl:grid-cols-6 xl:divide-y-0">
            @foreach ([
                ['Código de lote (QR)', $recepcion->codigoQR ?? '—', true],
                ['Depositante', $nombreInvestigador, false],
                ['Lotes', $recepcion->nroLotes ?? '—', false],
                ['Individuos', $recepcion->nroIndividuos ?? '—', false],
                ['Grupo animal', $recepcion->grupoAnimal ?? '—', false],
                ['Localidad', $recepcion->localidad ?? '—', false],
            ] as [$etiqueta, $valor, $monoespaciado])
                <div class="min-w-0 px-4 py-3">
                    <dt class="text-xs text-text-secondary">{{ $etiqueta }}</dt>
                    <dd @class([
                        'mt-1 break-words text-sm font-semibold text-text-primary',
                        'font-mono tracking-wide text-blue-navy' => $monoespaciado,
                    ])>{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="hub-panel p-4" aria-labelledby="documentacion-entrega">
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-navy/8 text-blue-navy"><flux:icon name="document-check" class="size-5" /></span>
            <div class="min-w-0">
                <h2 id="documentacion-entrega" class="font-display text-base font-semibold text-blue-navy">Documentación que acompaña la entrega</h2>

                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-md bg-bg-main/70 px-3 py-2"><dt class="text-xs text-text-secondary">Permiso de recolección</dt><dd class="mt-1 font-mono font-medium text-text-primary">{{ $recepcion->nroPermisoRecoleccion ?? 'No declarado' }}</dd></div>
                    <div class="rounded-md bg-bg-main/70 px-3 py-2"><dt class="text-xs text-text-secondary">Guía o permiso de movilización</dt><dd class="mt-1 font-mono font-medium text-text-primary">{{ $recepcion->nroPermisoMovilizacion ?? 'No declarado' }}</dd></div>
                </dl>
            </div>
        </div>
    </section>

    @if (! $recepcion->recepcionIniciada)
        <flux:callout data-hub-feedback="info" variant="info" icon="hand-raised">
            <flux:callout.heading>Constatación pendiente de inicio</flux:callout.heading>
            <flux:callout.text>
                Confirma que el lote físico y sus documentos están presentes antes de abrir la lista de verificación. Este registro dejará constancia del inicio de la recepción por ventanilla.
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button variant="primary" icon="play" wire:click="iniciarRecepcion" wire:loading.attr="disabled" wire:target="iniciarRecepcion">
                    Iniciar constatación física
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Estado terminal: verificado (conforme o con observaciones) --}}
    @if($verificado)
        <flux:callout data-hub-feedback="success" variant="success" icon="check-badge">
            <flux:callout.heading>Lote recibido y constatado</flux:callout.heading>
            <flux:callout.text>
                La cadena de custodia quedó registrada. Se envió a curaduría una alerta con enlace
                directo para generar y firmar el acta final. Hasta que la firma sea validada, los
                especímenes no ingresan a la colección. Régimen propuesto:
                <span class="font-semibold">{{ $recepcion->estadoColeccion }}</span>.
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
        <flux:callout data-hub-feedback="warning" variant="warning" icon="arrow-path">
            <flux:callout.heading>Recepción suspendida</flux:callout.heading>
            <flux:callout.text>
                Anomalía subsanable: <span class="font-semibold">{{ $recepcion->motivoFallo }}</span>.
                Orden de acción correctiva: <span class="font-semibold">{{ $recepcion->accionCorrectiva }}</span>.
                El Código QR permanece vigente para reintentar la recepción del mismo lote.
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button
                    variant="primary"
                    icon="arrow-path"
                    wire:click="reintentar"
                    wire:loading.attr="disabled"
                    wire:target="reintentar"
                    class="w-full sm:w-auto"
                >
                    Reintentar recepción
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Lista de verificación + decisión (solo mientras está en verificación) --}}
    @if($recepcion->recepcionIniciada && $enVerificacion)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center gap-3">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white shrink-0">
                    <flux:icon name="clipboard-document-check" class="size-3.5" />
                </div>
                <flux:heading size="base" level="2" class="font-display">Lista de verificación de recepción</flux:heading>
            </div>

            {{-- Desktop: tabla --}}
            <div class="hidden md:block overflow-x-auto">
                <x-grid-frame><table class="w-full text-sm">
                    <thead class="bg-blue-navy">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white">Ítem de verificación</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Resultado conforme</th>
                            <th class="px-4 py-3 text-right font-medium text-white">Estado de comprobación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($items as $indice => $definicion)
                            <tr class="hover:bg-bg-main transition-colors">
                                <td class="px-4 py-3 font-medium text-text-primary">{{ $definicion['item'] }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $definicion['resultado'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end">
                                        <label for="criterio-desktop-{{ $indice }}" class="sr-only">Estado de comprobación: {{ $definicion['item'] }}</label>
                                        <select
                                            id="criterio-desktop-{{ $indice }}"
                                            wire:model.live="estadoCriterio.{{ $indice }}"
                                            aria-label="Estado de comprobación: {{ $definicion['item'] }}"
                                            class="min-h-11 rounded-lg border-border bg-surface px-3 text-sm text-text-primary focus:border-science-blue focus:ring-science-blue"
                                        >
                                            <option value="pendiente">Pendiente de comprobar</option>
                                            <option value="conforme">Criterio confirmado</option>
                                            <option value="no_conforme">No conformidad / observación</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></x-grid-frame>
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
                                {{ $definicion['resultado'] }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        </dl>
                        <label for="criterio-mobile-{{ $indice }}" class="block text-sm font-medium text-text-primary">Estado de comprobación</label>
                        <select
                            id="criterio-mobile-{{ $indice }}"
                            wire:model.live="estadoCriterio.{{ $indice }}"
                            aria-label="Estado de comprobación: {{ $definicion['item'] }}"
                            class="min-h-11 w-full rounded-lg border-border bg-surface px-3 text-sm text-text-primary focus:border-science-blue focus:ring-science-blue"
                        >
                            <option value="pendiente">Pendiente de comprobar</option>
                            <option value="conforme">Criterio confirmado</option>
                            <option value="no_conforme">No conformidad / observación</option>
                        </select>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- Zona de decisión --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5">
            <flux:heading size="base" level="2" class="font-display">Decisión de recepción</flux:heading>

            @error('estadoCriterio')
                <p data-hub-feedback="error" class="mt-3 text-sm font-medium text-error" role="alert">{{ $message }}</p>
            @enderror

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

    {{-- Modal: aceptar con observaciones (ítems no conformes) --}}
    <flux:modal wire:model="showObservacionModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Aceptar con observaciones</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Los siguientes ítems quedaron no conformes y curaduría los incorporará al Acta Digital
                de Recepción.
                @unless($conforme[1] ?? false)
                    Si curaduría firma el acta, se propondrá su ingreso en
                    <span class="font-semibold">cuarentena</span>.
                @endunless
            </flux:text>
            <ul class="space-y-1.5 rounded-lg border border-border bg-bg-main p-3 text-sm text-text-primary">
                @foreach($items as $indice => $definicion)
                    @unless($conforme[$indice] ?? false)
                        <li class="flex items-center gap-2">
                            <flux:icon name="exclamation-triangle" class="size-4 text-warning shrink-0" />
                            {{ $definicion['item'] }}
                        </li>
                    @endunless
                @endforeach
            </ul>
            <flux:field>
                <flux:label>Comentario adicional (opcional)</flux:label>
                <flux:textarea wire:model="comentarioObservacion" rows="2"
                    placeholder="Añade una nota extra si hay algo más que registrar..." />
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
