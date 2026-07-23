<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg-main antialiased">
        {{-- Subtle background radial touches --}}
        <div class="hub-auth-bg pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true"></div>

        <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">

                {{-- Brand: lockup oficial de Hub Digital --}}
                <a href="{{ route('home') }}" class="flex items-center justify-center" wire:navigate>
                    <img src="{{ asset('images/hub-logo.png') }}" alt="Hub Digital · Laboratorio de Invertebrados"
                         class="h-16 w-auto" />
                </a>

                {{-- Card --}}
                <div class="rounded-lg border border-border bg-surface px-8 py-8 shadow-sm">
                    {{ $slot }}
                </div>

            </div>
        </div>

        @fluxScripts
    </body>
</html>
