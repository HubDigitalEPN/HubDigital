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
                    <x-app-logo-icon class="size-6 fill-current text-white" />
                </span>
                <div class="flex flex-col leading-tight">
                    <span class="font-serif text-sm font-bold text-blue-navy">Hub Digital</span>
                    <span class="text-[0.625rem] font-medium uppercase tracking-wider text-text-secondary">Laboratorio de Invertebrados</span>
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

                {{-- Ficha COMPLETA: todos los campos con dato, agrupados y etiquetados
                     reusando el registro de columnas del espécimen. --}}
                @php
                    $etiquetasGrupo = [
                        'identificacion' => 'Identificación',
                        'taxonomia' => 'Taxonomía',
                        'localidad' => 'Localidad y geografía',
                        'fecha' => 'Fecha de colecta',
                        'registro' => 'Registro',
                        'atributos' => 'Atributos del espécimen',
                        'revision' => 'Estado de revisión',
                    ];
                    // codigoCatalogo, taxonNombre y estado ya se muestran en la cabecera.
                    $omitir = ['codigoCatalogo', 'taxonNombre', 'estado'];
                    $porGrupo = [];
                    foreach (\Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen::todas() as $col) {
                        if (in_array($col['clave'], $omitir, true)) {
                            continue;
                        }
                        $valor = $ficha->datos[$col['clave']] ?? null;
                        if ($valor === null || $valor === '') {
                            continue;
                        }
                        $porGrupo[$col['grupo']][] = ['etiqueta' => $col['etiqueta'], 'valor' => $valor];
                    }
                @endphp

                <div class="space-y-4 border-t border-border pt-4">
                    @foreach($etiquetasGrupo as $g => $titulo)
                        @if(! empty($porGrupo[$g]))
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-science-blue">{{ $titulo }}</p>
                                <dl class="space-y-2 text-sm">
                                    @foreach($porGrupo[$g] as $campo)
                                        <div class="flex justify-between gap-3">
                                            <dt class="shrink-0 text-text-secondary">{{ $campo['etiqueta'] }}</dt>
                                            <dd class="min-w-0 text-right text-text-primary break-words">{{ $campo['valor'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    @endforeach

                    <div class="flex flex-col gap-1 border-t border-border pt-3">
                        <dt class="text-sm text-text-secondary">GUID</dt>
                        <dd class="font-mono text-xs break-all text-text-secondary">{{ $ficha->id }}</dd>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-text-secondary">
                Ficha verificada mediante el código QR físico del espécimen.
            </p>
        </main>

        @fluxScripts
    </body>
</html>
