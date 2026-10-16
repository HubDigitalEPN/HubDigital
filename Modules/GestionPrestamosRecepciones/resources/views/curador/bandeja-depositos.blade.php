<div class="hub-transactional-ui hub-depositos-ui hub-workspace p-4 sm:p-6 space-y-5"
    x-data
    @toast.window="$flux.toast($event.detail.message)"
    @domain-error.window="$flux.toast({ text: $event.detail.message, variant: 'danger' })">

    {{-- Encabezado --}}
    <header class="hub-page-header">
        <div>
            <h1 class="mt-1 hub-page-title">Bandeja de recepciones</h1>
        </div>
    </header>

    <section class="overflow-hidden rounded-lg border border-border bg-surface shadow-sm" aria-label="Controles de la bandeja">
    {{-- Pestañas: revisión documental / actuación curatorial / historial --}}
    <div class="flex max-w-full overflow-x-auto border-b border-border p-1">
        <button type="button" wire:click="cambiarVista('pendientes')"
            @class([
                'inline-flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                'bg-blue-navy text-white' => ! $esResueltas && ! $esActas,
                'text-text-secondary hover:text-text-primary' => $esResueltas || $esActas,
            ])>
            Por revisar
            @if($totalPendientes > 0)
                <span @class([
                    'inline-flex items-center justify-center rounded-full px-1.5 min-w-[1.25rem] h-5 text-xs font-semibold tabular-nums',
                    'bg-white/20 text-white' => ! $esResueltas && ! $esActas,
                    'bg-warning/15 text-warning' => $esResueltas || $esActas,
                ])>{{ $totalPendientes }}</span>
            @endif
        </button>
        <button type="button" wire:click="cambiarVista('actas')"
            @class([
                'inline-flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                'bg-blue-navy text-white' => $esActas,
                'text-text-secondary hover:text-text-primary' => ! $esActas,
            ])>
            Actas pendientes
            @if($totalActasPendientes > 0)
                <span @class([
                    'inline-flex items-center justify-center rounded-full px-1.5 min-w-[1.25rem] h-5 text-xs font-semibold tabular-nums',
                    'bg-white/20 text-white' => $esActas,
                    'bg-danger/15 text-danger' => ! $esActas,
                ])>{{ $totalActasPendientes }}</span>
            @endif
        </button>
        <button type="button" wire:click="cambiarVista('resueltas')"
            @class([
                'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                'bg-blue-navy text-white' => $esResueltas,
                'text-text-secondary hover:text-text-primary' => ! $esResueltas,
            ])>
            Resueltas
        </button>
    </div>

    {{-- Filtros --}}
    <div>
        <div class="flex items-center gap-2 bg-bg-main px-4 py-2">
            <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
            <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
            <span wire:loading.delay wire:target="busqueda, tipoTramite, ordenDireccion, toggleOrden, cambiarVista, limpiarFiltros"
                class="inline-flex items-center gap-1 text-xs text-science-blue">
                <flux:icon name="arrow-path" class="size-3 animate-spin" /> Actualizando…
            </span>
            @if($hayFiltros)
                <button wire:click="limpiarFiltros" class="ml-auto text-xs font-medium text-science-blue hover:underline transition-colors">
                    Limpiar todo
                </button>
            @endif
        </div>
        <div class="grid gap-2 p-3 lg:grid-cols-[minmax(0,1fr)_13rem_auto] lg:items-center">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por N.º o depositante..."
                icon="magnifying-glass"
                clearable />
                <flux:select wire:model.live="tipoTramite">
                    <flux:select.option value="">Todos los trámites</flux:select.option>
                    <flux:select.option value="Depósito">Depósito</flux:select.option>
                    <flux:select.option value="Donación">Donación</flux:select.option>
                </flux:select>
                <flux:button
                    wire:click="toggleOrden"
                    variant="ghost"
                    icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                    class="w-full lg:w-auto">
                    {{ $ordenDireccion === 'asc' ? 'Más antiguas' : 'Más recientes' }}
                </flux:button>
        </div>
    </div>
    </section>

    @if(count($solicitudes) === 0)
        <div class="hub-compact-empty rounded-lg border border-border bg-surface shadow-sm">
            <div class="flex size-12 items-center justify-center rounded-full border border-border bg-bg-main">
                <flux:icon name="{{ $hayFiltros ? 'magnifying-glass' : ($esActas ? 'document-check' : ($esResueltas ? 'check-circle' : 'inbox')) }}" class="size-6 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">
                    {{ $hayFiltros ? 'Sin resultados' : ($esActas ? 'Sin actas pendientes' : ($esResueltas ? 'Sin solicitudes resueltas' : 'Bandeja al día')) }}
                </flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">
                    {{ $hayFiltros
                        ? 'Ninguna solicitud coincide con los filtros aplicados.'
                        : ($esActas
                            ? 'No hay lotes recibidos y constatados esperando la firma del acta final.'
                            : ($esResueltas
                                ? 'Aún no has aprobado ni rechazado ninguna solicitud.'
                                : 'No hay solicitudes pendientes de revisión documental.')) }}
                </flux:text>
            </div>
            @if($hayFiltros)
                <flux:button wire:click="limpiarFiltros" variant="ghost" size="sm">Limpiar filtros</flux:button>
            @endif
        </div>
    @else
        {{-- Tabla — escritorio --}}
        <div class="hidden md:block rounded-lg border border-border bg-surface shadow-sm overflow-hidden transition-opacity"
            wire:loading.class.delay="opacity-50 pointer-events-none"
            wire:target="busqueda, tipoTramite, ordenDireccion, toggleOrden, cambiarVista, limpiarFiltros">
            <div class="overflow-x-auto">
                <x-grid-frame><table class="w-full text-sm min-w-[820px]">
                    <thead class="bg-blue-navy border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Trámite</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Depositante</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                            @unless($esResueltas || $esActas)
                                <th class="px-4 py-3 text-left font-medium text-white">Prioridad</th>
                            @endunless
                            <th class="px-4 py-3 text-left font-medium text-white">{{ $esActas ? 'Recibida' : ($esResueltas ? 'Resuelta' : 'Fecha') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-white">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($solicitudes as $solicitud)
                            @php
                                $esPrioritaria = $solicitud->prioridad === 'Prioritaria';
                                $recepcion = $recepciones->get($solicitud->id);
                                $actaPendiente = $recepcion !== null;
                            @endphp
                            <tr class="hover:bg-bg-main transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                    {{ $solicitud->numero ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :color="$solicitud->tipo_tramite === 'Donación' ? 'purple' : 'sky'">
                                        {{ $solicitud->tipo_tramite }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-text-secondary">
                                    {{ $nombres[$solicitud->investigador_id] ?? $solicitud->nombre_investigador_documento ?? $solicitud->investigador_id }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-gestionprestamosrecepciones::deposito-status-badge :estado="$solicitud->estado" />
                                    @if($actaPendiente)
                                        <flux:badge size="sm" color="amber" icon="bell-alert" class="mt-1">Acta pendiente</flux:badge>
                                    @endif
                                </td>
                                @unless($esResueltas || $esActas)
                                    <td class="px-4 py-3">
                                        @if($esPrioritaria)
                                            <flux:badge size="sm" color="indigo" icon="flag">Prioritaria</flux:badge>
                                        @else
                                            <span class="text-xs text-text-secondary">Normal</span>
                                        @endif
                                    </td>
                                @endunless
                                <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                    @fechaEc($esActas ? $recepcion?->verificado_en : ($esResueltas ? $solicitud->updated_at : $solicitud->created_at), 'd/m/Y')
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @if(! $esResueltas && ! $esActas && ! $esPrioritaria)
                                            <flux:button size="sm" variant="ghost" icon="flag"
                                                wire:click="priorizar('{{ $solicitud->id }}')"
                                                wire:loading.attr="disabled" wire:target="priorizar('{{ $solicitud->id }}')">
                                                <flux:icon wire:loading wire:target="priorizar('{{ $solicitud->id }}')" name="arrow-path" class="animate-spin size-3.5" />
                                                Priorizar
                                            </flux:button>
                                        @endif
                                        @if($actaPendiente)
                                            <flux:button size="sm" variant="primary" icon="pencil-square"
                                                wire:navigate href="{{ route('prestamos.curador.deposito.acta', $solicitud->id) }}">
                                                Generar y firmar
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" :variant="$esResueltas ? 'ghost' : 'primary'"
                                                icon="{{ $esResueltas ? 'eye' : 'clipboard-document-check' }}"
                                                wire:navigate href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}">
                                                {{ $esResueltas ? 'Ver' : 'Revisar' }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></x-grid-frame>
            </div>
        </div>

        {{-- Tarjetas — móvil --}}
        <div class="md:hidden space-y-3 transition-opacity"
            wire:loading.class.delay="opacity-50 pointer-events-none"
            wire:target="busqueda, tipoTramite, ordenDireccion, toggleOrden, cambiarVista, limpiarFiltros">
            @foreach($solicitudes as $solicitud)
                @php
                    $esPrioritaria = $solicitud->prioridad === 'Prioritaria';
                    $recepcion = $recepciones->get($solicitud->id);
                    $actaPendiente = $recepcion !== null;
                @endphp
                <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-text-secondary">{{ $solicitud->numero ?? '—' }}</p>
                            <flux:badge size="sm" class="mt-1" :color="$solicitud->tipo_tramite === 'Donación' ? 'purple' : 'sky'">
                                {{ $solicitud->tipo_tramite }}
                            </flux:badge>
                        </div>
                        <x-gestionprestamosrecepciones::deposito-status-badge :estado="$solicitud->estado" />
                    </div>

                    <dl class="space-y-2 text-sm">
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Depositante">
                            {{ $nombres[$solicitud->investigador_id] ?? $solicitud->nombre_investigador_documento ?? $solicitud->investigador_id }}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        @unless($esResueltas || $esActas)
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Prioridad">
                                {{ $esPrioritaria ? 'Prioritaria' : 'Normal' }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        @endunless
                        @if($actaPendiente)
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Acción curatorial">
                                <span class="font-medium text-warning">Generar y firmar acta final</span>
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        @endif
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil :etiqueta="$esActas ? 'Recibida' : ($esResueltas ? 'Resuelta' : 'Fecha')">
                            @fechaEc($esActas ? $recepcion?->verificado_en : ($esResueltas ? $solicitud->updated_at : $solicitud->created_at), 'd/m/Y')
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    </dl>

                    <div class="flex flex-col gap-2 pt-1">
                        @if(! $esResueltas && ! $esActas && ! $esPrioritaria)
                            <flux:button variant="ghost" icon="flag" class="w-full"
                                wire:click="priorizar('{{ $solicitud->id }}')"
                                wire:loading.attr="disabled" wire:target="priorizar('{{ $solicitud->id }}')">
                                <flux:icon wire:loading wire:target="priorizar('{{ $solicitud->id }}')" name="arrow-path" class="animate-spin size-3.5" />
                                Priorizar
                            </flux:button>
                        @endif
                        @if($actaPendiente)
                            <flux:button variant="primary" icon="pencil-square" class="w-full"
                                wire:navigate href="{{ route('prestamos.curador.deposito.acta', $solicitud->id) }}">
                                Generar y firmar acta
                            </flux:button>
                        @else
                            <flux:button :variant="$esResueltas ? 'ghost' : 'primary'"
                                icon="{{ $esResueltas ? 'eye' : 'clipboard-document-check' }}" class="w-full"
                                wire:navigate href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}">
                                {{ $esResueltas ? 'Ver' : 'Revisar' }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
