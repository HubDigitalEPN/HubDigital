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
        x-init="turnstileVerified = !$el.querySelector('[name=cf-turnstile-response]') || !!$el.querySelector('[name=cf-turnstile-response]').value"
        x-on:hub-register-turnstile-passed.window="turnstileVerified = true"
        x-on:hub-register-turnstile-reset.window="turnstileVerified = false">
        @csrf
        <p class="text-xs text-text-secondary">El triángulo rojo identifica los campos obligatorios.</p>
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
                    aria-describedby="register-password-help"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                />
                <flux:error name="password" />
                <flux:description id="register-password-help">Mínimo 8 caracteres.@if(app()->isProduction()) Incluye mayúsculas, minúsculas, números y símbolos; utiliza una contraseña que no haya sido expuesta.@endif</flux:description>
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
            <p class="flex items-center gap-1 text-xs text-error">
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
