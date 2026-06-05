<div class="space-y-6 p-4 sm:p-6" wire:poll.5s="refrescar">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Monitoreo IoT</flux:heading>
        <div class="flex items-center gap-2 text-xs text-text-secondary">
            <span class="inline-block size-2 rounded-full bg-success animate-pulse"></span>
            Actualizando cada 5s
        </div>
    </div>

    @if($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle">{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Resumen de estados --}}
    @if(count($resumenEstados) > 0)
        @php
            $estadoLabels = [
                'en_gabinete'             => ['label' => 'En gabinete',          'bg' => 'bg-success', 'text' => 'text-white'],
                'en_transito'             => ['label' => 'En tránsito',           'bg' => 'bg-warning', 'text' => 'text-text-primary'],
                'extraccion_prolongada'   => ['label' => 'Extracción prolongada', 'bg' => 'bg-warning', 'text' => 'text-text-primary'],
                'pendiente_clasificacion' => ['label' => 'Pendiente',             'bg' => 'bg-info',    'text' => 'text-white'],
                'ubicacion_incorrecta'    => ['label' => 'Ubic. incorrecta',      'bg' => 'bg-error',   'text' => 'text-white'],
                'extraviada'              => ['label' => 'Extraviada',            'bg' => 'bg-error',   'text' => 'text-white'],
            ];
        @endphp
        <div class="flex flex-wrap gap-3">
            @foreach($resumenEstados as $estado => $count)
                @php $cfg = $estadoLabels[$estado] ?? ['label' => $estado, 'bg' => 'bg-border', 'text' => 'text-text-primary']; @endphp
                <div class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 shadow-sm">
                    <span class="inline-flex items-center justify-center rounded min-w-[1.5rem] px-1.5 py-0.5 text-xs font-bold {{ $cfg['bg'] }} {{ $cfg['text'] }}">
                        {{ $count }}
                    </span>
                    <span class="text-sm text-text-primary">{{ $cfg['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Grid de gabinetes --}}
    @forelse($gabinetes as $gabinete)
        <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">{{ $gabinete['codigo'] }} — {{ $gabinete['nombre'] }}</flux:heading>
                    <p class="text-xs text-text-secondary">
                        {{ count($gabinete['ranuras']) }} ranuras configuradas de {{ $gabinete['totalRanuras'] }}
                    </p>
                </div>
                <flux:button
                    size="sm"
                    variant="primary"
                    icon="cog-6-tooth"
                    style="color: white;"
                    class="w-full sm:w-auto"
                    :href="route('inventario.gabinetes.show', $gabinete['id'])"
                    wire:navigate
                >
                    Configurar
                </flux:button>
            </div>

            @if(count($gabinete['ranuras']) > 0)
                <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(2.75rem, 1fr))">
                    @foreach($gabinete['ranuras'] as $ranura)
                        <x-inventariogestioncoleccion::seguimiento-fisico.ranura-slot
                            :ranura="$ranura"
                            :caja="$ranura['cajaActual']"
                        />
                    @endforeach
                </div>
            @else
                <p class="text-sm text-text-primary py-2">
                    Sin ranuras. <a :href="route('inventario.gabinetes.show', $gabinete['id'])" class="text-science-blue underline" wire:navigate>Configurar gabinete</a>
                </p>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-border p-12 text-center">
            <flux:icon name="archive-box" class="mx-auto size-12 text-text-secondary mb-3" />
            <p class="text-text-primary">No hay gabinetes registrados.</p>
            <flux:button class="mt-4" :href="route('inventario.gabinetes')" wire:navigate>
                Ir a gabinetes
            </flux:button>
        </div>
    @endforelse
</div>
