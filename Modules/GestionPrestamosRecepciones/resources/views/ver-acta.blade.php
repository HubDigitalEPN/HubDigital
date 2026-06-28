@once
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 1.8cm 2cm;
    }

    /* Resetear html y body para evitar página en blanco */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
    }

    /* Ocultar el layout de la app (sidebar, header de Flux UI) */
    [data-flux-sidebar],
    [data-flux-header],
    aside,
    header,
    nav {
        display: none !important;
        height: 0 !important;
        overflow: hidden !important;
    }

    /* Colapsar todos los contenedores intermedios entre body y #acta-doc */
    body > *:not(script):not(style),
    body > * > *:not(script):not(style) {
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
    }

    #acta-doc {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }

    /* Evitar cortes bruscos solo en elementos pequeños */
    #acta-doc li,
    #acta-doc tr,
    #acta-doc dl > div {
        break-inside: avoid;
    }
}
</style>
@endonce

<div class="p-6 space-y-4 print:!p-0 print:!m-0 print:!space-y-0">

    {{-- Toolbar (oculto en modo embed/iframe) --}}
    <div class="flex items-center justify-between print:hidden {{ ($isEmbed ?? false) ? 'hidden' : '' }}">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item>Acta de préstamo</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $acta?->numeroPrestamo ?? '—' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="flex items-center gap-3">
            <flux:button
                icon="arrow-down-tray"
                tag="a"
                href="{{ route('prestamos.acta.descargar-pdf', $acta?->id) }}"
                target="_blank">
                Descargar PDF
            </flux:button>
        </div>
    </div>

    @if(!$acta)
        <flux:callout variant="danger" icon="exclamation-triangle">Acta no encontrada.</flux:callout>
    @else
        {{-- Document --}}
        <div id="acta-doc" class="bg-white rounded-lg border border-border shadow-sm mx-auto max-w-3xl p-10 space-y-8 print:shadow-none print:border-none print:max-w-full print:rounded-none print:p-0">

            {{-- Header con logos --}}
            @php
                $logoEpnPath = resource_path('logos/logo-epn.jpeg');
                $logoBioPath = resource_path('logos/logo-departamento-biologia-epn.jpeg');
                $logoEpnBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoEpnPath));
                $logoBioBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoBioPath));
            @endphp
            <div class="flex items-center justify-between">
                <img src="{{ $logoEpnBase64 }}" alt="Logo EPN" class="h-16 w-auto flex-shrink-0" />
                <div class="text-center space-y-1 flex-1 px-4">
                    <p class="text-xs text-text-secondary uppercase tracking-widest">Laboratorio de Invertebrados</p>
                    <h1 class="text-2xl font-bold text-text-primary font-serif mt-2">ACTA DE PRÉSTAMO DE ESPECÍMENES</h1>
                    <p class="font-mono text-sm text-text-secondary mt-1">{{ $acta->numeroPrestamo }}</p>
                    @if($acta->patente !== '')
                        <p class="text-xs text-text-secondary mt-1">PATENTE: {{ $acta->patente }}</p>
                    @endif
                </div>
                <img src="{{ $logoBioBase64 }}" alt="Logo Departamento de Biología" class="h-16 w-auto flex-shrink-0" />
            </div>

            <hr class="border-border" />

            {{-- Datos del préstamo --}}
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Condiciones del préstamo</h2>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div>
                        <dt class="text-text-secondary">Tipo de préstamo</dt>
                        <dd class="font-medium text-text-primary capitalize">{{ $acta->tipoPrestamo }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Alcance</dt>
                        <dd class="font-medium text-text-primary">{{ ($acta->alcancePrestamo ?? 'nacional') === 'internacional' ? 'Internacional' : 'Nacional' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de inicio</dt>
                        <dd class="font-medium text-text-primary">
                            {{ ($acta->fechaInicio instanceof \DateTimeInterface ? $acta->fechaInicio : \Carbon\Carbon::parse($acta->fechaInicio))->format('d/m/Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de vencimiento</dt>
                        <dd class="font-medium text-text-primary">
                            {{ ($acta->fechaFin instanceof \DateTimeInterface ? $acta->fechaFin : \Carbon\Carbon::parse($acta->fechaFin))->format('d/m/Y') }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Investigador --}}
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Investigador solicitante</h2>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Título del estudio</dt>
                        <dd class="font-medium text-text-primary mt-0.5">{{ $acta->tituloEstudio }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Institución de adscripción</dt>
                        <dd class="font-medium text-text-primary">{{ $acta->institucionAdscripcion }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Línea de investigación</dt>
                        <dd class="font-medium text-text-primary">{{ $acta->lineaInvestigacion }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Propósito del préstamo</dt>
                        <dd class="text-text-primary mt-0.5">{{ $acta->propositoPrestamo }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Especímenes --}}
            @if(count($acta->items))
                <div class="space-y-3">
                    <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Especímenes en préstamo</h2>
                    <table class="w-full text-sm border border-border rounded">
                        <thead>
                            <tr class="bg-bg-main">
                                <th class="text-left px-3 py-2 border-b border-border font-medium text-text-primary w-8">#</th>
                                <th class="text-left px-3 py-2 border-b border-border font-medium text-text-primary">Código de espécimen</th>
                                <th class="text-left px-3 py-2 border-b border-border font-medium text-text-primary w-24">Cantidad</th>
                                <th class="text-left px-3 py-2 border-b border-border font-medium text-text-primary">Condiciones específicas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($acta->items as $i => $item)
                                <tr class="{{ $loop->even ? 'bg-bg-main' : 'bg-white' }}">
                                    <td class="px-3 py-2 border-b border-border text-text-secondary">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 border-b border-border font-mono text-text-primary">{{ $item->codigoExterno }}</td>
                                    <td class="px-3 py-2 border-b border-border text-text-primary">{{ $item->cantidadSolicitada }}</td>
                                    <td class="px-3 py-2 border-b border-border text-text-secondary text-xs">{{ $item->condicionesEspecificas ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Condiciones generales --}}
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Condiciones generales</h2>
                <ul class="text-sm text-text-primary leading-relaxed space-y-4 list-none">

                    <li class="flex gap-3">
                        <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                        <div class="space-y-1">
                            <p>En caso de ser de interés para el receptor del préstamo, se autoriza el depósito de réplicas de especies, o morfoespecies, de series de colecciones, en la colección de la institución receptora. El material único, de localidades únicas (singletons) debe ser retornado al MHNGOV, posterior a su estudio.</p>
                            <p class="text-text-secondary italic text-xs border-l-2 border-border pl-3">
                                <span class="font-semibold not-italic">Nota:</span> En caso de requerir el depósito de réplicas en la institución receptora, se deben notificar los detalles previamente al funcionario responsable, puesto que es necesario firmar un documento de "préstamo permanente".
                            </p>
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                        <p>No se realizan préstamos permanentes de material único. Sin embargo, bajo petición de la institución interesada, los casos excepcionales serán analizados.</p>
                    </li>

                    <li class="flex gap-3">
                        <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                        <p>En caso de existir especies nuevas para la ciencia, todos los holotipos, sin excepción, deben retornar al MHNGOV. En el caso de paratipos, el autor(s) de la(s) descripción(es) tiene la libertad de elegir el repositorio de destino de los mismos.</p>
                    </li>

                    <li class="flex gap-3">
                        <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                        <p><span class="font-medium">Especímenes enviados en etanol:</span> se agradece la gentileza de que sean retornados montados en alfileres o placas, con sus respectivas etiquetas, esto es, datos de colección e identificación. Esta última, conteniendo únicamente el género y/o especie.</p>
                    </li>

                    <li class="flex gap-3">
                        <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                        <p>En caso de requerir extensión del presente préstamo, favor notificarlo con al menos un mes de anticipación al vencimiento de la fecha establecida <span class="italic">(Return due date)</span>.</p>
                    </li>

                    @if($acta->condicionesGenerales)
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                            <p>{{ $acta->condicionesGenerales }}</p>
                        </li>
                    @endif

                </ul>
            </div>

            {{-- Compromiso --}}
            <div class="space-y-2">
                <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Compromiso del investigador</h2>
                <p class="text-sm text-text-primary leading-relaxed">
                    El investigador solicitante se compromete a utilizar los especímenes en préstamo únicamente para los fines
                    declarados en la presente solicitud, a mantenerlos en condiciones adecuadas de conservación, y a devolverlos
                    íntegros al Laboratorio de Invertebrados en la fecha de vencimiento indicada
                    o antes si el estudio concluye. Cualquier daño, pérdida o uso indebido de los especímenes será responsabilidad
                    del investigador y su institución.
                </p>
            </div>

            {{-- Firmas --}}
            <div class="grid grid-cols-2 gap-16 pt-8">
                <div>
                    <div class="border-t-2 border-text-primary pt-3 space-y-1">
                        <p class="text-sm font-semibold text-text-primary">Adrian Troya</p>
                        <p class="text-xs text-text-secondary">Biólogo</p>
                        <p class="text-xs text-text-secondary">Curador</p>
                        <p class="text-xs text-text-secondary">Laboratorio de Invertebrados</p>
                    </div>
                </div>
                <div>
                    @php
                        $firmaPath = 'firmas-investigador/' . $acta->id . '.png';
                        $mostrarFirma = !($sinFirma ?? false);
                        $firmaBase64 = ($mostrarFirma && \Illuminate\Support\Facades\Storage::exists($firmaPath))
                            ? 'data:image/png;base64,' . base64_encode(\Illuminate\Support\Facades\Storage::get($firmaPath))
                            : null;
                    @endphp
                    @if($firmaBase64)
                        <img src="{{ $firmaBase64 }}" alt="Firma" class="h-16 max-w-56 mb-1 object-contain">
                    @else
                        <div class="h-16"></div>
                    @endif
                    <div class="border-t-2 border-text-primary pt-3 space-y-1">
                        <p class="text-sm font-semibold text-text-primary">{{ $acta->nombreInvestigador ?? 'Investigador solicitante' }}</p>
                        <p class="text-xs text-text-secondary">{{ $acta->institucionAdscripcion }}</p>
                        <p class="text-xs text-text-secondary">
                            @if($firmaBase64)
                                Firmado digitalmente
                            @else
                                Fecha: ___________________
                            @endif
                        </p>
                    </div>
                </div>
            </div>

        </div>
    @endif

</div>

@if(request('download') == '1')
<script>
    document.addEventListener("DOMContentLoaded", () => {
        window.location.href = '{{ route('prestamos.acta.descargar-pdf', $acta?->id) }}';
    });
</script>
@endif
