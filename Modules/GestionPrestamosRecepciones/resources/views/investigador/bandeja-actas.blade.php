<div class="space-y-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl" level="1" class="font-display">Mis actas</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Actas de préstamo pendientes de firma y validadas.
        </flux:text>
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
                    placeholder="Buscar por N.º acta o N.º solicitud..."
                    icon="magnifying-glass"
                    clearable />
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <flux:select wire:model.live="estado" class="w-52">
                    <flux:select.option value="">Todos los estados</flux:select.option>
                    <flux:select.option value="pendiente_envio">Pendiente de envío</flux:select.option>
                    <flux:select.option value="pendiente_firma">Pendiente de firma</flux:select.option>
                    <flux:select.option value="pendiente_validacion">Pendiente de validación</flux:select.option>
                    <flux:select.option value="validada">Validada</flux:select.option>
                    <flux:select.option value="rechazada">Rechazada</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="ordenCampo" class="w-48">
                    <flux:select.option value="fecha">Por fecha</flux:select.option>
                    <flux:select.option value="numero_solicitud">Por N.º solicitud</flux:select.option>
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

    @if(count($actas) === 0 && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="clipboard-document" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin actas</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">
                    Tus actas aparecerán aquí cuando el curador apruebe tu solicitud.
                </flux:text>
            </div>
        </div>

    @elseif(count($actas) === 0)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="magnifying-glass" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin resultados</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">No se encontraron actas con los filtros aplicados.</flux:text>
            </div>
            <flux:button variant="ghost" wire:click="limpiarFiltros">Limpiar filtros</flux:button>
        </div>

    @else
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º acta</th>
                        <th class="px-4 py-3 text-left font-medium text-white">N.º solicitud</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($actas as $acta)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->numeroPrestamo }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->numeroSolicitud ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($acta->estado === 'pendiente_firma')
                                    <flux:button size="sm" variant="primary" icon="arrow-up-tray"
                                        wire:navigate href="{{ route('prestamos.investigador.acta.detalle', $acta->actaId) }}">
                                        Adjuntar documentos
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="eye"
                                        wire:navigate href="{{ route('prestamos.investigador.acta.detalle', $acta->actaId) }}">
                                        Ver detalle
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

</div>
