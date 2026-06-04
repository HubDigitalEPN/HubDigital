@php
    $colorPrioridad = function (string $prioridad): string {
        return match ($prioridad) {
            'critica' => 'bg-error',
            'recomendada' => 'bg-warning',
            default => 'bg-text-secondary',
        };
    };
@endphp

<div class="space-y-6 p-4 sm:p-6 max-w-5xl">
    <div>
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Configuración de columnas</flux:heading>
        <p class="text-sm text-text-secondary mt-1">
            Cambia la prioridad de cada columna por pantalla. Los cambios son globales: cualquier curador autorizado puede modificarlos, y todos los usuarios verán la nueva clasificación.
        </p>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    {{-- Leyenda --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-4">
        <div class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-2">Significado de cada prioridad</div>
        <div class="grid gap-2 text-sm">
            <div class="flex items-start gap-3">
                <span class="inline-block size-3 rounded-full bg-error mt-1.5 shrink-0"></span>
                <div><span class="font-medium text-text-primary">Crítica</span> — requerida para que el espécimen sea publicable a GBIF. Mostrada por defecto en las listas y resaltada en rojo.</div>
            </div>
            <div class="flex items-start gap-3">
                <span class="inline-block size-3 rounded-full bg-warning mt-1.5 shrink-0"></span>
                <div><span class="font-medium text-text-primary">Recomendada</span> — GBIF la considera de alta calidad. Mejora el descubrimiento de los datos. Mostrada en naranja.</div>
            </div>
            <div class="flex items-start gap-3">
                <span class="inline-block size-3 rounded-full bg-text-secondary mt-1.5 shrink-0"></span>
                <div><span class="font-medium text-text-primary">Opcional</span> — información descriptiva o suplementaria. Mostrada en gris.</div>
            </div>
        </div>
        <p class="text-xs text-text-secondary mt-3 italic">
            La prioridad solo afecta el color y la visibilidad por defecto en la UI. El exportador a Darwin Core Archive sigue su lista fija de columnas independientemente.
        </p>
    </div>

    {{-- Por cada pantalla --}}
    @foreach($pantallasMeta as $pantallaKey => $meta)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-5 py-4 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">{{ $meta['titulo'] }}</flux:heading>
                <p class="text-xs text-text-secondary mt-1">{{ $meta['descripcion'] }}</p>
            </div>

            {{-- Escritorio --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-blue-navy text-white">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Etiqueta</th>
                            <th class="px-4 py-3 text-left font-medium">Clave</th>
                            <th class="px-4 py-3 text-left font-medium">Grupo</th>
                            <th class="px-4 py-3 text-left font-medium">Prioridad actual</th>
                            <th class="px-4 py-3 text-left font-medium">Cambiar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($pantallas[$pantallaKey] ?? [] as $col)
                            <tr class="hover:bg-bg-main transition-colors align-top">
                                <td class="px-4 py-3 text-text-primary">{{ $col['etiqueta'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ $col['clave'] }}</td>
                                <td class="px-4 py-3 text-text-secondary text-xs">{{ $col['grupo'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block size-2.5 rounded-full {{ $colorPrioridad($col['prioridad']) }}"></span>
                                        <span class="text-sm capitalize">{{ $col['prioridad'] }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(['critica', 'recomendada', 'opcional'] as $opcion)
                                            @if($opcion !== $col['prioridad'])
                                                <button type="button"
                                                        wire:click="cambiar('{{ $pantallaKey }}', '{{ $col['clave'] }}', '{{ $opcion }}')"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center gap-1 rounded-full border border-border bg-surface px-2.5 py-0.5 text-xs hover:bg-bg-main">
                                                    <span class="inline-block size-2 rounded-full {{ $colorPrioridad($opcion) }}"></span>
                                                    {{ ucfirst($opcion) }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Móvil --}}
            <div class="md:hidden divide-y divide-border">
                @foreach($pantallas[$pantallaKey] ?? [] as $col)
                    <div class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-medium text-text-primary">{{ $col['etiqueta'] }}</div>
                                <div class="font-mono text-xs text-text-secondary">{{ $col['clave'] }}</div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 shrink-0">
                                <span class="inline-block size-2.5 rounded-full {{ $colorPrioridad($col['prioridad']) }}"></span>
                                <span class="text-xs capitalize">{{ $col['prioridad'] }}</span>
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-1 pt-1">
                            @foreach(['critica', 'recomendada', 'opcional'] as $opcion)
                                @if($opcion !== $col['prioridad'])
                                    <button type="button"
                                            wire:click="cambiar('{{ $pantallaKey }}', '{{ $col['clave'] }}', '{{ $opcion }}')"
                                            class="inline-flex items-center gap-1 rounded-full border border-border bg-surface px-2.5 py-1 text-xs hover:bg-bg-main">
                                        <span class="inline-block size-2 rounded-full {{ $colorPrioridad($opcion) }}"></span>
                                        Cambiar a {{ ucfirst($opcion) }}
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
