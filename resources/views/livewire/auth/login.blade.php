<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-1 text-center">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Iniciar Sesión</h1>
        <p class="text-sm text-text-secondary">Accede a tu cuenta del laboratorio</p>
    </div>

    {{-- Form --}}
    <form wire:submit="submit" class="flex flex-col gap-4" novalidate>

        <flux:field>
            <flux:label class="text-text-primary font-medium">Correo electrónico</flux:label>
            <flux:input
                wire:model="email"
                type="email"
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
            <flux:input
                wire:model="password"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
                viewable
            />
            <flux:error name="password" />
        </flux:field>

        <div class="flex flex-col gap-1.5">
            <flux:checkbox wire:model="remember" label="Recordarme en este dispositivo" />
            <p class="flex items-start gap-1 text-xs text-text-secondary">
                <flux:icon name="shield-check" variant="outline" class="mt-px size-3.5 shrink-0" />
                Mantiene tu sesión iniciada en este dispositivo. Úsalo solo en equipos personales y de confianza.
            </p>
        </div>

        <flux:button
            type="submit"
            variant="primary"
            class="mt-1 w-full bg-bio-green! border-bio-green! hover:bg-bio-green/90! text-white! font-semibold"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove class="flex items-center justify-center gap-2">
                Iniciar Sesión
            </span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                Iniciando sesión…
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
