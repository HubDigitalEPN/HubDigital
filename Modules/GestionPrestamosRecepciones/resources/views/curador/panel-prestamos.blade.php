<div class="flex h-full w-full flex-1 flex-col gap-4 p-6">

    <div class="flex flex-col gap-1">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Gestión de Préstamos</h1>
        <p class="text-sm text-text-secondary">Selecciona una sección para gestionar</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('prestamos.curador.solicitudes') }}" wire:navigate
           class="flex items-center gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-science-blue">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-science-blue/10">
                <flux:icon name="clipboard-document-list" variant="outline" class="size-6 text-science-blue" />
            </div>
            <div>
                <p class="font-medium text-text-primary">Bandeja de Solicitudes</p>
                <p class="text-xs text-text-secondary">Revisar y aprobar solicitudes de préstamo</p>
            </div>
        </a>

        <a href="{{ route('prestamos.curador.actas') }}" wire:navigate
           class="flex items-center gap-4 rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-bio-green">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-bio-green/10">
                <flux:icon name="document-text" variant="outline" class="size-6 text-bio-green" />
            </div>
            <div>
                <p class="font-medium text-text-primary">Bandeja de Actas</p>
                <p class="text-xs text-text-secondary">Validar actas de devolución</p>
            </div>
        </a>
    </div>

</div>
