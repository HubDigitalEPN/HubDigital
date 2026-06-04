<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Mis actas</flux:heading>

    {{-- Barra de filtros --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por N.º acta o N.º solicitud..."
                icon="magnifying-glass"
                clearable />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="estado" class="w-52">
                <flux:select.option value="">Todos los estados</flux:select.option>
                <flux:select.option value="pendiente_envio">Pendiente de envío</flux:select.option>
                <flux:select.option value="pendiente_firma">Pendiente de firma</flux:select.option>
                <flux:select.option value="pendiente_validacion">Pendiente de validación</flux:select.option>
                <flux:select.option value="validada">Validada</flux:select.option>
                <flux:select.option value="rechazada">Rechazada</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="ordenCampo" class="w-52">
                <flux:select.option value="fecha">Ordenar por fecha</flux:select.option>
                <flux:select.option value="numero_solicitud">Ordenar por N.º solicitud</flux:select.option>
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

    @if($actas->isEmpty() && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="clipboard-document" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin actas</flux:heading>
            <flux:text class="text-text-secondary mt-1">Tus actas aparecerán aquí cuando el funcionario responsable apruebe tu solicitud.</flux:text>
        </div>
    @elseif($actas->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="magnifying-glass" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin resultados</flux:heading>
            <flux:text class="text-text-secondary mt-1">No se encontraron actas con los filtros aplicados.</flux:text>
            <flux:button variant="ghost" class="mt-4" wire:click="limpiarFiltros">
                Limpiar filtros
            </flux:button>
        </div>
    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º acta</th>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha de acta</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($actas as $acta)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->numero_prestamo }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->solicitud?->numero_solicitud ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    wire:navigate href="{{ route('prestamos.investigador.acta.detalle', $acta->id) }}">
                                    Ver detalle
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
