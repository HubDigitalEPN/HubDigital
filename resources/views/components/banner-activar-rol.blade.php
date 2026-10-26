@use('App\Enums\RolUsuario')

@auth
    @php
        $usuarioBanner = auth()->user();
        $mostrarBanner = ! $usuarioBanner->esCurador() && $usuarioBanner->rolesAsignados()->count() === 1;
        $otroRol = $usuarioBanner->esDepositante() ? RolUsuario::PRESTAMISTA : RolUsuario::DEPOSITANTE;
        $mensajeBanner = $otroRol === RolUsuario::DEPOSITANTE
            ? '¿También quieres depositar material biológico en la colección?'
            : '¿También necesitas solicitar especímenes en préstamo?';
        $claveBanner = 'banner-rol-'.strtolower($otroRol->value);
    @endphp

    @if ($mostrarBanner)
        <aside
            x-data="{ visible: sessionStorage.getItem('{{ $claveBanner }}') !== 'oculto', timer: null }"
            x-init="timer = setTimeout(() => visible = false, 8500)"
            x-show="visible"
            x-transition
            x-cloak
            class="fixed bottom-5 right-5 z-[80] w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-border bg-surface p-4 shadow-xl"
            role="status"
            aria-live="polite"
        >
            <button type="button" x-on:click="visible = false; sessionStorage.setItem('{{ $claveBanner }}', 'oculto')" class="absolute right-2 top-2 p-2 text-text-secondary hover:text-text-primary" aria-label="Cerrar aviso">
                <flux:icon name="x-mark" class="size-4" />
            </button>
            <p class="pr-6 text-sm font-semibold text-text-primary">{{ $mensajeBanner }}</p>

        </aside>
    @endif
@endauth
