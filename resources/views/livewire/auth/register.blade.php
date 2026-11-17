<div class="flex flex-col gap-4">

    {{-- Header --}}
    <div class="flex flex-col gap-1 text-center">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Crear cuenta</h1>

    </div>

    {{-- Fortify centraliza validación, normalización, hash y evento Registered. --}}
    <form id="hub-register-form" method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-3" novalidate
        x-data="{
            role: @js($rolInicial),
            turnstileVerified: {{ config('services.turnstile.enabled') ? 'false' : 'true' }}
        }"
        x-init="turnstileVerified = !$el.querySelector('[name=cf-turnstile-response]') || !!$el.querySelector('[name=cf-turnstile-response]').value"
        x-on:hub-register-turnstile-passed.window="turnstileVerified = true"
        x-on:hub-register-turnstile-reset.window="turnstileVerified = false">
        @csrf

        @if (config('services.turnstile.enabled'))
            <input type="hidden" name="cf-turnstile-response" value="">
        @endif

        <input type="hidden" name="rol" value="DEPOSITANTE">
        <p class="text-sm text-text-secondary">Perfil Depositante. Para explorar, descargar datos seleccionados y realizar análisis puedes usar el catálogo público.</p>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <flux:field>
                <flux:label class="font-medium text-text-primary">Nombre</flux:label>
                <flux:input
                    name="first_name"
                    required
                    aria-required="true"
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
                    required
                    aria-required="true"
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
                    required
                    aria-required="true"
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
                    :required="$rolInicial === 'DEPOSITANTE'"
                    x-bind:required="role === 'DEPOSITANTE'"
                    x-bind:aria-required="(role === 'DEPOSITANTE').toString()"
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
                    :required="$rolInicial === 'DEPOSITANTE'"
                    x-bind:required="role === 'DEPOSITANTE'"
                    x-bind:aria-required="(role === 'DEPOSITANTE').toString()"
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
                    required
                    minlength="8"
                    aria-required="true"

                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                />
                <flux:error name="password" />

            </flux:field>

            <flux:field>
                <flux:label class="font-medium text-text-primary">Confirmar contraseña</flux:label>
                <x-auth-password
                    name="password_confirmation"
                    required
                    minlength="8"
                    aria-required="true"
                    description="confirmación de contraseña"
                    placeholder="Repite tu contraseña"
                    autocomplete="new-password"
                />
                <flux:error name="password_confirmation" />
            </flux:field>
        </div>

        @error('form')
            <p data-hub-feedback="error" class="flex items-center gap-1 text-xs text-error">
                <flux:icon name="exclamation-circle" variant="outline" class="size-3.5 shrink-0" />
                {{ $message }}
            </p>
        @enderror

        <div class="grid items-end gap-3 sm:grid-cols-[minmax(18rem,1fr)_minmax(12rem,.7fr)]">
            <x-turnstile />

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

</div>
