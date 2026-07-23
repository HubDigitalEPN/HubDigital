@use('App\Enums\RolUsuario')

@auth
    @php
        $usuarioBanner = auth()->user();
        $mostrarBanner = ! $usuarioBanner->esCurador() && $usuarioBanner->rolesAsignados()->count() === 1;
        $otroRol = $usuarioBanner->esDepositante() ? RolUsuario::PRESTAMISTA : RolUsuario::DEPOSITANTE;
        $mensajeBanner = $otroRol === RolUsuario::DEPOSITANTE
            ? '¿También quieres depositar material biológico en la colección?'
            : '¿También necesitas solicitar especímenes en préstamo?';
        $ctaBanner = $otroRol === RolUsuario::DEPOSITANTE ? 'Activar rol de Depositante' : 'Activar rol de Solicitante';
        $claveBanner = 'banner-rol-'.strtolower($otroRol->value);
    @endphp

    @if ($mostrarBanner)
        <div
            x-data="{ visible: localStorage.getItem('{{ $claveBanner }}') !== 'oculto' }"
            x-show="visible"
            x-cloak
        >
            <div class="flex flex-col gap-3 rounded-lg border border-info/30 bg-info/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <flux:icon name="sparkles" class="mt-0.5 size-5 shrink-0 text-info" />
                    <div>
                        <p class="text-sm font-medium text-text-primary">{{ $mensajeBanner }}</p>
                        <p class="mt-0.5 text-xs text-text-secondary">
                            Puedes usar la misma cuenta para ambos trámites y cambiar de rol cuando quieras.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="plus-circle"
                        :href="route('roles.activar', strtolower($otroRol->value))"
                        wire:navigate
                    >
                        {{ $ctaBanner }}
                    </flux:button>
                    <button
                        type="button"
                        x-on:click="visible = false; localStorage.setItem('{{ $claveBanner }}', 'oculto')"
                        class="text-text-secondary hover:text-text-primary"
                        aria-label="Ocultar aviso"
                    >
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    @endif
@endauth
