<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Bandeja de solicitudes</flux:heading>

    {{-- Filtro de estado --}}
    <div class="flex items-center gap-3">
        <flux:text class="text-sm text-text-secondary">Filtrar por estado:</flux:text>
        <flux:select wire:model.live="filtroEstado" class="w-48">
            <flux:select.option value="todos">Todos</flux:select.option>
            <flux:select.option value="enviada">Enviadas</flux:select.option>
            <flux:select.option value="aprobada">Aprobadas</flux:select.option>
            <flux:select.option value="observada">Observadas</flux:select.option>
            <flux:select.option value="rechazada">Rechazadas</flux:select.option>
        </flux:select>
    </div>

    @if($solicitudes->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="inbox" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin solicitudes</flux:heading>
            <flux:text class="text-text-secondary mt-1">No hay solicitudes con el filtro seleccionado.</flux:text>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Título</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Investigador</th>
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
                            <td class="px-4 py-3 text-sm text-text-secondary">
                                {{ $investigadores->get($solicitud->investigador_id)?->name ?? $solicitud->investigador_id }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $solicitud->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if($solicitud->estado === 'enviada')
                                        <flux:button size="sm" variant="primary" icon="check-circle"
                                            wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                            Decidir
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            wire:navigate href="{{ route('prestamos.curador.solicitud.revisar', $solicitud->id) }}">
                                            Revisar
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
