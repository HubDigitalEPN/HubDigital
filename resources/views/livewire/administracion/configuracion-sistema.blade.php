<div data-testid="system-settings-page" class="hub-workspace flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6">
    <header class="hub-page-header">
        <div>
            <h1 class="mt-1 hub-page-title">Configuración del sistema</h1>
        </div>
    </header>

    @if (session('configuracion-correo'))
        <div role="status" class="rounded-lg border border-bio-green/25 bg-bio-green/5 px-4 py-3 text-sm text-bio-green">
            {{ session('configuracion-correo') }}
        </div>
    @endif

    @if (session('prueba-correo'))
        <div role="status" class="rounded-lg border border-science-blue/25 bg-science-blue/5 px-4 py-3 text-sm text-science-blue">
            {{ session('prueba-correo') }}
        </div>
    @endif

    <section class="hub-panel p-5" aria-labelledby="titulo-descargas">
        <h2 id="titulo-descargas" class="hub-section-title">Descargas del catálogo</h2>
        <a href="{{ route('admin.botones') }}" wire:navigate class="mt-2 inline-block text-sm text-science-blue hover:underline">Administrar botones y permisos por perfil</a>

        <form wire:submit="guardarDescargas" class="mt-4 flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="descargasAutoservicio">Permitir descargas a usuarios de autoservicio</label>
            <flux:button type="submit" variant="primary">Guardar permiso</flux:button>
        </form>
        @if(session('configuracion-descargas'))<p data-hub-feedback="success" role="status" class="mt-3">{{ session('configuracion-descargas') }}</p>@endif
    </section>
    <section class="hub-panel p-5" aria-labelledby="titulo-correo">
        <div class="flex flex-col gap-2 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="titulo-correo" class="font-display text-xl font-semibold text-blue-navy">Correo transaccional</h2>

            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-md border border-border bg-bg-main px-3 py-1.5 text-xs font-semibold text-text-secondary">
                <span class="size-2 rounded-full {{ $configuracionGuardada ? 'bg-bio-green' : 'bg-amber-500' }}"></span>
                {{ $configuracionGuardada ? 'Administrado desde HubDigital' : 'Usando respaldo del entorno' }}
            </span>
        </div>

        <form
            x-data="{ guardando: false }"
            x-on:submit.prevent="
                if (guardando) return;
                guardando = true;
                const clave = $el.querySelector('input[name=smtp_password]');
                try {
                    await $wire.guardar(clave.value);
                } finally {
                    clave.value = '';
                    guardando = false;
                }
            "
            class="mt-5 grid gap-4 md:grid-cols-2"
            data-testid="system-mail-settings-form"
        >
            <flux:input wire:model="smtpHost" label="Servidor SMTP" required autocomplete="off" />
            <flux:input wire:model="smtpPort" type="number" min="1" max="65535" label="Puerto" required autocomplete="off" />
            <flux:input wire:model="smtpUsername" type="email" label="Usuario SMTP" required autocomplete="off" />
            <flux:input wire:model="fromAddress" type="email" label="Correo remitente" required autocomplete="off" />
            <flux:input wire:model="fromName" label="Nombre visible" required autocomplete="off" />
            <flux:input
                name="smtp_password"
                type="password"
                label="Nueva contraseña de aplicación"
                :required="! $configuracionGuardada"
                autocomplete="new-password"
                placeholder="{{ $configuracionGuardada ? 'Déjala vacía para conservar la actual' : 'Contraseña específica de aplicación' }}"
            />

            @if ($errors->has('smtpPassword'))
                <p data-hub-feedback="error" class="md:col-span-2 text-sm text-error" role="alert">{{ $errors->first('smtpPassword') }}</p>
            @endif

            <div class="md:col-span-2 flex justify-end">
                <flux:button type="submit" variant="primary" icon="check" x-bind:disabled="guardando">
                    Guardar configuración
                </flux:button>
            </div>
        </form>
    </section>

    <section class="hub-panel p-5" aria-labelledby="titulo-prueba-correo">
        <h2 id="titulo-prueba-correo" class="font-display text-lg font-semibold text-blue-navy">Probar entrega</h2>

        <form wire:submit="probar" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <flux:input wire:model="destinatarioPrueba" type="email" label="Correo destinatario" required autocomplete="off" />
                @error('destinatarioPrueba') <p data-hub-feedback="error" class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror
            </div>
            <flux:button type="submit" variant="primary" icon="paper-airplane">Enviar prueba</flux:button>
        </form>
    </section>
</div>
