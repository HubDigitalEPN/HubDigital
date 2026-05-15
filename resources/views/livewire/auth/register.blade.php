<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-col gap-1 text-center">
        <h1 class="font-display text-2xl font-bold text-blue-navy">Crear cuenta</h1>
        <p class="text-sm text-text-secondary">Únete al Hub Digital y gestiona tus especímenes de forma eficiente.</p>
    </div>

    {{-- Role selector — Alpine maneja el toggle visual sin round-trips al servidor --}}
    <div
        x-data="{ role: $wire.entangle('role') }"
        class="flex flex-col gap-2"
    >
        <p class="text-sm font-medium text-text-primary">¿Cuál es tu propósito?</p>

        <div class="grid grid-cols-2 gap-3">

            {{-- Prestamista --}}
            <button
                type="button"
                x-on:click="role = 'prestamista'"
                x-bind:class="role === 'prestamista'
                    ? 'border-science-blue bg-science-blue/5'
                    : 'border-border bg-surface hover:border-science-blue/40'"
                class="flex cursor-pointer flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all duration-150"
            >
                <div
                    x-bind:class="role === 'prestamista' ? 'bg-science-blue/15' : 'bg-bg-main'"
                    class="flex h-10 w-10 items-center justify-center rounded-lg"
                >
                    <flux:icon name="magnifying-glass" variant="outline"
                        x-bind:class="role === 'prestamista' ? 'text-science-blue' : 'text-text-secondary'"
                        class="size-5" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold leading-tight"
                       x-bind:class="role === 'prestamista' ? 'text-science-blue' : 'text-text-primary'">
                        Prestamista
                    </p>
                    <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                        Solicito préstamos de especímenes
                    </p>
                </div>
            </button>

            {{-- Depositante --}}
            <button
                type="button"
                x-on:click="role = 'depositante'"
                x-bind:class="role === 'depositante'
                    ? 'border-science-blue bg-science-blue/5'
                    : 'border-border bg-surface hover:border-science-blue/40'"
                class="flex cursor-pointer flex-col items-center gap-2.5 rounded-lg border-2 p-4 transition-all duration-150"
            >
                <div
                    x-bind:class="role === 'depositante' ? 'bg-science-blue/15' : 'bg-bg-main'"
                    class="flex h-10 w-10 items-center justify-center rounded-lg"
                >
                    <flux:icon name="building-library" variant="outline"
                        x-bind:class="role === 'depositante' ? 'text-science-blue' : 'text-text-secondary'"
                        class="size-5" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold leading-tight"
                       x-bind:class="role === 'depositante' ? 'text-science-blue' : 'text-text-primary'">
                        Depositante
                    </p>
                    <p class="mt-0.5 text-xs leading-snug text-text-secondary">
                        Deposito material biológico
                    </p>
                </div>
            </button>

        </div>

        @error('role')
            <p class="flex items-center gap-1 text-xs text-error">
                <flux:icon name="exclamation-circle" variant="outline" class="size-3.5 shrink-0" />
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- Form --}}
    <form wire:submit="submit" class="flex flex-col gap-4" novalidate>

        <flux:field>
            <flux:label class="font-medium text-text-primary">Nombre completo</flux:label>
            <flux:input
                wire:model="name"
                type="text"
                placeholder="Nombre y apellido"
                autocomplete="name"
                autofocus
            />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label class="font-medium text-text-primary">Correo electrónico</flux:label>
            <flux:input
                wire:model="email"
                type="email"
                placeholder="tu@email.com"
                autocomplete="email"
            />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label class="font-medium text-text-primary">Contraseña</flux:label>
            <flux:input
                wire:model="password"
                type="password"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
                viewable
            />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label class="font-medium text-text-primary">Confirmar contraseña</flux:label>
            <flux:input
                wire:model="password_confirmation"
                type="password"
                placeholder="Repite tu contraseña"
                autocomplete="new-password"
                viewable
            />
            <flux:error name="password_confirmation" />
        </flux:field>

        <flux:button
            type="submit"
            variant="primary"
            class="mt-1 w-full bg-bio-green! border-bio-green! hover:bg-bio-green/90! text-white! font-semibold"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove class="flex items-center justify-center gap-2">
                Crear cuenta
            </span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <flux:icon name="arrow-path" variant="outline" class="size-4 animate-spin" />
                Creando cuenta…
            </span>
        </flux:button>

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
