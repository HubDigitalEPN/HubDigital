<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-1 text-center">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Iniciar Sesión</h1>
        <p class="text-sm text-text-secondary">Accede a tu cuenta del laboratorio</p>
    </div>

    {{-- Form --}}
    {{--
        El POST lo procesa Laravel Fortify. Esto es importante: el pipeline de
        Fortify regenera la sesión, aplica el rate limit y desvía al reto TOTP
        cuando la cuenta tiene 2FA. No autenticar desde un método Livewire.
    --}}
    <form
        id="hub-login-form"
        method="POST"
        action="{{ route('login.store') }}"
        class="flex flex-col gap-4"
        novalidate
        x-data="{ turnstileVerified: {{ config('services.turnstile.enabled') ? 'false' : 'true' }} }"
        x-init="turnstileVerified = !$el.querySelector('[name=cf-turnstile-response]') || !!$el.querySelector('[name=cf-turnstile-response]').value"
        x-on:hub-turnstile-passed.window="turnstileVerified = true"
        x-on:hub-turnstile-reset.window="turnstileVerified = false"
    >
        @csrf
        @if (config('services.turnstile.enabled'))
            <input type="hidden" name="cf-turnstile-response" value="">
        @endif

        <flux:field>
            <flux:label class="text-text-primary font-medium">Correo electrónico</flux:label>
            <flux:input
                name="email"
                type="email"
                value="{{ old('email') }}"
                placeholder="tu@email.com"
                autocomplete="email"
                autofocus
            />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label class="text-text-primary font-medium">Contraseña</flux:label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       class="text-xs text-science-blue hover:underline">
                        ¿Olvidó su contraseña?
                    </a>
                @endif
            </div>
            <x-auth-password
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
            />
            <flux:error name="password" />
        </flux:field>

        <div class="flex flex-col gap-1.5">
            <flux:checkbox name="remember" value="1" :checked="old('remember')" label="Recordarme en este dispositivo" />
            <p class="flex items-start gap-1 text-xs text-text-secondary">
                <flux:icon name="shield-check" variant="outline" class="mt-px size-3.5 shrink-0" />
                Mantiene tu sesión iniciada en este dispositivo. Úsalo solo en equipos personales y de confianza.
            </p>
        </div>

        <x-turnstile />

        <flux:button
            type="submit"
            variant="primary"
            x-bind:disabled="!turnstileVerified"
            class="mt-1 w-full bg-science-blue! border-science-blue! hover:bg-science-blue/90! text-white! font-semibold disabled:cursor-not-allowed disabled:opacity-45"
        >
            <span class="flex items-center justify-center gap-2">
                Iniciar Sesión
            </span>
        </flux:button>

    </form>

    {{-- Divider --}}
    <div class="relative flex items-center gap-3">
        <div class="h-px flex-1 bg-border"></div>
        <span class="text-xs text-text-secondary">o</span>
        <div class="h-px flex-1 bg-border"></div>
    </div>

    {{-- Register link --}}
    @if (Route::has('register'))
        <p class="text-center text-sm text-text-secondary">
            ¿No tiene una cuenta?
            <a href="{{ route('register') }}" wire:navigate
               class="font-semibold text-science-blue hover:underline">
                Regístrese aquí
            </a>
        </p>
    @endif



</div>
