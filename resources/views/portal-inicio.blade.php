@component('layouts.portal', ['title' => 'Laboratorio de Invertebrados · Escuela Politécnica Nacional'])
    <div class="overflow-hidden bg-white text-text-primary">
        <section class="relative isolate border-b border-blue-navy/10 bg-white" aria-labelledby="titulo-portada">
            <div class="portal-hero-grid">
                <div class="portal-container portal-hero-copy relative z-10 flex items-center">
                    <div class="max-w-2xl">
                        <h1 id="titulo-portada" class="portal-hero-title font-display font-bold leading-[1.04] tracking-[-0.035em] text-blue-navy">
                            Ciencia, colecciones y biodiversidad del Ecuador
                        </h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-text-secondary sm:text-lg sm:leading-8">
                            El Laboratorio de Invertebrados de la Escuela Politécnica Nacional conserva, estudia y conecta con la sociedad el patrimonio biológico que custodia el Museo de Historia Natural Gustavo Orcés&nbsp;V.
                        </p>

                        <div class="portal-responsive-actions portal-home-actions mt-8">
                            <a
                                href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}"
                                class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-science-blue px-5 py-3 text-sm font-semibold !text-white shadow-sm transition hover:bg-[#1266b8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2"
                            >
                                Ver Colección Biológica
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <figure class="relative min-h-[23rem] overflow-hidden sm:min-h-[30rem] lg:min-h-full">
                    <img
                        src="{{ asset('images/portal-laboratorio-hero.jpg') }}"
                        alt="Gaveta entomológica durante una tarea de curaduría científica"
                        width="1536"
                        height="1024"
                        class="absolute inset-0 size-full object-cover object-center"
                        fetchpriority="high"
                        decoding="async"
                    />
                    <div class="absolute inset-y-0 left-0 hidden w-40 bg-gradient-to-r from-white to-transparent lg:block" aria-hidden="true"></div>
                </figure>
            </div>
        </section>


        <section id="catalogo" class="scroll-mt-28 bg-blue-navy text-white" aria-labelledby="titulo-catalogo">
            <div class="portal-container mx-auto py-14 lg:py-20">
                <div class="grid gap-10 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)] lg:gap-16">
                    <div>
                        <h2 id="titulo-catalogo" class="font-display text-3xl font-bold tracking-[-0.02em] sm:text-4xl">Catálogo e investigación de la biodiversidad</h2>
                        <p class="mt-5 max-w-2xl text-base leading-8 text-white/75">
                            El catálogo conecta ejemplares, identificaciones y localidades para consultar la diversidad de invertebrados y apoyar la investigación. Los registros publicados conservan su contexto científico y curatorial.
                        </p>
                        <a href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}" class="mt-7 inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-white px-5 py-3 text-sm font-semibold !text-blue-navy transition hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-blue-navy">
                            Consultar el catálogo digital
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>

                    <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        @foreach([
                            ['Taxonomía y sistemática', 'Identificación y documentación de la diversidad de invertebrados.'],
                            ['Distribución y biogeografía', 'Ocurrencias relacionadas con localidades y fechas de colecta.'],
                            ['Conservación', 'Evidencia histórica para comprender cambios en la biodiversidad.'],
                            ['Formación científica', 'Material de referencia para docencia y tesis.'],
                        ] as [$titulo, $descripcion])
                            <article class="border-l-2 border-[#9BD7A5] pl-4">
                                <h3 class="font-display text-lg font-semibold text-white">{{ $titulo }}</h3>
                                <p class="mt-2 text-sm leading-6 text-white/70">{{ $descripcion }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>

                <dl class="mt-10 grid gap-6 border-t border-white/20 pt-7 md:grid-cols-3 md:gap-8">
                    <div>
                        <dt class="font-semibold text-[#9BD7A5]">Darwin Core</dt>
                        <dd class="mt-1 text-sm leading-6 text-white/70">Términos comunes para describir datos de biodiversidad.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-[#9BD7A5]">GBIF</dt>
                        <dd class="mt-1 text-sm leading-6 text-white/70">Referencia para contrastar nombres taxonómicos.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-[#9BD7A5]">Trazabilidad</dt>
                        <dd class="mt-1 text-sm leading-6 text-white/70">Vínculo entre el ejemplar, su colecta y la gestión curatorial.</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section id="depositos" class="scroll-mt-28 bg-white" aria-labelledby="titulo-depositos">
            <div class="portal-container mx-auto py-16 lg:py-24">
                <div class="grid gap-12 lg:grid-cols-[0.78fr_1.22fr] lg:items-start lg:gap-20">
                    <div>
                        <div class="h-1 w-14 bg-bio-green" aria-hidden="true"></div>
                        <h2 id="titulo-depositos" class="mt-6 font-display text-3xl font-bold tracking-[-0.02em] text-blue-navy sm:text-4xl">Integrar material a la colección</h2>
                        <p class="mt-5 leading-8 text-text-secondary">El módulo de depósitos acompaña la preparación de formularios, la carga de permisos, la revisión curatorial y la entrega física de los ejemplares.</p>
                        <a href="{{ route('depositos.portal') }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-bio-green px-5 py-3 text-sm font-semibold !text-white transition hover:bg-[#246829] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-bio-green focus-visible:ring-offset-2">
                            Ir al portal de depósitos
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>

                    <ol class="grid gap-7 sm:grid-cols-2">
                        @foreach([
                            ['01', 'Revisa', 'Conoce requisitos y documentos aplicables antes de iniciar.'],
                            ['02', 'Registra', 'Completa los datos y adjunta la documentación en línea.'],
                            ['03', 'Da seguimiento', 'Atiende observaciones y consulta el estado de la revisión.'],
                            ['04', 'Formaliza', 'Entrega el material para constatación y generación del acta.'],
                        ] as [$numero, $titulo, $descripcion])
                            <li class="border-t border-blue-navy/20 pt-5">
                                <span class="font-display text-3xl font-semibold text-bio-green">{{ $numero }}</span>
                                <h3 class="mt-3 font-display text-xl font-semibold text-blue-navy">{{ $titulo }}</h3>
                                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ $descripcion }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        <section id="contacto" class="scroll-mt-28 border-t border-blue-navy/10 bg-[#F5F8FC]" aria-labelledby="titulo-contacto">
            <div class="portal-container mx-auto grid gap-10 py-14 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:py-16">
                <div>
                    <h2 id="titulo-contacto" class="font-display text-3xl font-bold text-blue-navy">Laboratorio de Invertebrados</h2>
                    <p class="mt-4 flex max-w-2xl items-start gap-3 leading-7 text-text-secondary">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="mt-1 size-5 shrink-0 fill-none stroke-bio-green stroke-2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                        <span>Departamento de Biología · Facultad de Ciencias · Escuela Politécnica Nacional · Ladrón de Guevara E11-253 · Quito, Ecuador</span>
                    </p>
                </div>
                <a href="mailto:adrian.troya@epn.edu.ec" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-md bg-science-blue px-5 py-3 text-sm font-semibold !text-white transition hover:bg-[#1266b8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue focus-visible:ring-offset-2">
                    Escribir al laboratorio
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="size-4 fill-none stroke-current stroke-2"><path d="M4 6h16v12H4zM4 7l8 6 8-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
            </div>
        </section>
    </div>
@endcomponent
