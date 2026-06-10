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
                Te enviamos un enlace a tu correo electrónico; ábrelo para activar tu cuenta y acceder a la plataforma.
            </p>
        </div>

        {{-- Confirmación de reenvío --}}
        @if (session('status') == 'verification-link-sent')
            <div class="flex items-start gap-2 rounded-lg border border-success/30 bg-success/5 p-3">
                <flux:icon name="check-circle" variant="outline" class="mt-px size-4 shrink-0 text-success" />
                <p class="text-xs text-text-primary">
                    Enviamos un nuevo enlace de verificación al correo que registraste. Revisa también tu carpeta de spam.
                </p>
            </div>
        @endif

        {{-- Aviso: revisar carpeta de spam --}}
        <div class="flex items-start gap-2 rounded-lg border border-info/30 bg-info/5 p-3">
            <flux:icon name="information-circle" variant="outline" class="mt-px size-4 shrink-0 text-info" />
            <p class="text-xs text-text-primary">
                ¿No encuentras el correo? Revisa tu carpeta de <span class="font-medium">spam o correo no deseado</span>.
                Si lo hallas allí, márcalo como <span class="font-medium">«No es spam»</span> para recibir sin problemas los próximos mensajes.
            </p>
        </div>

        {{-- Nota de vigencia --}}
        <p class="flex items-start gap-1 text-xs text-text-secondary">
            <flux:icon name="clock" variant="outline" class="mt-px size-3.5 shrink-0" />
            Por seguridad, el enlace caduca a los {{ config('auth.verification.expire', 60) }} minutos. Si expira, solicita uno nuevo aquí.
        </p>

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
