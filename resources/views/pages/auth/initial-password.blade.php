<x-layouts::auth title="Cambiar contraseña inicial">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Cambiar contraseña inicial" />

        <form method="POST" action="{{ route('password.initial.update') }}" class="flex flex-col gap-6">
            @csrf

            <x-auth-password name="current_password" label="Contraseña actual" description="contraseña actual" required />
            <x-auth-password name="password" label="Nueva contraseña" description="nueva contraseña" required />
            <x-auth-password name="password_confirmation" label="Confirmar nueva contraseña" description="confirmación de contraseña" required />

            <flux:button type="submit" variant="primary" data-test="initial-password-submit">
                Guardar
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
