<div class="flex flex-col gap-6">
    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg" class="text-text-primary dark:text-text-primary">{{ __('Iniciar sesión') }}</flux:heading>
        <flux:text class="text-text-primary dark:text-text-primary">{{ __('Acceso al panel de administración') }}</flux:text>

        <flux:separator />

        <form wire:submit="login" class="flex flex-col gap-4">
            <flux:input
                wire:model="username"
                :label="__('Usuario')"
                placeholder="admin"
                autofocus
                autocomplete="username"
            />

            <flux:input
                wire:model="password"
                type="password"
                :label="__('Contraseña')"
                placeholder="••••••••"
                autocomplete="current-password"
                viewable
            />

            @error('username')
                <flux:text class="text-error text-sm">{{ $message }}</flux:text>
            @enderror

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Entrar') }}
            </flux:button>
        </form>
    </flux:card>
</div>
