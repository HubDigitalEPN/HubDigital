<x-layouts::auth :title="__('Página no encontrada')">
    @php
        $logoEpnPath = resource_path('logos/logo-epn.jpeg');
        $logoBioPath = resource_path('logos/logo-departamento-biologia-epn.jpeg');
        $logoEpnBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoEpnPath));
        $logoBioBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoBioPath));
    @endphp

    <div class="flex flex-col gap-6">

        {{-- Logos institucionales --}}
        <div class="flex items-center justify-center gap-6">
            <img src="{{ $logoEpnBase64 }}" alt="Logo EPN" class="h-14 w-auto" />
            <div class="h-10 w-px bg-border"></div>
            <img src="{{ $logoBioBase64 }}" alt="Logo Departamento de Biología" class="h-14 w-auto" />
        </div>

        {{-- Header --}}
        <div class="flex flex-col items-center gap-2 text-center">
            <span class="font-display text-6xl font-bold leading-none text-blue-navy">404</span>
            <h1 class="font-display text-xl font-bold text-blue-navy">Página no encontrada</h1>
            <p class="text-sm text-text-secondary">
                La página que buscas no existe, cambió de dirección o el enlace que usaste ya no es válido.
            </p>
        </div>

        {{-- Acciones --}}
        <div class="flex flex-col gap-3">
            @auth
                <a href="{{ route('dashboard') }}" wire:navigate class="w-full">
                    <flux:button variant="primary" icon="home" class="w-full">Ir al inicio</flux:button>
                </a>
            @else
                <a href="{{ route('login') }}" wire:navigate class="w-full">
                    <flux:button variant="primary" class="w-full">Iniciar sesión</flux:button>
                </a>
            @endauth

            <a href="javascript:history.back()" class="w-full">
                <flux:button variant="ghost" class="w-full text-sm">Volver atrás</flux:button>
            </a>
        </div>

    </div>
</x-layouts::auth>
