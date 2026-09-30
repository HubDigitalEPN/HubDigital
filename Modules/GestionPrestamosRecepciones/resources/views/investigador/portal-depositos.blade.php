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
                    <h1 id="titulo-depositos" class="portal-hero-title portal-depositos-title font-display font-bold leading-[1.08] tracking-[-0.025em] text-blue-navy">Depósito de colecciones biológicas</h1>
                    <div class="mt-5 h-1 w-14 rounded-full bg-bio-green" aria-hidden="true"></div>
                    <p class="mt-6 max-w-4xl text-lg leading-8 text-text-secondary">
                        Registra un depósito o donación. El sistema revisa tus PDF, recupera datos y te guía hasta la firma y el envío a curaduría.
                    </p>

                    <div class="portal-responsive-actions portal-depositos-actions mt-8">
                        @auth
                            <a
                                href="{{ auth()->user()->esDepositante() || auth()->user()->esAdministrador() ? route('depositos.solicitud.crear') : route('dashboard') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="document-plus" class="size-5" />
                                {{ auth()->user()->esDepositante() || auth()->user()->esAdministrador() ? 'Iniciar una solicitud' : 'Ir a mi cuenta' }}
                                <flux:icon name="arrow-right" class="size-4" />
                            </a>
                        @else
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-navy px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#244872] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="user-plus" class="size-5" />
                                Crear cuenta e iniciar
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
                                    Ver mis solicitudes
                                </a>
                            @endif
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-blue-navy/25 bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:border-blue-navy/60 hover:bg-blue-navy/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                <flux:icon name="arrow-right-end-on-rectangle" class="size-5" />
                                Ya tengo una cuenta
                            </a>
                        @endauth
                    </div>

                    <p class="mt-5 flex max-w-lg items-start gap-2 text-sm leading-6 text-text-secondary">
                        <flux:icon name="lock-closed" class="mt-0.5 size-4 shrink-0 text-bio-green" />
                        Puedes consultar esta información sin iniciar sesión. Solo te pediremos autenticarte al ingresar al formulario para proteger tu expediente.
                    </p>
                </div>
            </div>

            <figure class="relative min-h-72 min-w-0 max-w-full overflow-hidden sm:min-h-96 lg:min-h-full">
                <img
                    src="{{ asset('images/portal-depositos-hero.webp') }}"
                    alt="Gaveta de colección entomológica con especímenes y etiquetas de archivo"
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
                    <h2 id="preparar-expediente" class="font-display text-2xl font-bold text-blue-navy">Prepara tu expediente</h2>
                </div>
                <p class="mt-4 leading-7 text-text-secondary">Los requisitos se adaptan al tipo de trámite, procedencia y situación regulatoria declarada.</p>
                <ul class="mt-6 space-y-3 text-sm leading-6 text-text-secondary">
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Solicitud de depósito o donación generada y firmada dentro de HubDigital.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Autorización de recolección y guía de movilización, cuando correspondan.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Evidencia de procedencia lícita, cesión o justificación institucional.</span></li>
                    <li class="flex gap-3"><flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-bio-green" /><span>Datos de depósito material MEPN y detalle biológico completados en formularios guiados.</span></li>
                </ul>
            </article>

            <div class="hidden bg-blue-navy/10 lg:block" aria-hidden="true"></div>

            <article>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                        <flux:icon name="information-circle" class="size-5" />
                    </div>
                    <h2 class="font-display text-2xl font-bold text-blue-navy">Antes de trasladar el material</h2>
                </div>
                <ul class="mt-6 space-y-4 text-sm leading-6 text-text-secondary">
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">01</span><span>Los depósitos temporales admiten hasta tres solicitudes por depositante en cada año calendario.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">02</span><span>La recepción física no implica aceptación automática ni transferencia de propiedad.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">03</span><span>No envíes ni traslades especímenes hasta recibir instrucciones del equipo curatorial.</span></li>
                    <li class="grid grid-cols-[1.5rem_1fr] gap-2"><span class="font-mono font-semibold text-amber-700">04</span><span>Tus documentos y datos sensibles solo serán visibles para ti y el personal autorizado.</span></li>
                </ul>
            </article>
        </div>
    </section>

</div>
