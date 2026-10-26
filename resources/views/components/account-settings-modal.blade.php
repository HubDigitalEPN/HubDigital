<flux:modal name="account-settings" class="hub-account-settings-modal w-full max-w-5xl" x-data="{ section: 'profile' }">
    <div class="space-y-6">
        <header class="flex items-start justify-between gap-5 border-b border-border pb-5">
            <div class="flex min-w-0 items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-blue-navy text-white shadow-sm">
                    <x-app-logo-icon class="size-7 fill-current" />
                </span>
                <div>
                    <flux:heading size="xl" level="2" class="font-display">Configuración de cuenta</flux:heading>

                </div>
            </div>
        </header>

        <div class="grid min-h-[28rem] gap-6 md:grid-cols-[12rem_minmax(0,1fr)]">
            <nav class="hub-settings-rail flex gap-2 overflow-x-auto border-b border-border pb-4 md:flex-col md:overflow-visible md:border-b-0 md:border-e md:pb-0 md:pe-5" aria-label="Secciones de configuración">
                <button type="button" x-on:click="section = 'profile'" x-bind:aria-current="section === 'profile' ? 'page' : null" class="hub-settings-rail__item">
                    <flux:icon name="user" class="size-5" />
                    <span><strong>Perfil</strong></span>
                </button>
                @unless(auth()->user()->esUsuarioInterno())
                    <button type="button" x-on:click="section = 'roles'" x-bind:aria-current="section === 'roles' ? 'page' : null" class="hub-settings-rail__item">
                        <flux:icon name="arrows-right-left" class="size-5" />
                        <span><strong>Roles</strong></span>
                    </button>
                @endunless
                <button type="button" x-on:click="section = 'security'" x-bind:aria-current="section === 'security' ? 'page' : null" class="hub-settings-rail__item">
                    <flux:icon name="lock-closed" class="size-5" />
                    <span><strong>Seguridad</strong></span>
                </button>
                <button type="button" x-on:click="section = 'danger'" x-bind:aria-current="section === 'danger' ? 'page' : null" class="hub-settings-rail__item hub-settings-rail__item--danger">
                    <flux:icon name="trash" class="size-5" />
                    <span><strong>Eliminar cuenta</strong></span>
                </button>
            </nav>

            <div class="min-w-0">
                <section x-show="section === 'profile'" x-cloak aria-labelledby="settings-profile-title">
                    <div class="mb-5">
                        <h3 id="settings-profile-title" class="font-display text-xl font-semibold text-blue-navy">Perfil</h3>

                    </div>
                    <livewire:pages::settings.profile :modal-mode="true" :key="'account-settings-profile-'.auth()->id()" />
                </section>

                @unless(auth()->user()->esUsuarioInterno())
                    <section x-show="section === 'roles'" x-cloak aria-labelledby="settings-roles-title">
                        <h3 id="settings-roles-title" class="font-display text-xl font-semibold text-blue-navy">Cambiar de rol</h3>

                        <livewire:selector-rol-activo />
                    </section>
                @endunless

                <section x-show="section === 'security'" x-cloak aria-labelledby="settings-security-title">
                    <div class="mb-5">
                        <h3 id="settings-security-title" class="font-display text-xl font-semibold text-blue-navy">Seguridad</h3>

                    </div>
                    <div class="grid gap-3">
                        <a href="{{ route('security.edit') }}" wire:navigate class="group flex items-center gap-4 rounded-xl border border-border bg-surface p-4 transition hover:-translate-y-0.5 hover:border-bio-green/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bio-green">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-bio-green/10 text-bio-green">
                                <flux:icon name="key" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong class="block text-sm text-text-primary">Cambiar contraseña</strong>

                            </span>
                            <flux:icon name="chevron-right" class="size-5 text-text-secondary transition group-hover:translate-x-0.5" />
                        </a>
                        <a href="{{ route('security.edit') }}" wire:navigate class="group flex items-center gap-4 rounded-xl border border-border bg-surface p-4 transition hover:-translate-y-0.5 hover:border-bio-green/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bio-green">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-science-blue/10 text-science-blue">
                                <flux:icon name="shield-check" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong class="block text-sm text-text-primary">Verificación en dos pasos (2FA)</strong>

                            </span>
                            <flux:icon name="chevron-right" class="size-5 text-text-secondary transition group-hover:translate-x-0.5" />
                        </a>

                    </div>
                </section>

                <section x-show="section === 'danger'" x-cloak aria-labelledby="settings-danger-title">
                    <div class="rounded-lg border border-error/25 bg-error/5 p-5">
                        <h3 id="settings-danger-title" class="font-display text-xl font-semibold text-error">Eliminar cuenta</h3>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-text-secondary">Esta acción elimina permanentemente tu cuenta y los recursos asociados. Se solicitará tu contraseña antes de continuar.</p>
                        <div class="mt-5">
                            <livewire:pages::settings.delete-user-form :key="'account-settings-delete-'.auth()->id()" />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</flux:modal>
