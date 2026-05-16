<div class="space-y-6 p-6">
    <flux:heading size="xl" level="1" class="text-blue-navy font-bold font-display">Alertas de ubicación</flux:heading>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Filtros --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['activa' => 'Activas', 'resuelta' => 'Resueltas', 'ignorada' => 'Ignoradas', 'todas' => 'Todas'] as $valor => $etiqueta)
            <flux:button
                size="sm"
                :variant="$filtroEstado === $valor ? 'primary' : 'ghost'"
                wire:click="$set('filtroEstado', '{{ $valor }}')"
            >
                {{ $etiqueta }}
            </flux:button>
        @endforeach
    </div>

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-navy border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-white">Tipo de alerta</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Caja</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Estado actual</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Estado alerta</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Contexto</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Fecha</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($alertas as $alerta)
                    <tr class="hover:bg-bg-main transition-colors {{ $alerta['estado'] === 'activa' ? 'bg-error/5' : '' }}">
                        <td class="px-4 py-3">
                            <x-inventariogestioncoleccion::seguimiento-fisico.alerta-badge
                                :tipo="$alerta['tipo']"
                            />
                        </td>
                        <td class="px-4 py-3 font-medium text-text-primary">
                            {{ $alerta['cajaCodigo'] }}
                        </td>
                        <td class="px-4 py-3">
                            @if($alerta['cajaEstado'])
                                <x-inventariogestioncoleccion::seguimiento-fisico.caja-estado-badge
                                    :estado="$alerta['cajaEstado']"
                                />
                            @else
                                <span class="text-text-secondary text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                [$estadoBg, $estadoText, $estadoLabel] = match($alerta['estado']) {
                                    'activa'   => ['bg-error',   'text-white',        'Activa'],
                                    'resuelta' => ['bg-success', 'text-white',        'Resuelta'],
                                    'ignorada' => ['bg-border',  'text-text-primary', 'Ignorada'],
                                    default    => ['bg-border',  'text-text-primary', $alerta['estado']],
                                };
                            @endphp
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold {{ $estadoBg }} {{ $estadoText }}">
                                {{ $estadoLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-text-secondary max-w-xs">
                            @if(count($alerta['datosContexto']) > 0)
                                <ul class="space-y-0.5">
                                    @foreach($alerta['datosContexto'] as $k => $v)
                                        <li><span class="font-medium text-text-primary">{{ ucfirst($k) }}:</span> {{ ucfirst($v) }}</li>
                                    @endforeach
                                </ul>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-text-secondary whitespace-nowrap">
                            {{ $alerta['generadaEn'] }}
                        </td>
                        <td class="px-4 py-3">
                            @if($alerta['estado'] === 'activa')
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        wire:click="abrirResolverModal('{{ $alerta['id'] }}')"
                                    >
                                        Resolver
                                    </flux:button>
                                    <flux:tooltip content="Ignorar sin resolver">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="ignorar('{{ $alerta['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="ignorar('{{ $alerta['id'] }}')"
                                        >
                                            Ignorar
                                        </flux:button>
                                    </flux:tooltip>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-text-primary">
                            No hay alertas en este estado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal: Resolver alerta --}}
    <flux:modal wire:model="showResolverModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Resolver alerta</flux:heading>
            <p class="text-sm text-text-primary">Describe la acción tomada para resolver esta alerta.</p>

            <flux:field>
                <flux:label>Motivo de resolución</flux:label>
                <flux:textarea
                    wire:model="motivoResolucion"
                    rows="3"
                    placeholder="Ej: Se verificó físicamente la ubicación de la caja y se corrigió el registro..."
                />
                <flux:error name="motivoResolucion" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showResolverModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="resolver" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="resolver">Confirmar resolución</span>
                    <span wire:loading wire:target="resolver">Guardando…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
