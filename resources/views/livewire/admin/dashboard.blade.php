<flux:main>
    <div class="flex flex-col gap-6 p-6">
        <div>
            <flux:heading size="xl" class="text-blue-navy font-bold">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1 text-text-primary">
                {{ __('Bienvenido al panel de administración.') }}
            </flux:text>
        </div>

        <flux:separator />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="flex flex-col gap-2">
                <flux:heading size="sm" class="text-text-primary">{{ __('Módulos activos') }}</flux:heading>
                <flux:text class="text-2xl font-bold">—</flux:text>
            </flux:card>
        </div>
    </div>
</flux:main>
