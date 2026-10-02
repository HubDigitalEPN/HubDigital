<x-layouts::auth title="Confirmar contraseña">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Confirmar contraseña"
            description="Esta sección requiere confirmar tu contraseña antes de continuar."
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-auth-password
                name="password"
                label="Contraseña"
                required
                autocomplete="current-password"
                placeholder="Contraseña"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                Confirmar
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
