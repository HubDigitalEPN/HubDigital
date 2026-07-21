<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    {{-- Invitación a activar el rol complementario. Vive aquí y no en el layout:
         es contenido del panel, no del armazón, y antes salía en todas las pantallas. --}}
    <x-banner-activar-rol />

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Mis préstamos</h1>
        <p class="text-sm text-text-secondary">Bienvenido, {{ auth()->user()->name }}</p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-science-blue/10">
                    <flux:icon name="document-text" variant="outline" class="size-5 text-science-blue" />
                </div>
                <div>
                    <p class="text-xs text-text-secondary">Mis solicitudes</p>
                    <p class="text-xl font-semibold text-text-primary">{{ $statTotal }}</p>
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
                    <p class="text-xl font-semibold text-text-primary">{{ $statAprobadas }}</p>
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
                    <p class="text-xl font-semibold text-text-primary">{{ $statPendientes }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-1 rounded-lg border border-border bg-surface p-6 shadow-sm">
        <div class="flex h-full flex-col items-start gap-4">
            <h2 class="text-xl font-semibold text-text-primary">Accesos rápidos</h2>
            <div class="flex flex-wrap gap-3">
                <flux:button href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate variant="primary" icon="clipboard-document-list">
                    Ver mis solicitudes
                </flux:button>
                <flux:button href="{{ route('prestamos.investigador.solicitud.crear') }}" wire:navigate variant="ghost" icon="plus">
                    Nueva solicitud
                </flux:button>
            </div>
        </div>
    </div>

</div>
