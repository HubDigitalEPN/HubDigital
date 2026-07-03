<div class="space-y-6">

    {{-- Encabezado --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display">Mis solicitudes</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Gestiona tus solicitudes de préstamo de especímenes entomológicos.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus"
            wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}"
            class="shrink-0 self-start sm:self-auto">
            Nueva solicitud
        </flux:button>
    </div>

    {{-- Panel de filtros --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-bg-main border-b border-border">
            <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
            <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
            @if($busqueda !== '' || $estado !== '')
                <button wire:click="limpiarFiltros" class="ml-auto text-xs font-medium text-science-blue hover:underline transition-colors">
                    Limpiar todo
                </button>
            @endif
        </div>
        <div class="px-4 py-3 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por título o N.º solicitud..."
                    icon="magnifying-glass"
                    clearable />
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <flux:select wire:model.live="estado" class="w-48">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    <flux:select.option value="borrador">Borrador</flux:select.option>
                    <flux:select.option value="enviada">Enviada</flux:select.option>
                    <flux:select.option value="observada">Observada</flux:select.option>
                    <flux:select.option value="aprobada">Aprobada</flux:select.option>
                    <flux:select.option value="rechazada">Rechazada</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="ordenCampo" class="w-44">
                    <flux:select.option value="fecha">Por fecha</flux:select.option>
                    <flux:select.option value="titulo">Por título</flux:select.option>
                </flux:select>
                <flux:button
                    wire:click="toggleOrden"
                    variant="ghost"
                    icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                    title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            </div>
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== ''; @endphp

    @if(count($solicitudes) === 0 && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="document-text" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Aún no tienes solicitudes</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">Crea tu primera solicitud de préstamo para comenzar.</flux:text>
            </div>
            <flux:button variant="primary" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
                Crear solicitud
            </flux:button>
        </div>

    @elseif(count($solicitudes) === 0)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="magnifying-glass" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin resultados</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">No se encontraron solicitudes con los filtros aplicados.</flux:text>
            </div>
            <flux:button variant="ghost" wire:click="limpiarFiltros">Limpiar filtros</flux:button>
        </div>

    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white w-72">Título del estudio</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha de envío</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($solicitudes as $solicitud)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->numeroSolicitud }}
                            </td>
                            <td class="px-4 py-3 font-medium text-text-primary w-72">
                                <flux:tooltip content="{{ $solicitud->tituloEstudio }}">
                                    <span class="block truncate max-w-xs cursor-default">{{ $solicitud->tituloEstudio }}</span>
                                </flux:tooltip>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1.5">
                                    <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                                    @if($solicitud->actaEstado)
                                        @if($solicitud->actaEstado === 'pendiente_firma')
                                            <a wire:navigate href="{{ route('prestamos.investigador.acta.detalle', $solicitud->actaId) }}"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 w-fit hover:bg-amber-100 transition-colors">
                                                <flux:icon name="exclamation-triangle" class="size-3" />
                                                Requiere tu firma
                                            </a>
                                        @elseif($solicitud->actaEstado === 'pendiente_validacion')
                                            <span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded px-2 py-0.5 w-fit">
                                                <flux:icon name="clock" class="size-3" />
                                                Acta en validación
                                            </span>
                                        @elseif($solicitud->actaEstado === 'pendiente_envio')
                                            <span class="inline-flex items-center gap-1 text-xs text-text-secondary bg-bg-main border border-border rounded px-2 py-0.5 w-fit">
                                                <flux:icon name="document-text" class="size-3" />
                                                Acta en preparación
                                            </span>
                                        @elseif($solicitud->actaEstado === 'validada')
                                            <a wire:navigate href="{{ route('prestamos.investigador.mis-actas') }}"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded px-2 py-0.5 w-fit hover:bg-green-100 transition-colors">
                                                <flux:icon name="check-circle" class="size-3" />
                                                Préstamo activo
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.investigador.solicitud.detalle', $solicitud->solicitudId) }}">
                                        Ver
                                    </flux:button>
                                    @if(in_array($solicitud->estado, ['borrador', 'observada']))
                                        <flux:button size="sm" variant="ghost" icon="pencil"
                                            wire:navigate href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->solicitudId) }}">
                                            Editar
                                        </flux:button>
                                    @endif
                                    @if($solicitud->estado === 'borrador')
                                        <flux:button size="sm" variant="primary" icon="paper-airplane"
                                            wire:click="prepararEnvioSolicitud('{{ $solicitud->solicitudId }}')">
                                            Enviar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    {{-- Modal: confirmar envío de solicitud para revisión --}}
    <flux:modal wire:model="showEnviarSolicitudModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-science-blue/15 shrink-0">
                    <flux:icon name="paper-airplane" class="size-5 text-science-blue" />
                </div>
                <flux:heading size="lg">Enviar solicitud para revisión</flux:heading>
            </div>
            <flux:text class="text-text-secondary text-sm">
                La solicitud será enviada al curador responsable para su revisión. Ya no podrás editarla hasta recibir una respuesta.
            </flux:text>
            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="paper-airplane"
                    wire:click="enviarSolicitud"
                    wire:loading.attr="disabled"
                    wire:target="enviarSolicitud">
                    <flux:icon wire:loading wire:target="enviarSolicitud" name="arrow-path" class="animate-spin" />
                    Sí, enviar
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
