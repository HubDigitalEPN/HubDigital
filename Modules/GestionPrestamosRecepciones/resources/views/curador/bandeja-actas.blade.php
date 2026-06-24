<div class="space-y-6">

    <div>
        <flux:heading size="xl" level="1" class="font-display">Actas</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">Actas de préstamo pendientes de envío, firma y validación.</flux:text>
    </div>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    {{-- Panel de filtros --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-bg-main border-b border-border">
            <flux:icon name="funnel" class="size-3.5 text-text-secondary" />
            <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">Filtros</span>
            @if($busqueda !== '' || $estado !== '' || $busquedaInvestigador !== '')
                <button wire:click="limpiarFiltros" class="ml-auto text-xs font-medium text-science-blue hover:underline transition-colors">
                    Limpiar todo
                </button>
            @endif
        </div>
        <div class="px-4 py-3 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <flux:input
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por código o título..."
                    icon="magnifying-glass"
                    clearable
                    class="flex-1" />
                <flux:input
                    wire:model.live.debounce.300ms="busquedaInvestigador"
                    placeholder="Buscar por solicitante..."
                    icon="user"
                    clearable
                    class="flex-1" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
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
                    <flux:select.option value="codigo">Por código</flux:select.option>
                </flux:select>
                <flux:button
                    wire:click="toggleOrden"
                    variant="ghost"
                    icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                    title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            </div>
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== '' || $busquedaInvestigador !== ''; @endphp

    @if(count($actas) === 0 && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-16 text-center px-8 gap-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-bg-main border border-border">
                <flux:icon name="clipboard-document" class="size-8 text-text-secondary/50" />
            </div>
            <div>
                <flux:heading size="lg" level="2">Sin actas</flux:heading>
                <flux:text class="text-text-secondary mt-1 text-sm">Las actas aparecerán aquí cuando se aprueben solicitudes.</flux:text>
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
            <table class="w-full text-sm min-w-[750px]">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Solicitante</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha de generación</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($actas as $acta)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->numeroPrestamo }}
                            </td>
                            <td class="px-4 py-3 text-sm text-text-secondary">
                                {{ $acta->solicitanteNombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->fecha->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    @if($acta->estado === 'pendiente_envio')
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            href="{{ route('prestamos.acta.ver', $acta->actaId) }}" target="_blank">
                                            Vista previa
                                        </flux:button>
                                        <flux:button size="sm" variant="primary" icon="paper-airplane"
                                            wire:click="prepararEnvioActa('{{ $acta->actaId }}')">
                                            Enviar
                                        </flux:button>
                                    @elseif($acta->estado === 'pendiente_firma')
                                        <flux:button size="sm" variant="ghost" icon="document-text"
                                            href="{{ route('prestamos.acta.ver', $acta->actaId) }}" target="_blank">
                                            Ver acta
                                        </flux:button>
                                    @elseif($acta->estado === 'pendiente_validacion')
                                        <flux:button size="sm" variant="primary" icon="magnifying-glass"
                                            wire:navigate href="{{ route('prestamos.curador.acta.validar', $acta->actaId) }}">
                                            Validar
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="document-text"
                                            wire:navigate href="{{ route('prestamos.curador.acta.validar', $acta->actaId) }}">
                                            Ver documentos
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

    {{-- Modal: confirmar envío de acta al investigador --}}
    <flux:modal wire:model="showEnviarActaModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-science-blue/15 shrink-0">
                    <flux:icon name="paper-airplane" class="size-5 text-science-blue" />
                </div>
                <flux:heading size="lg">Enviar acta al investigador</flux:heading>
            </div>
            <flux:text class="text-text-secondary text-sm">
                El acta será enviada al investigador para su firma digital. Esta acción no se puede deshacer.
            </flux:text>
            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="paper-airplane"
                    wire:click="enviarActa"
                    wire:loading.attr="disabled"
                    wire:target="enviarActa">
                    <flux:icon wire:loading wire:target="enviarActa" name="arrow-path" class="animate-spin" />
                    Sí, enviar
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
