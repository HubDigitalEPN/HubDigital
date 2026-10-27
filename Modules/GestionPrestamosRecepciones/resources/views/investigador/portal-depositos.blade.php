@php($depositosTexto = static fn (string $clave): string => \App\Support\PortalCopy::text('depositos.'.$clave))
<style>
    .portal-depositos-hero-image { object-position: 78% 50%; }
    @media (min-width: 640px) { .portal-depositos-hero-image { object-position: 70% 50%; } }
    @media (min-width: 1024px) {
        .portal-depositos-hero-image { object-position: 62% 50%; }
        .portal-depositos-hero-grid { grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr); }
    }
</style>

<div class="overflow-hidden bg-white text-text-primary">
    <section class="relative isolate border-b border-blue-navy/10 bg-white" aria-labelledby="titulo-depositos">
        <div class="portal-hero-grid portal-depositos-hero-grid min-w-0 lg:min-h-[34rem]">
            <div class="portal-container portal-hero-copy relative z-10 flex items-center">
                <div class="min-w-0 max-w-4xl">
                    <h1 id="titulo-depositos" class="portal-hero-title portal-depositos-title font-display font-bold leading-[1.08] tracking-[-0.025em] text-blue-navy">{{ $depositosTexto('titulo') }}</h1>
                    <div class="mt-5 h-1 w-14 rounded-full bg-bio-green" aria-hidden="true"></div>
                    @if(auth()->user()?->esCurador())
                        <a href="{{ route('admin.textos-portal', ['grupo' => 'depositos']) }}" class="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-science-blue underline">Editar textos y requisitos de depósitos</a>
                    @endif

                    <div class="portal-responsive-actions portal-depositos-actions mt-8">
                        @auth
                            <a
                                href="{{ auth()->user()->esDepositante() || auth()->user()->esAdministrador() ? route('depositos.solicitud.crear') : route('dashboard') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="document-plus" class="size-5" />
                                {{ auth()->user()->esDepositante() || auth()->user()->esAdministrador() ? $depositosTexto('solicitud') : $depositosTexto('cuenta') }}
                                <flux:icon name="arrow-right" class="size-4" />
                            </a>
                        @else
                            <a
                                href="{{ route('register', ['rol' => 'DEPOSITANTE']) }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="user-plus" class="size-5" />
                                {{ $depositosTexto('crear_cuenta') }}
                                <flux:icon name="arrow-right" class="size-4" />
                            </a>
                        @endauth

                        @auth
                            @if(auth()->user()->esDepositante())
                                <a
                                    href="{{ route('depositos.mis-solicitudes') }}"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy/60 hover:bg-blue-navy/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                                >
                                    <flux:icon name="clipboard-document-list" class="size-5" />
                                    {{ $depositosTexto('solicitudes') }}
                                </a>
                            @endif
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy/60 hover:bg-blue-navy/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="arrow-right-end-on-rectangle" class="size-5" />
                                {{ $depositosTexto('tengo_cuenta') }}
                            </a>
                        @endauth
                    </div>

                    <p class="mt-5 flex max-w-lg items-start gap-2 text-sm leading-6 text-text-secondary">
                        <flux:icon name="lock-closed" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                        {{ $depositosTexto('autenticacion') }}
                    </p>
                </div>
            </div>

            <figure class="relative min-h-72 min-w-0 max-w-full overflow-hidden sm:min-h-96 lg:min-h-full">
                <img
                    src="{{ asset('images/portal-depositos-hero.webp') }}"
                    alt="{{ $depositosTexto('hero_alt') }}"
                    class="portal-depositos-hero-image absolute inset-0 size-full object-cover object-[62%_50%]"
                    fetchpriority="high"
                />
                <div class="absolute inset-y-0 left-0 hidden w-32 bg-gradient-to-r from-white to-transparent lg:block" aria-hidden="true"></div>
            </figure>
        </div>
    </section>

    <section class="bg-white" aria-labelledby="preparar-expediente">
        <div class="portal-container mx-auto grid gap-10 py-14 lg:grid-cols-[minmax(0,1fr)_1px_minmax(0,1fr)] lg:gap-12 lg:py-16">
            <article>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-science-blue/10 text-science-blue">
                        <flux:icon name="document-text" class="size-5" />
                    </div>
                    <h2 id="preparar-expediente" class="font-display text-2xl font-bold text-blue-navy">{{ $depositosTexto('preparar_titulo') }}</h2>
                </div>

                <ul class="mt-6 space-y-3 text-sm leading-6 text-text-secondary">
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>{{ $depositosTexto('requisito_solicitud') }}</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>{{ $depositosTexto('requisito_permisos') }}</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>{{ $depositosTexto('requisito_procedencia') }}</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>{{ $depositosTexto('requisito_material') }}</span></li>
                </ul>
            </article>

            <div class="hidden bg-blue-navy/10 lg:block" aria-hidden="true"></div>

            <article>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <flux:icon name="information-circle" class="size-5" />
                    </div>
                    <h2 class="font-display text-2xl font-bold text-blue-navy">{{ $depositosTexto('traslado_titulo') }}</h2>
                </div>
                <ul class="mt-6 space-y-4 text-sm leading-6 text-text-secondary">
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">01</span><span>{{ $depositosTexto('limite_temporal') }}</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">02</span><span>{{ $depositosTexto('recepcion') }}</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">03</span><span>{{ $depositosTexto('instrucciones') }}</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">04</span><span>{{ $depositosTexto('privacidad') }}</span></li>
                </ul>
            </article>
        </div>
    </section>

</div>
