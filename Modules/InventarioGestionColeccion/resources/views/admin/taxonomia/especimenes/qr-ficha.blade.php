<!DOCTYPE html>
<html lang="es">
    <head>
        @include('partials.head')
        <title>Ficha del espécimen {{ $ficha->codigoCatalogo }}</title>
    </head>
    <body class="min-h-screen bg-bg-main">
        <main class="mx-auto w-full max-w-md p-4 sm:p-6">
            {{-- Marca --}}
            <div class="mb-4 flex items-center gap-2.5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-navy shadow-sm">
                    <x-app-logo-icon class="size-5 fill-current text-white" />
                </span>
                <div class="flex flex-col leading-tight">
                    <span class="font-serif text-sm font-bold text-blue-navy">Hub Digital</span>
                    <span class="text-[0.625rem] font-medium uppercase tracking-wider text-text-secondary">Colección Entomológica</span>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-text-secondary">Código de catálogo</p>
                        <h1 class="font-serif text-2xl font-bold break-all text-text-primary">{{ $ficha->codigoCatalogo }}</h1>
                    </div>
                    @php
                        $estadoClases = match ($ficha->estado) {
                            'disponible' => 'bg-success text-white',
                            'en_prestamo' => 'bg-warning text-white',
                            default => 'bg-border text-text-primary',
                        };
                    @endphp
                    <span class="inline-flex shrink-0 items-center rounded px-2 py-1 text-xs font-semibold {{ $estadoClases }}">
                        {{ ucfirst(str_replace('_', ' ', $ficha->estado)) }}
                    </span>
                </div>

                {{-- Nombre científico: el dato más importante de la etiqueta. --}}
                <div class="mb-4 border-t border-border pt-3">
                    <p class="text-xs font-medium uppercase tracking-wider text-text-secondary">Determinación</p>
                    @if($ficha->taxonNombre)
                        <p class="font-serif text-lg italic text-text-primary">{{ $ficha->taxonNombre }}</p>
                    @else
                        <p class="text-sm text-text-secondary italic">Sin determinar</p>
                    @endif
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-text-secondary">Localidad</dt>
                        <dd class="text-right text-text-primary">{{ $ficha->localidad ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-text-secondary">Fecha de colecta</dt>
                        <dd class="text-right text-text-primary">{{ $ficha->fechaColecta ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-text-secondary">Colector</dt>
                        <dd class="text-right text-text-primary">{{ $ficha->colector ?: '—' }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 border-t border-border pt-3">
                        <dt class="text-text-secondary">GUID</dt>
                        <dd class="font-mono text-xs break-all text-text-secondary">{{ $ficha->id }}</dd>
                    </div>
                </dl>
            </div>

            <p class="mt-4 text-center text-xs text-text-secondary">
                Ficha verificada mediante el código QR físico del espécimen.
            </p>
        </main>

        @fluxScripts
    </body>
</html>
