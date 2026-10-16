<div data-testid="admin-users-page" class="hub-workspace flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6">
    <header class="hub-page-header">
        <div>
            <h1 class="mt-1 hub-page-title">Usuarios y perfiles</h1>
        </div>
        <flux:button
            wire:click="alternarFormulario"
            variant="primary"
            icon="user-plus"
            data-testid="admin-user-create-toggle"
        >
            {{ $mostrarFormulario ? 'Cerrar formulario' : 'Crear usuario' }}
        </flux:button>
    </header>

    @if (session('usuario-creado'))
        <div role="status" class="rounded-lg border border-bio-green/25 bg-bio-green/5 px-4 py-3 text-sm text-bio-green">
            {{ session('usuario-creado') }}
        </div>
    @endif

    @if (session('usuario-actualizado'))
        <div role="status" class="rounded-lg border border-science-blue/25 bg-science-blue/5 px-4 py-3 text-sm text-science-blue">
            {{ session('usuario-actualizado') }}
        </div>
    @endif

    @if ($mostrarFormulario)
        <section class="hub-panel p-5">
            <div class="mb-5">
                <h2 class="font-display text-lg font-semibold text-blue-navy">Nueva cuenta</h2>
                <p class="text-sm text-text-secondary">
                    Las cuentas internas deben usar un correo institucional EPN. Las cuentas creadas aqui quedan habilitadas de inmediato. La clave inicial debera cambiarse en el primer acceso.
                </p>
            </div>

            <form
                x-data="{ enviando: false }"
                x-on:submit.prevent="
                    if (enviando) return;
                    enviando = true;
                    const clave = $el.querySelector('input[name=password]');
                    const confirmacion = $el.querySelector('input[name=password_confirmation]');
                    try {
                        await $wire.crear(clave.value, confirmacion.value);
                    } finally {
                        clave.value = '';
                        confirmacion.value = '';
                        enviando = false;
                    }
                "
                data-testid="admin-user-create-form"
                class="grid gap-4 md:grid-cols-2"
            >
                <flux:input wire:model="first_name" name="first_name" label="Nombres" required autocomplete="off" />
                <flux:input wire:model="last_name" name="last_name" label="Apellidos" required autocomplete="off" />
                <flux:input wire:model="email" name="email" type="email" label="Correo electrónico" required autocomplete="off" />
                <flux:select wire:model.live="rol" name="rol" label="Rol" required>
                    @foreach ($roles as $opcionRol)
                        <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="cargo" name="cargo" label="Cargo o función" :required="$rol === \App\Enums\RolUsuario::DEPOSITANTE->value" autocomplete="off" />
                <flux:input wire:model="institucion" name="institucion" label="Institución" :required="$rol === \App\Enums\RolUsuario::DEPOSITANTE->value" autocomplete="off" />
                <flux:input name="password" type="password" label="Contraseña inicial" required minlength="12" maxlength="72" autocomplete="new-password" />
                <flux:input name="password_confirmation" type="password" label="Confirmar contraseña" required minlength="12" maxlength="72" autocomplete="new-password" />
                <p class="md:col-span-2 text-xs text-text-secondary">
                    Usa al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos. El sistema exigira cambiar la contraseña inicial al primer ingreso.
                </p>

                @if ($errors->any())
                    <div role="alert" class="md:col-span-2 rounded-lg border border-error/25 bg-error/5 p-3 text-sm text-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="md:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary" x-bind:disabled="enviando" data-testid="admin-user-create-submit">
                        Guardar usuario
                    </flux:button>
                </div>
            </form>
        </section>
    @endif

    @if ($usuarioEnEdicion)
        <section class="hub-panel border-science-blue/30 p-5">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-display text-lg font-semibold text-blue-navy">Editar perfil de usuario</h2>
                    <p class="text-sm text-text-secondary">Actualiza el correo, el perfil y los roles autorizados.</p>
                </div>
                <flux:button wire:click="cancelarEdicion" variant="ghost" icon="x-mark">Cancelar</flux:button>
            </div>

            <form wire:submit="actualizar" class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="edicionFirstName" label="Nombres" required autocomplete="off" />
                <flux:input wire:model="edicionLastName" label="Apellidos" required autocomplete="off" />
                <flux:input wire:model="edicionEmail" type="email" label="Correo electronico" required autocomplete="off" />
                <div class="md:col-span-2 rounded-lg border border-border bg-bg-main/45 p-4">
                    <p class="text-sm font-semibold text-blue-navy">Roles asignados</p>
                    <p class="mt-1 text-xs leading-5 text-text-secondary">Los roles internos EPN son exclusivos. Los perfiles externos pueden combinar Depositante y Solicitante.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($roles as $opcionRol)
                            <label class="flex min-h-11 items-center gap-3 rounded-md border border-border bg-surface px-3 py-2 text-sm text-text-primary">
                                <input type="checkbox" value="{{ $opcionRol->value }}" wire:model="edicionRoles" class="size-4 rounded border-border text-science-blue focus:ring-science-blue" />
                                <span>{{ $opcionRol->etiqueta() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('edicionRoles') <p class="mt-2 text-xs text-error">{{ $message }}</p> @enderror
                </div>
                <flux:select wire:model="edicionRol" label="Rol operativo principal" required>
                    @foreach ($roles as $opcionRol)
                        <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="edicionCargo" label="Cargo o función" :required="in_array(\App\Enums\RolUsuario::DEPOSITANTE->value, $edicionRoles, true)" autocomplete="off" />
                <flux:input wire:model="edicionInstitucion" class="md:col-span-2" label="Institución" :required="in_array(\App\Enums\RolUsuario::DEPOSITANTE->value, $edicionRoles, true)" autocomplete="off" />

                @if ($errors->any())
                    <div role="alert" class="md:col-span-2 rounded-lg border border-error/25 bg-error/5 p-3 text-sm text-error">{{ $errors->first() }}</div>
                @endif

                <div class="md:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">Guardar cambios</flux:button>
                </div>
            </form>
        </section>
    @endif

    @if($usuarioPorEliminar)
        <section class="hub-panel border-error/30 p-5" role="alertdialog" aria-label="Confirmar eliminacion de usuario" aria-modal="true">
            <h2 class="font-display text-lg font-semibold text-error">Eliminar cuenta definitivamente</h2>
            <p class="mt-2 text-sm text-text-secondary">Se eliminara la cuenta de la base de datos. Si tiene depositos, archivos en R2 o prestamos historicos, el sistema protegera la trazabilidad y bloqueara esta accion.</p>
            @error('usuarioPorEliminar') <p class="mt-2 text-sm text-error">{{ $message }}</p> @enderror
            <div class="mt-4 flex gap-2">
                <flux:button wire:click="cancelarEliminacion" variant="ghost">Cancelar</flux:button>
                <flux:button wire:click="eliminarDefinitivamente" variant="danger">Eliminar definitivamente</flux:button>
            </div>
        </section>
    @endif

    <x-interactive-grid :rows="$usuarios" label="Usuarios y perfiles" :columns="['Persona', 'Perfil', 'Rol', 'Estado', 'Alta', 'Acciones']">
        <x-slot:filters>
            <flux:select x-model="filterValues.filtroRol" data-grid-filter="filtroRol" label="Rol" aria-label="Filtrar por rol" data-testid="admin-user-role-filter">
                <flux:select.option value="">Todos los roles</flux:select.option>
                @foreach ($roles as $opcionRol)
                    <flux:select.option value="{{ $opcionRol->value }}">{{ $opcionRol->etiqueta() }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:filters>
        <thead><tr><x-grid-heading field="persona">Persona</x-grid-heading><x-grid-heading field="perfil">Perfil</x-grid-heading><x-grid-heading>Rol</x-grid-heading><x-grid-heading field="estado">Estado</x-grid-heading><x-grid-heading field="alta">Alta</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
                <tbody class="divide-y divide-border">
                    @forelse ($usuarios as $usuario)
                        <tr data-testid="admin-user-row" class="hover:bg-bg-main/50">
                            <td class="px-4 py-4">
                                <p class="font-medium text-text-primary">{{ $usuario->name }}</p>
                                <p class="text-xs text-text-secondary">{{ $usuario->email }}</p>
                            </td>
                            <td class="px-4 py-4 text-text-secondary">
                                <p>{{ $usuario->cargo ?: 'Sin cargo registrado' }}</p>
                                <p class="text-xs">{{ $usuario->institucion ?: 'Sin institución registrada' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($usuario->rolesAsignados() as $rolAsignado)
                                        <span class="rounded-full bg-blue-navy/8 px-2.5 py-1 text-xs font-medium text-blue-navy">
                                            {{ $rolAsignado->etiqueta() }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $usuario->hasVerifiedEmail() ? 'text-bio-green' : 'text-amber-700' }}">
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $usuario->hasVerifiedEmail() ? 'Verificado' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-text-secondary">
                                {{ $usuario->created_at?->format('d/m/Y') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if (! $usuario->hasVerifiedEmail())
                                        <flux:button wire:click="reenviarVerificacion('{{ $usuario->id }}')" variant="ghost" size="sm" icon="envelope" title="Reenviar verificación">Verificar</flux:button>
                                    @endif
                                    <flux:button wire:click="editar('{{ $usuario->id }}')" variant="ghost" size="sm" icon="pencil-square">Editar</flux:button>
                                    @if($usuario->id !== auth()->id())
                                        <flux:button wire:click="confirmarEliminacion('{{ $usuario->id }}')" variant="ghost" size="sm" icon="trash" aria-label="Eliminar usuario">Eliminar</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-text-secondary">
                                No se encontraron usuarios con estos criterios.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
    </x-interactive-grid>
</div>
