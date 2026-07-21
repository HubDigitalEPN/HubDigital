<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-xl">
        <div class="mb-6">
            <flux:heading size="xl" class="font-serif text-blue-navy">Activar rol de Depositante</flux:heading>
            <flux:text class="mt-1 text-text-secondary">
                Con este rol podrás depositar material biológico en la colección. Declara tu cargo e
                institución: se usan en el Acta de recepción-depósito.
            </flux:text>
        </div>

        <form wire:submit="confirmar" class="rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6">
            <div class="grid gap-4">
                <flux:input
                    wire:model="cargo"
                    label="Cargo"
                    placeholder="Ej. Investigador titular"
                    required
                />

                <flux:input
                    wire:model="institucion"
                    label="Institución"
                    placeholder="Ej. Escuela Politécnica Nacional"
                    required
                />
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <flux:button
                    variant="ghost"
                    :href="route('dashboard')"
                    wire:navigate
                    class="w-full sm:w-auto"
                >
                    Cancelar
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check-circle"
                    class="w-full sm:w-auto"
                >
                    Activar rol
                </flux:button>
            </div>
        </form>
    </div>
</div>
