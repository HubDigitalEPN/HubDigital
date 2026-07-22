<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen flex flex-col bg-bg-main">

<header class="bg-surface border-b border-border shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <a href="{{ route('portal.catalogo') }}" class="flex min-w-0 items-center">
                <img src="/images/logo-DB.jpg" alt="Departamento de Biología EPN" class="h-10 w-auto object-contain" />
            </a>

            <nav class="flex items-center gap-6">
                <a href="{{ route('login') }}" wire:navigate class="inline-flex min-h-11 items-center px-1 text-sm text-text-secondary transition-colors hover:text-text-primary">Iniciar sesión</a>
            </nav>
        </div>
    </div>
</header>

<main class="flex-1">
    {{ $slot }}
</main>

@php
    $hubVersion = '1.0.0';
    $hubUrl = 'www.Hubdigital.test';
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $hoy = now();
    $fechaConsulta = $hoy->day.' de '.$meses[$hoy->month - 1].' de '.$hoy->year;
    $anioActual = $hoy->year;
    $citaHub = 'Hub Digital Laboratorio de Invertebrados. Versión '.$hubVersion.'. Departamento de Biología, Facultad de Ciencias, Escuela Politécnica Nacional. Versión en línea en https://'.$hubUrl.'. Fecha de consulta: '.$fechaConsulta.'.';
@endphp

<footer class="mt-12 bg-blue-navy text-white/85">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 space-y-8">

        {{-- ══════════════════════════════════
             Cita del Hub con botón "copiar"
             ══════════════════════════════════ --}}
        <section
            x-data="{
                copiado: false,
                copiar() {
                    const texto = this.$refs.cita.innerText.trim();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(texto).then(() => {
                            this.copiado = true;
                            setTimeout(() => { this.copiado = false; }, 2500);
                        });
                    }
                }
            }"
            aria-labelledby="footer-cita-titulo"
        >
            <h3 id="footer-cita-titulo" class="font-serif text-sm font-semibold uppercase tracking-wide text-white">
                Para citar el Hub Digital del Laboratorio de Invertebrados
            </h3>

            <div class="mt-3 flex items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-4">
                <p x-ref="cita" class="flex-1 text-sm leading-relaxed italic">
                    {{ $citaHub }}
                </p>
                <button
                    type="button"
                    @click="copiar()"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-white/20"
                    :aria-label="copiado ? 'Cita copiada' : 'Copiar cita al portapapeles'"
                    :title="copiado ? 'Copiado' : 'Copiar cita'"
                >
                    <template x-if="!copiado">
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="clipboard-document" class="size-4" />
                            <span>Copiar</span>
                        </span>
                    </template>
                    <template x-if="copiado">
                        <span class="inline-flex items-center gap-1.5 text-success">
                            <flux:icon name="check" class="size-4" />
                            <span>Copiado</span>
                        </span>
                    </template>
                </button>
            </div>
        </section>

        {{-- ══════════════════════════════════
             Licencia Creative Commons
             ══════════════════════════════════ --}}
        <section class="space-y-3 text-sm leading-relaxed">
            <p>
                El contenido del HubDigital del Laboratorio de Invertebrados está licenciado bajo
                <a href="https://creativecommons.org/licenses/by/4.0/deed.es" target="_blank" rel="noopener noreferrer" class="font-medium text-white underline decoration-white/40 underline-offset-2 transition-colors hover:decoration-white">
                    Licencia de Atribución Creative Commons
                </a>.
                Fomentamos el uso de imágenes del HubDigital del Laboratorio de Invertebrados. En la impresión o uso digital en publicaciones científicas o de difusión, cada fotografía debe incluir al pie lo siguiente: atribución de la persona que la adquirió, año, código único del espécimen y cita abreviada
                <span class="whitespace-nowrap font-mono">{{ $hubUrl }}</span>.
            </p>
            <p class="rounded-md bg-white/5 px-3 py-2 text-xs">
                <span class="font-semibold text-white">Ejemplo:</span>
                Juan Pérez, {{ $anioActual }}, MEPNINV00001, {{ $hubUrl }}
            </p>
            <p>
                Para los sitios web, las imágenes deben incluir la atribución del fotógrafo y estar claramente identificadas como procedentes de
                <span class="whitespace-nowrap font-mono">{{ $hubUrl }}</span>,
                con enlace activo a la página de origen correspondiente.
            </p>
        </section>

        {{-- ══════════════════════════════════
             Créditos + hosting + fecha en vivo
             ══════════════════════════════════ --}}
        <section class="space-y-3 border-t border-white/10 pt-6 text-xs leading-relaxed text-white/70">
            <p>
                El HubDigital del Laboratorio de Invertebrados es una creación del Laboratorio de ADN,
                Facultad de Ingeniería en Sistemas, Escuela Politécnica Nacional, Quito, Ecuador.
            </p>
            <p>
                <span class="font-semibold text-white/85">Créditos:</span>
                Alejandro Alemán, Jimmy Valladares, Michael Trocellier, Miguel Mendosa, Paul Cajas.
            </p>
            <p>
                El HubDigital del Laboratorio de Invertebrados está alojado en los servidores de la
                Facultad de Ingeniería en Sistemas, Escuela Politécnica Nacional.
            </p>
            <p
                x-data="{
                    ahora: '',
                    actualizar() {
                        const opciones = {
                            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
                        };
                        this.ahora = new Intl.DateTimeFormat('es-EC', opciones).format(new Date());
                    }
                }"
                x-init="actualizar(); setInterval(() => actualizar(), 1000)"
                class="font-mono text-white/60 tabular-nums"
                aria-live="polite"
            >
                <span x-text="ahora">&nbsp;</span>
            </p>
        </section>

        {{-- Copyright final --}}
        <div class="border-t border-white/10 pt-6 text-center text-xs text-white/60">
            © {{ $anioActual }} Escuela Politécnica Nacional. Todos los derechos reservados.
        </div>
    </div>
</footer>

@livewire(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)

@fluxScripts
</body>
</html>
