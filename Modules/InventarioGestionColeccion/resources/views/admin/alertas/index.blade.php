<div class="p-6 space-y-6">
    <flux:heading size="xl" level="1">Alertas de Ubicación</flux:heading>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Filtro por estado --}}
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
            <thead class="bg-bg-main border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Caja ID</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Estado</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Contexto</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($alertas as $alerta)
                    <tr @class([
                        'hover:bg-bg-main transition-colors',
                        'bg-red-50 dark:bg-red-950/10' => $alerta['estado'] === 'activa',
                    ])>
                        <td class="px-4 py-3">
                            <x-inventariogestioncoleccion::seguimiento-fisico.alerta-badge
                                :tipo="$alerta['tipo']"
                            />
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-text-secondary">
                            {{ substr($alerta['cajaId'], 0, 8) }}...
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $estadoCfg = match($alerta['estado']) {
                                    'activa'   => ['color' => 'red',    'label' => 'Activa'],
                                    'resuelta' => ['color' => 'green',  'label' => 'Resuelta'],
                                    'ignorada' => ['color' => 'zinc',   'label' => 'Ignorada'],
                                    default    => ['color' => 'zinc',   'label' => $alerta['estado']],
                                };
                            @endphp
                            <flux:badge :color="$estadoCfg['color']">{{ $estadoCfg['label'] }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-text-secondary max-w-xs truncate">
                            @if(count($alerta['datosContexto']) > 0)
                                {{ collect($alerta['datosContexto'])->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') }}
                            @else
                                —
                            @endif
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

                                    <flux:tooltip content="Ignorar esta alerta sin resolverla">
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
                        <td colspan="5" class="px-4 py-8 text-center text-text-secondary">
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
            <flux:heading size="lg">Resolver Alerta</flux:heading>
            <p class="text-sm text-text-secondary">
                Describe la acción tomada para resolver esta alerta.
            </p>

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
                    <span wire:loading.remove wire:target="resolver">Confirmar Resolución</span>
                    <span wire:loading wire:target="resolver">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
