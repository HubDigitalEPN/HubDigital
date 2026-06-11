<x-layouts::auth :title="__('Enlace no válido')">
    <div class="flex flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col items-center gap-3 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-warning/10">
                <flux:icon name="exclamation-triangle" variant="outline" class="size-6 text-warning" />
            </div>
            <h1 class="font-display text-2xl font-bold text-blue-navy">No pudimos continuar</h1>
            <p class="text-sm text-text-secondary">
                @auth
                    @if (! auth()->user()->hasVerifiedEmail())
                        El enlace de verificación pudo haber <span class="font-medium text-text-primary">expirado</span>
                        o <span class="font-medium text-text-primary">ya fue utilizado</span>. No te preocupes:
                        solicita uno nuevo para continuar.
                    @else
                        No tienes permiso para acceder a esta página.
                    @endif
                @else
                    No tienes acceso a esta página, o el enlace que usaste expiró. Inicia sesión para continuar.
                @endauth
            </p>
        </div>

        {{-- Acciones contextuales --}}
        <div class="flex flex-col gap-3">
            @auth
                @if (! auth()->user()->hasVerifiedEmail())
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full bg-bio-green! border-bio-green! hover:bg-bio-green/90! text-white! font-semibold"
                        >
                            Enviarme un nuevo enlace
                        </flux:button>
                    </form>
                @else
                    <a href="{{ route('dashboard') }}" wire:navigate class="w-full">
                        <flux:button variant="primary" class="w-full">Ir al inicio</flux:button>
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button variant="ghost" type="submit" class="w-full text-sm">
                        Cerrar sesión
                    </flux:button>
                </form>
            @else
                <a href="{{ route('login') }}" wire:navigate class="w-full">
                    <flux:button variant="primary" class="w-full">Iniciar sesión</flux:button>
                </a>
            @endauth
        </div>

    </div>
</x-layouts::auth>
