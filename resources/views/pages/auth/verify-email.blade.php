<x-layouts::auth :title="__('Verifica tu correo')">
    <div class="flex flex-col gap-6">

        {{-- Header --}}
        <div class="flex flex-col items-center gap-3 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-science-blue/10">
                <flux:icon name="envelope" variant="outline" class="size-6 text-science-blue" />
            </div>
            <h1 class="font-display text-2xl font-bold text-blue-navy">Verifica tu correo</h1>
            <p class="text-sm text-text-secondary">
                Tu cuenta está <span class="font-medium text-text-primary">pendiente de verificación</span>.
                Te enviamos un enlace a
                <strong class="break-all font-semibold text-text-primary">{{ auth()->user()->email }}</strong>;
                ábrelo para activar tu cuenta y acceder a la plataforma.
            </p>
        </div>

        {{-- Confirmación de reenvío --}}
        @if (session('status') == 'verification-link-sent')
            <div class="flex items-start gap-2 rounded-lg border border-success/30 bg-success/5 p-3">
                <flux:icon name="check-circle" variant="outline" class="mt-px size-4 shrink-0 text-success" />
                <p class="text-xs text-text-primary">
                    Enviamos un nuevo enlace de verificación a <strong>{{ auth()->user()->email }}</strong>.
                    Revisa también tu carpeta de spam.
                </p>
            </div>
        @endif

        {{-- Aviso: revisar carpeta de spam --}}
        <div class="flex items-start gap-2 rounded-lg border border-info/30 bg-info/5 p-3">
            <flux:icon name="information-circle" variant="outline" class="mt-px size-4 shrink-0 text-info" />

        </div>

        {{-- Nota de vigencia --}}

        {{-- Acciones --}}
        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button
                    type="submit"
                    variant="primary"
                    class="w-full bg-bio-green! border-bio-green! hover:bg-bio-green/90! text-white! font-semibold"
                >
                    Reenviar correo de verificación
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="w-full text-sm" data-test="logout-button">
                    Cerrar sesión
                </flux:button>
            </form>
        </div>

    </div>
</x-layouts::auth>
