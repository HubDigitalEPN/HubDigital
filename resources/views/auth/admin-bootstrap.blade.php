<x-layouts::auth :title="__('Instalación segura')">
    <div data-testid="admin-bootstrap-page" class="space-y-6">
        <div class="space-y-2 text-center">
            <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-blue-navy/10 text-blue-navy">
                <flux:icon name="shield-check" class="size-6" />
            </span>
            <h1 class="font-display text-2xl font-bold text-blue-navy">Administrador inicial</h1>

        </div>

        <form
            method="POST"
            action="{{ route('admin.bootstrap.store') }}"
            data-testid="admin-bootstrap-form"
            class="space-y-5"
        >
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    name="first_name"
                    :label="__('Nombres')"
                    :value="old('first_name')"
                    required
                    autofocus
                    autocomplete="given-name"
                />
                <flux:input
                    name="last_name"
                    :label="__('Apellidos')"
                    :value="old('last_name')"
                    required
                    autocomplete="family-name"
                />
            </div>

            <flux:input
                name="email"
                type="email"
                :label="__('Correo institucional autorizado')"
                :value="old('email', $email)"
                readonly
                required
                autocomplete="username"
            />

            <flux:input
                name="bootstrap_token"
                type="password"
                :label="__('Token efímero de instalación')"
                required
                autocomplete="off"
            />

            <flux:input
                name="password"
                type="password"
                :label="__('Contraseña inicial')"
                required
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
            />

            <flux:input
                name="password_confirmation"
                type="password"
                :label="__('Confirmar contraseña')"
                required
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
            />

            @if ($errors->any())
                <div data-hub-feedback="error" role="alert" class="rounded-lg border border-error/25 bg-error/5 p-3 text-sm text-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <flux:button
                type="submit"
                variant="primary"
                class="w-full"
                data-testid="admin-bootstrap-submit"
            >
                Crear administrador
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
