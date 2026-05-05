<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

        {{-- Page heading --}}
        <div class="flex flex-col gap-1">
            <h1 class="font-display text-2xl font-bold text-blue-navy">Dashboard</h1>
            <p class="text-sm text-text-secondary">
                Bienvenido, {{ auth()->user()->name }}
            </p>
        </div>

        {{-- Stat cards --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                        <flux:icon name="document-text" variant="outline" class="size-5 text-science-blue" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Solicitudes</p>
                        <p class="text-xl font-semibold text-text-primary">—</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-bio-green/10">
                        <flux:icon name="check-circle" variant="outline" class="size-5 text-bio-green" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Aprobadas</p>
                        <p class="text-xl font-semibold text-text-primary">—</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                        <flux:icon name="clock" variant="outline" class="size-5 text-warning" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Pendientes</p>
                        <p class="text-xl font-semibold text-text-primary">—</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main content area --}}
        <div class="flex-1 rounded-lg border border-border bg-surface p-6 shadow-sm">
            <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-bg-main">
                    <flux:icon name="squares-2x2" variant="outline" class="size-7 text-text-secondary" />
                </div>
                <p class="text-base font-medium text-text-primary">Panel en construcción</p>
                <p class="text-sm text-text-secondary">
                    Las funciones del módulo estarán disponibles próximamente.
                </p>
            </div>
        </div>

    </div>
</x-layouts::app>
