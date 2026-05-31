<div class="p-6 space-y-6">

    <flux:heading size="xl" level="1" class="font-display">Actas</flux:heading>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    {{-- Barra de filtros --}}
    <div class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:input
                wire:model.live.debounce.300ms="busqueda"
                placeholder="Buscar por N.º acta, N.º solicitud o título..."
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
            <flux:select wire:model.live="ordenCampo" class="w-52">
                <flux:select.option value="fecha">Ordenar por fecha</flux:select.option>
                <flux:select.option value="numero_solicitud">Ordenar por N.º solicitud</flux:select.option>
            </flux:select>
            <flux:button
                wire:click="toggleOrden"
                variant="ghost"
                icon="{{ $ordenDireccion === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}"
                title="{{ $ordenDireccion === 'asc' ? 'Ascendente' : 'Descendente' }}" />
            @if($busqueda !== '' || $estado !== '' || $busquedaInvestigador !== '')
                <flux:button wire:click="limpiarFiltros" variant="ghost" icon="x-mark" title="Limpiar filtros" />
            @endif
        </div>
    </div>

    @php $filtroActivo = $busqueda !== '' || $estado !== '' || $busquedaInvestigador !== ''; @endphp

    @if($actas->isEmpty() && !$filtroActivo)
        <div class="flex flex-col items-center justify-center rounded-lg border border-border bg-surface py-[60px] text-center">
            <flux:icon name="clipboard-document" class="size-12 text-text-secondary mb-3" />
            <flux:heading size="lg" level="2">Sin actas</flux:heading>
            <flux:text class="text-text-secondary mt-1">Las actas aparecerán aquí cuando se aprueben solicitudes.</flux:text>
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
                        <th class="px-4 py-3 text-left font-medium text-white">Solicitante</th>
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
                            <td class="px-4 py-3 text-sm text-text-secondary">
                                {{ $investigadores->get($acta->solicitud?->investigador_id)?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                            </td>
                            <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                                {{ $acta->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    @if($acta->estado === 'pendiente_envio')
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                                            Vista previa
                                        </flux:button>
                                        <flux:button size="sm" variant="primary" icon="paper-airplane"
                                            wire:click="enviarActa('{{ $acta->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="enviarActa('{{ $acta->id }}')"
                                            wire:confirm="¿Confirmas que deseas enviar el acta al investigador para su firma?">
                                            <flux:icon wire:loading wire:target="enviarActa('{{ $acta->id }}')" name="arrow-path" class="animate-spin" />
                                            Enviar
                                        </flux:button>
                                    @elseif($acta->estado === 'pendiente_firma')
                                        <flux:button size="sm" variant="ghost" icon="document-text"
                                            href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                                            Ver acta
                                        </flux:button>
                                    @elseif($acta->estado === 'pendiente_validacion')
                                        <flux:button size="sm" variant="primary" icon="magnifying-glass"
                                            wire:navigate href="{{ route('prestamos.curador.acta.validar', $acta->id) }}">
                                            Validar
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="ghost" icon="eye"
                                            href="{{ route('prestamos.acta.ver', $acta->id) }}" target="_blank">
                                            Ver
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
