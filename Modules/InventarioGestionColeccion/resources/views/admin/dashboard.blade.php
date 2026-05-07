<div class="p-6 space-y-6" wire:poll.5s="refrescar">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">Monitoreo IoT</flux:heading>
        <div class="flex items-center gap-2 text-xs text-text-secondary">
            <span class="inline-block size-2 rounded-full bg-success animate-pulse"></span>
            Actualizando cada 5s
        </div>
    </div>

    {{-- Resumen de estados --}}
    @if(count($resumenEstados) > 0)
        @php
            $estadoLabels = [
                'en_gabinete'             => ['label' => 'En Gabinete',          'color' => 'green'],
                'en_transito'             => ['label' => 'En Tránsito',           'color' => 'yellow'],
                'extraccion_prolongada'   => ['label' => 'Extracción Prolongada', 'color' => 'yellow'],
                'pendiente_clasificacion' => ['label' => 'Pendiente',             'color' => 'blue'],
                'ubicacion_incorrecta'    => ['label' => 'Ubic. Incorrecta',      'color' => 'red'],
                'extraviada'              => ['label' => 'Extraviada',            'color' => 'red'],
            ];
        @endphp
        <div class="flex flex-wrap gap-3">
            @foreach($resumenEstados as $estado => $count)
                @php $cfg = $estadoLabels[$estado] ?? ['label' => $estado, 'color' => 'zinc']; @endphp
                <div class="flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 shadow-sm">
                    <flux:badge :color="$cfg['color']" size="sm">{{ $count }}</flux:badge>
                    <span class="text-sm text-text-secondary">{{ $cfg['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Grid de gabinetes --}}
    @forelse($gabinetes as $gabinete)
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg" level="2">{{ $gabinete['codigo'] }} — {{ $gabinete['nombre'] }}</flux:heading>
                    <p class="text-xs text-text-secondary">
                        {{ count($gabinete['ranuras']) }} ranuras configuradas de {{ $gabinete['totalRanuras'] }}
                    </p>
                </div>
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="cog-6-tooth"
                    :href="route('admin.inventario.gabinetes.show', $gabinete['id'])"
                    wire:navigate
                >
                    Configurar
                </flux:button>
            </div>

            @if(count($gabinete['ranuras']) > 0)
                <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-13 gap-1.5">
                    @foreach($gabinete['ranuras'] as $ranura)
                        <x-inventariogestioncoleccion::seguimiento-fisico.ranura-slot
                            :ranura="$ranura"
                            :caja="$ranura['cajaActual']"
                        />
                    @endforeach
                </div>
            @else
                <p class="text-sm text-text-secondary py-2">
                    Sin ranuras. <a :href="route('admin.inventario.gabinetes.show', $gabinete['id'])" class="text-science-blue underline" wire:navigate>Configurar gabinete</a>
                </p>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-border p-12 text-center">
            <flux:icon name="archive-box" class="mx-auto size-12 text-text-secondary mb-3" />
            <p class="text-text-secondary">No hay gabinetes registrados.</p>
            <flux:button class="mt-4" :href="route('admin.inventario.gabinetes')" wire:navigate>
                Ir a Gabinetes
            </flux:button>
        </div>
    @endforelse
</div>
