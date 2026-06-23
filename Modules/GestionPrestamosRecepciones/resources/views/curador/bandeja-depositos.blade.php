<div class="p-4 sm:p-6 space-y-5"
    x-data
    @toast.window="$flux.toast($event.detail.message)"
    @domain-error.window="$flux.toast({ text: $event.detail.message, variant: 'danger' })">

    {{-- Encabezado --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display">Bandeja de recepciones</flux:heading>
            <flux:text class="text-text-secondary mt-1 text-sm">
                {{ $esResueltas
                    ? 'Solicitudes de depósito y donación ya revisadas (aprobadas o rechazadas).'
                    : 'Solicitudes de depósito y donación pendientes de revisión documental.' }}
            </flux:text>
        </div>
    </div>

    {{-- Pestañas: por revisar / resueltas --}}
    <div class="inline-flex rounded-lg border border-border bg-surface p-1">
        <button type="button" wire:click="cambiarVista('pendientes')"
            @class([
                'inline-flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                'bg-blue-navy text-white' => ! $esResueltas,
                'text-text-secondary hover:text-text-primary' => $esResueltas,
            ])>
            Por revisar
            @if($totalPendientes > 0)
                <span @class([
                    'inline-flex items-center justify-center rounded-full px-1.5 min-w-[1.25rem] h-5 text-xs font-semibold tabular-nums',
                    'bg-white/20 text-white' => ! $esResueltas,
                    'bg-warning/15 text-warning' => $esResueltas,
                ])>{{ $totalPendientes }}</span>
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
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-bg-main border-b border-border">
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
        <div class="px-4 py-3 space-y-3">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por N.º solicitud..."
                icon="magnifying-glass"
                clearable />
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:select wire:model.live="tipoTramite" class="sm:w-52">
                    <flux:select.option value="">Todos los trámites</flux:select.option>
                    <flux:select.option value="Depósito">Depósito</flux:select.option>
                    <flux:select.option value="Donación">Donación</flux:select.option>
                </flux:select>
                <flux:button
                    wire:click="toggleOrden"
                    variant="ghost"
                    icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                    class="w-full sm:w-auto">
                    {{ $ordenDireccion === 'asc' ? 'Más antiguas' : 'Más recientes' }}
                </flux:button>
            </div>
        </div>
    </div>

    @if(count($solicitudes) === 0)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="{{ $hayFiltros ? 'magnifying-glass' : ($esResueltas ? 'check-circle' : 'inbox') }}" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">
                    {{ $hayFiltros ? 'Sin resultados' : ($esResueltas ? 'Sin solicitudes resueltas' : 'Bandeja al día') }}
                </flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">
                    {{ $hayFiltros
                        ? 'Ninguna solicitud coincide con los filtros aplicados.'
                        : ($esResueltas
                            ? 'Aún no has aprobado ni rechazado ninguna solicitud.'
                            : 'No hay solicitudes pendientes de revisión documental.') }}
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
                <table class="w-full text-sm min-w-[820px]">
                    <thead class="bg-blue-navy border-b border-border">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Trámite</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Solicitante</th>
                            <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                            @unless($esResueltas)
                                <th class="px-4 py-3 text-left font-medium text-white">Prioridad</th>
                            @endunless
                            <th class="px-4 py-3 text-left font-medium text-white">{{ $esResueltas ? 'Resuelta' : 'Fecha' }}</th>
                            <th class="px-4 py-3 text-right font-medium text-white">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($solicitudes as $solicitud)
                            @php $esPrioritaria = $solicitud->prioridad === 'Prioritaria'; @endphp
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
                                </td>
                                @unless($esResueltas)
                                    <td class="px-4 py-3">
                                        @if($esPrioritaria)
                                            <flux:badge size="sm" color="indigo" icon="flag">Prioritaria</flux:badge>
                                        @else
                                            <span class="text-xs text-text-secondary">Normal</span>
                                        @endif
                                    </td>
                                @endunless
                                <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                    {{ ($esResueltas ? $solicitud->updated_at : $solicitud->created_at)?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @if(! $esResueltas && ! $esPrioritaria)
                                            <flux:button size="sm" variant="ghost" icon="flag"
                                                wire:click="priorizar('{{ $solicitud->id }}')"
                                                wire:loading.attr="disabled" wire:target="priorizar('{{ $solicitud->id }}')">
                                                <flux:icon wire:loading wire:target="priorizar('{{ $solicitud->id }}')" name="arrow-path" class="animate-spin size-3.5" />
                                                Priorizar
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" :variant="$esResueltas ? 'ghost' : 'primary'"
                                            icon="{{ $esResueltas ? 'eye' : 'clipboard-document-check' }}"
                                            wire:navigate href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}">
                                            {{ $esResueltas ? 'Ver' : 'Revisar' }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tarjetas — móvil --}}
        <div class="md:hidden space-y-3 transition-opacity"
            wire:loading.class.delay="opacity-50 pointer-events-none"
            wire:target="busqueda, tipoTramite, ordenDireccion, toggleOrden, cambiarVista, limpiarFiltros">
            @foreach($solicitudes as $solicitud)
                @php $esPrioritaria = $solicitud->prioridad === 'Prioritaria'; @endphp
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
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Solicitante">
                            {{ $nombres[$solicitud->investigador_id] ?? $solicitud->nombre_investigador_documento ?? $solicitud->investigador_id }}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        @unless($esResueltas)
                            <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Prioridad">
                                {{ $esPrioritaria ? 'Prioritaria' : 'Normal' }}
                            </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                        @endunless
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil :etiqueta="$esResueltas ? 'Resuelta' : 'Fecha'">
                            {{ ($esResueltas ? $solicitud->updated_at : $solicitud->created_at)?->format('d/m/Y') ?? '—' }}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    </dl>

                    <div class="flex flex-col gap-2 pt-1">
                        @if(! $esResueltas && ! $esPrioritaria)
                            <flux:button variant="ghost" icon="flag" class="w-full"
                                wire:click="priorizar('{{ $solicitud->id }}')"
                                wire:loading.attr="disabled" wire:target="priorizar('{{ $solicitud->id }}')">
                                <flux:icon wire:loading wire:target="priorizar('{{ $solicitud->id }}')" name="arrow-path" class="animate-spin size-3.5" />
                                Priorizar
                            </flux:button>
                        @endif
                        <flux:button :variant="$esResueltas ? 'ghost' : 'primary'"
                            icon="{{ $esResueltas ? 'eye' : 'clipboard-document-check' }}" class="w-full"
                            wire:navigate href="{{ route('prestamos.curador.deposito.revisar', $solicitud->id) }}">
                            {{ $esResueltas ? 'Ver' : 'Revisar' }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
