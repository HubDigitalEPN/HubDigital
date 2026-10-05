<div class="flex flex-col gap-4">

    {{-- Header --}}
    <div class="flex flex-col gap-1 text-center">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Crear cuenta</h1>
        <p class="text-sm text-text-secondary">Únete al Hub Digital y gestiona tus especímenes de forma eficiente.</p>
    </div>

    {{-- Fortify centraliza validación, normalización, hash y evento Registered. --}}
    <form id="hub-register-form" method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-3" novalidate
        x-data="{
            role: @js($rolInicial),
            turnstileVerified: {{ config('services.turnstile.enabled') ? 'false' : 'true' }}
        }"
        x-on:hub-register-turnstile-passed.window="turnstileVerified = true"
        x-on:hub-register-turnstile-reset.window="turnstileVerified = false">
        @csrf
        @if (config('services.turnstile.enabled'))
            <input type="hidden" name="cf-turnstile-response" value="">
        @endif

        {{-- Selector de rol público. Los roles internos nunca se aceptan aquí. --}}
        <fieldset class="flex flex-col gap-2">
        <legend class="text-sm font-medium text-text-primary">¿Cuál es tu propósito?</legend>

        <div class="grid grid-cols-2 gap-3">

            {{-- Prestamista --}}
            <label
                x-bind:class="role === 'PRESTAMISTA'
                    ? 'border-science-blue bg-science-blue/5'
                    : 'border-border bg-surface hover:border-science-blue/40'"
                class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border-2 p-3 transition-all duration-150 focus-within:ring-2 focus-within:ring-science-blue"
            >
                <input type="radio" name="rol" value="PRESTAMISTA" x-model="role" class="sr-only" @checked($rolInicial === 'PRESTAMISTA')>
                <div
                    x-bind:class="role === 'PRESTAMISTA' ? 'bg-science-blue/15' : 'bg-bg-main'"
                    class="flex h-8 w-8 items-center justify-center rounded-lg"
                >
                    <flux:icon name="magnifying-glass" variant="outline"
                        x-bind:class="role === 'PRESTAMISTA' ? 'text-science-blue' : 'text-text-secondary'"
                        class="size-5" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold leading-tight"
                       x-bind:class="role === 'PRESTAMISTA' ? 'text-science-blue' : 'text-text-primary'">
                        Solicitante
                    </p>
                    <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                        Solicito préstamos de especímenes
                    </p>
                </div>
            </label>

            {{-- Depositante --}}
            <label
                x-bind:class="role === 'DEPOSITANTE'
                    ? 'border-science-blue bg-science-blue/5'
                    : 'border-border bg-surface hover:border-science-blue/40'"
                class="flex cursor-pointer flex-col items-center gap-1.5 rounded-lg border-2 p-3 transition-all duration-150 focus-within:ring-2 focus-within:ring-science-blue"
            >
                <input type="radio" name="rol" value="DEPOSITANTE" x-model="role" class="sr-only" @checked($rolInicial === 'DEPOSITANTE')>
                <div
                    x-bind:class="role === 'DEPOSITANTE' ? 'bg-science-blue/15' : 'bg-bg-main'"
                    class="flex h-8 w-8 items-center justify-center rounded-lg"
                >
                    <flux:icon name="building-library" variant="outline"
                        x-bind:class="role === 'DEPOSITANTE' ? 'text-science-blue' : 'text-text-secondary'"
                        class="size-5" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold leading-tight"
                       x-bind:class="role === 'DEPOSITANTE' ? 'text-science-blue' : 'text-text-primary'">
                        Depositante
                    </p>
                    <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                        Deposito material biológico
                    </p>
                </div>
            </label>

        </div>

        @error('rol')
            <p class="flex items-center gap-1 text-xs text-error">
                <flux:icon name="exclamation-circle" variant="outline" class="size-3.5 shrink-0" />
                {{ $message }}
            </p>
        @enderror
        </fieldset>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <flux:field>
                <flux:label class="font-medium text-text-primary">Nombre</flux:label>
                <flux:input
                    name="first_name"
                    type="text"
                    value="{{ old('first_name') }}"
                    placeholder="Tu nombre"
                    autocomplete="given-name"
                    autofocus
                />
                <flux:error name="first_name" />
            </flux:field>

            <flux:field>
                <flux:label class="font-medium text-text-primary">Apellido</flux:label>
                <flux:input
                    name="last_name"
                    type="text"
                    value="{{ old('last_name') }}"
                    placeholder="Tu apellido"
                    autocomplete="family-name"
                />
                <flux:error name="last_name" />
            </flux:field>

            <flux:field>
                <flux:label class="font-medium text-text-primary">Correo electrónico</flux:label>
                <flux:input
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="tu@email.com"
                    autocomplete="email"
                />
                <flux:error name="email" />
            </flux:field>
        </div>

        {{-- Datos del depositante: solo cuando el propósito es depositar material biológico.
             Alimentan el Acta recepción-depósito oficial (MEPN). --}}
        <div
            x-show="role === 'DEPOSITANTE'"
            x-collapse
            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
        >
            <flux:field>
                <flux:label class="font-medium text-text-primary">Cargo o posición</flux:label>
                <flux:input
                    name="cargo"
                    type="text"
                    value="{{ old('cargo') }}"
                    placeholder="Ej. Coordinador Técnico de Proyectos"
                    autocomplete="organization-title"
                />
                <flux:error name="cargo" />
            </flux:field>

            <flux:field>
                <flux:label class="font-medium text-text-primary">Institución o empresa</flux:label>
                <flux:input
                    name="institucion"
                    type="text"
                    value="{{ old('institucion') }}"
                    placeholder="Ej. EcoSambito C. Ltda"
                    autocomplete="organization"
                />
                <flux:error name="institucion" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:field>
                <flux:label class="font-medium text-text-primary">Contraseña</flux:label>
                <x-auth-password
                    name="password"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label class="font-medium text-text-primary">Confirmar contraseña</flux:label>
                <x-auth-password
                    name="password_confirmation"
                    description="confirmación de contraseña"
                    placeholder="Repite tu contraseña"
                    autocomplete="new-password"
                />
                <flux:error name="password_confirmation" />
            </flux:field>
        </div>

        @error('form')
            <p class="flex items-center gap-1 text-xs text-error">
                <flux:icon name="exclamation-circle" variant="outline" class="size-3.5 shrink-0" />
                {{ $message }}
            </p>
        @enderror

        <div class="grid items-end gap-3 sm:grid-cols-[minmax(18rem,1fr)_minmax(12rem,.7fr)]">
            @if (config('services.turnstile.enabled'))
                <div class="hub-turnstile flex min-w-0 flex-col gap-2" aria-label="Verificación de seguridad">
                    @if (filled(config('services.turnstile.site_key')))
                        <div
                            class="cf-turnstile min-h-[4.0625rem]"
                            data-sitekey="{{ config('services.turnstile.site_key') }}"
                            data-action="turnstile-spin-v2"
                            data-size="flexible"
                            data-theme="auto"
                            data-response-field="false"
                            data-callback="hubRegisterTurnstilePassed"
                            data-expired-callback="hubRegisterTurnstileReset"
                            data-error-callback="hubRegisterTurnstileReset"
                        ></div>
                    @else
                        <p class="rounded-md border border-error/30 bg-error/5 px-3 py-2 text-sm text-error">
                            La verificación de seguridad no está configurada. Solicita asistencia.
                        </p>
                    @endif

                    @error('cf-turnstile-response')
                        <p class="flex items-start gap-1.5 text-sm text-error" role="alert">
                            <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>
            @endif

            <flux:button
                type="submit"
                variant="primary"
                :disabled="config('services.turnstile.enabled')"
                x-bind:disabled="!turnstileVerified"
                class="w-full bg-bio-green! border-bio-green! hover:bg-bio-green/90! text-white! font-semibold disabled:cursor-not-allowed disabled:opacity-45 {{ config('services.turnstile.enabled') ? '' : 'sm:col-span-2' }}"
            >
                <span class="flex items-center justify-center gap-2">
                    Crear cuenta
                </span>
            </flux:button>
        </div>

    </form>

    {{-- Login link --}}
    <p class="text-center text-sm text-text-secondary">
        ¿Ya tiene una cuenta?
        <a href="{{ route('login') }}" wire:navigate
           class="font-semibold text-science-blue hover:underline">
            Inicie sesión aquí
        </a>
    </p>

    @if (config('services.turnstile.enabled') && filled(config('services.turnstile.site_key')))
        <script>
            window.hubRegisterTurnstilePassed = (token) => {
                const field = document.querySelector('#hub-register-form input[name="cf-turnstile-response"]');
                if (!field || !token) return;
                field.value = token;
                window.dispatchEvent(new CustomEvent('hub-register-turnstile-passed'));
            };
            window.hubRegisterTurnstileReset = () => {
                const field = document.querySelector('#hub-register-form input[name="cf-turnstile-response"]');
                if (field) field.value = '';
                window.dispatchEvent(new CustomEvent('hub-register-turnstile-reset'));
            };
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif

</div>
