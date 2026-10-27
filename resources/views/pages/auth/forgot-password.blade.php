<x-layouts::auth :title="__('Recuperar contraseña')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Recuperar contraseña')" :description="__('Ingresa el correo registrado en tu cuenta para recibir el enlace de recuperación.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" data-async-auth class="flex flex-col gap-6">
            @csrf
            <p data-auth-feedback role="status" aria-live="polite" class="text-sm text-text-secondary" hidden></p>

            <flux:input
                name="email"
                :label="__('Correo electrónico')"
                type="email"
                :value="old('email')"
                autocomplete="email"
                required
                autofocus
                placeholder="correo@ejemplo.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Enviar enlace de recuperación') }}
            </flux:button>
        </form>

        <p class="text-sm text-text-secondary">Revisa también Spam o Correo no deseado. Un correo alterno solo recibe el enlace si es el que registraste en tu cuenta. Si solicitas otro enlace, utiliza el más reciente.</p>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('O vuelve a') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('iniciar sesión') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
