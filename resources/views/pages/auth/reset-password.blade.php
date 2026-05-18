<x-layouts::auth :title="__('Restablecer contraseña')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Restablecer contraseña')" :description="__('Ingresa tu nueva contraseña a continuación')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Correo electrónico')"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                name="password"
                :label="__('Nueva contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Nueva contraseña')"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Confirmar contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirmar contraseña')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Restablecer contraseña') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
