<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main antialiased">
        <x-estado-conectividad />
        {{-- Subtle background radial touches --}}
        <div class="hub-auth-bg pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true"></div>

        <div class="relative flex min-h-svh flex-col items-center justify-start overflow-x-hidden px-3 py-3 sm:px-5 sm:py-5 md:px-8">
            <div class="flex w-full max-w-2xl flex-col gap-3 sm:gap-4">

                <a href="{{ route('home') }}" class="flex items-center justify-center" wire:navigate>
                    <img src="{{ \App\Support\FotosEditoriales::url(\App\Support\FotosEditoriales::ACCESO_SISTEMA) }}"
                         alt="{{ \App\Support\FotosEditoriales::alt(\App\Support\FotosEditoriales::ACCESO_SISTEMA, \App\Support\FotosEditoriales::ALT_SISTEMA) }}"
                         class="h-10 w-auto max-w-full object-contain sm:h-12" data-logo-acceso />
                </a>

                {{-- Card --}}
                <div class="min-w-0 rounded-lg border border-border bg-surface px-4 py-4 shadow-sm sm:px-5 sm:py-5">
                    {{ $slot }}
                </div>

            </div>
        </div>

        @fluxScripts
    </body>
</html>
