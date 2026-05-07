<div class="p-6 space-y-6">
    <div class="flex items-center gap-3">
        <flux:button
            icon="arrow-left"
            variant="ghost"
            size="sm"
            :href="route('admin.inventario.gabinetes')"
            wire:navigate
        />
        <div>
            <flux:heading size="xl" level="1">
                {{ $gabinete['codigo'] ?? '' }} — {{ $gabinete['nombre'] ?? '' }}
            </flux:heading>
            <p class="text-sm text-text-secondary">{{ count($ranuras) }} / {{ $gabinete['totalRanuras'] ?? 0 }} ranuras configuradas</p>
        </div>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">Ranuras</flux:heading>
            @if(count($ranuras) < ($gabinete['totalRanuras'] ?? 0))
                <flux:button
                    icon="plus"
                    size="sm"
                    variant="primary"
                    wire:click="$set('showAgregarRanura', true)"
                >
                    Agregar Ranura
                </flux:button>
            @endif
        </div>

        @if(count($ranuras) > 0)
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                @foreach($ranuras as $ranura)
                    <x-inventariogestioncoleccion::seguimiento-fisico.ranura-slot
                        :ranura="$ranura"
                        :caja="null"
                    />
                @endforeach
            </div>
        @else
            <p class="text-sm text-text-secondary py-4 text-center">
                No hay ranuras configuradas. Agrega la primera ranura.
            </p>
        @endif
    </div>

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-bg-main border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">#</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Familia Taxonómica Esperada</th>
                    <th class="px-4 py-3 text-left font-medium text-text-secondary">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($ranuras as $ranura)
                    <tr class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-medium text-text-primary">{{ $ranura['numeroRanura'] }}</td>
                        <td class="px-4 py-3 text-text-secondary">
                            {{ $ranura['familiaTaxonomicaEsperadaId'] ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($ranura['activa'])
                                <flux:badge color="green">Activa</flux:badge>
                            @else
                                <flux:badge color="zinc">Inactiva</flux:badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-text-secondary">Sin ranuras.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model="showAgregarRanura" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg">Agregar Ranura</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Número de ranura</flux:label>
                <flux:input type="number" wire:model="numeroRanura" min="1" :max="$gabinete['totalRanuras'] ?? 25" />
                <flux:error name="numeroRanura" />
            </flux:field>

            <flux:field>
                <flux:label>Familia taxonómica esperada <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="familiaTaxonomicaEsperadaId" placeholder="ej. Cerambycidae" />
                <flux:description>Identificador de la familia taxonómica asignada a esta ranura.</flux:description>
                <flux:error name="familiaTaxonomicaEsperadaId" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showAgregarRanura', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="agregarRanura" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="agregarRanura">Agregar</span>
                    <span wire:loading wire:target="agregarRanura">Agregando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
