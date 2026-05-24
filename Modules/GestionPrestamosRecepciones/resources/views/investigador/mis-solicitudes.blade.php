<div class="p-6 space-y-6">

    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="font-display">Mis solicitudes de préstamo</flux:heading>
        <flux:button variant="primary" icon="plus" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
            Nueva solicitud
        </flux:button>
    </div>

    {{-- Barra de filtros --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por título o N.º solicitud..."
                icon="magnifying-glass"
                clearable />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="estado" class="w-48">
                <flux:select.option value="">Todos los estados</flux:select.option>
                <flux:select.option value="borrador">Borrador</flux:select.option>
                <flux:select.option value="enviada">Enviada</flux:select.option>
                <flux:select.option value="observada">Observada</flux:select.option>
                <flux:select.option value="aprobada">Aprobada</flux:select.option>
                <flux:select.option value="rechazada">Rechazada</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="ordenCampo" class="w-44">
                <flux:select.option value="fecha">Ordenar por fecha</flux:select.option>
                <flux:select.option value="titulo">Ordenar por título</flux:select.option>
            </flux:select>
            <flux:button
                wire:click="toggleOrden"
                variant="ghost"
                icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            @if($busqueda !== '' || $estado !== '')
                <flux:button wire:click="limpiarFiltros" variant="ghost" icon="x-mark" title="Limpiar filtros" />
            @endif
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== ''; @endphp

    @if($solicitudes->isEmpty() && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="document-text" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Aún no tienes solicitudes</flux:heading>
            <flux:text class="text-text-secondary mt-1">Crea tu primera solicitud de préstamo para comenzar.</flux:text>
            <flux:button variant="primary" class="mt-4" wire:navigate href="{{ route('prestamos.investigador.solicitud.crear') }}">
                Crear solicitud
            </flux:button>
        </div>
    @elseif($solicitudes->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="magnifying-glass" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin resultados</flux:heading>
            <flux:text class="text-text-secondary mt-1">No se encontraron solicitudes con los filtros aplicados.</flux:text>
            <flux:button variant="ghost" class="mt-4" wire:click="limpiarFiltros">
                Limpiar filtros
            </flux:button>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Título del estudio</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($solicitudes as $solicitud)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->numero_solicitud }}
                            </td>
                            <td class="px-4 py-3 font-medium text-text-primary">
                                {{ $solicitud->titulo_estudio }}
                            </td>
                            <td class="px-4 py-3">
                                @php $actaSolicitud = $actasPorSolicitud[$solicitud->id] ?? null; @endphp
                                <div class="flex flex-col gap-1.5">
                                    <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                                    @if($actaSolicitud)
                                        @if($actaSolicitud->estado === 'pendiente_firma')
                                            <a wire:navigate href="{{ route('prestamos.investigador.acta.detalle', $actaSolicitud->id) }}"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-0.5 w-fit hover:bg-amber-100 transition-colors">
                                                <flux:icon name="exclamation-triangle" class="size-3" />
                                                Requiere tu firma
                                            </a>
                                        @elseif($actaSolicitud->estado === 'pendiente_validacion')
                                            <span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded px-2 py-0.5 w-fit">
                                                <flux:icon name="clock" class="size-3" />
                                                Acta en validación
                                            </span>
                                        @elseif($actaSolicitud->estado === 'pendiente_envio')
                                            <span class="inline-flex items-center gap-1 text-xs text-text-secondary bg-bg-main border border-border rounded px-2 py-0.5 w-fit">
                                                <flux:icon name="document-text" class="size-3" />
                                                Acta en preparación
                                            </span>
                                        @elseif($actaSolicitud->estado === 'validada')
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
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.investigador.solicitud.detalle', $solicitud->id) }}">
                                        Ver
                                    </flux:button>
                                    @if(in_array($solicitud->estado, ['borrador', 'observada']))
                                        <flux:button size="sm" variant="ghost" icon="pencil"
                                            wire:navigate href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->id) }}">
                                            Editar
                                        </flux:button>
                                    @endif
                                    @if($solicitud->estado === 'borrador')
                                        <flux:button size="sm" variant="primary" icon="paper-airplane"
                                            wire:click="enviarSolicitud('{{ $solicitud->id }}')"
                                            wire:confirm="¿Enviar esta solicitud para revisión del curador?"
                                            wire:loading.attr="disabled">
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
    @endif

</div>
