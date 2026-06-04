@once
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 1.8cm 2cm;
    }

    /* Ocultar el layout de la app (sidebar, header de Flux UI) */
    [data-flux-sidebar],
    [data-flux-header],
    aside,
    header,
    nav {
        display: none !important;
    }

    #acta-doc {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
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

<div class="p-6 space-y-4">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between print:hidden">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item>Acta de préstamo</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $acta?->numero_prestamo ?? '—' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="flex items-center gap-3">
            <flux:text class="text-xs text-text-secondary text-right leading-tight max-w-48">
                En el diálogo, desactiva<br><span class="font-medium">"Encabezados y pies de página"</span>
            </flux:text>
            <flux:button icon="printer"
                onclick="let t=document.title; document.title='Acta{{ $acta?->solicitud?->numero_solicitud ?? $acta?->numero_prestamo }}'; window.print(); setTimeout(()=>document.title=t, 500)">
                Imprimir / Descargar PDF
            </flux:button>
        </div>
    </div>

    @if(!$acta)
        <flux:callout variant="danger" icon="exclamation-triangle">Acta no encontrada.</flux:callout>
    @else
        {{-- Document --}}
        <div id="acta-doc" class="bg-white rounded-lg border border-border shadow-sm mx-auto max-w-3xl p-10 space-y-8 print:shadow-none print:border-none print:max-w-full print:rounded-none print:p-0">

            {{-- Header --}}
            <div class="text-center space-y-1">
                <p class="text-xs text-text-secondary uppercase tracking-widest">Laboratorio de Invertebrados de la Escuela Politécnica Nacional</p>
                <h1 class="text-2xl font-bold text-text-primary font-serif mt-2">ACTA DE PRÉSTAMO DE ESPECÍMENES</h1>
                <p class="font-mono text-sm text-text-secondary mt-1">{{ $acta->numero_prestamo }}</p>
            </div>

            <hr class="border-border" />

            {{-- Datos del préstamo --}}
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-text-primary uppercase tracking-widest">Condiciones del préstamo</h2>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div>
                        <dt class="text-text-secondary">Tipo de préstamo</dt>
                        <dd class="font-medium text-text-primary capitalize">{{ $acta->tipo_prestamo }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">N.º solicitud</dt>
                        <dd class="font-mono font-medium text-text-primary">{{ $acta->solicitud?->numero_solicitud }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de inicio</dt>
                        <dd class="font-medium text-text-primary">
                            {{ ($acta->fecha_inicio instanceof \DateTimeInterface ? $acta->fecha_inicio : \Carbon\Carbon::parse($acta->fecha_inicio))->format('d/m/Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de vencimiento</dt>
                        <dd class="font-medium text-text-primary">
                            {{ ($acta->fecha_fin instanceof \DateTimeInterface ? $acta->fecha_fin : \Carbon\Carbon::parse($acta->fecha_fin))->format('d/m/Y') }}
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
                        <dd class="font-medium text-text-primary mt-0.5">{{ $acta->solicitud?->titulo_estudio }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Institución de adscripción</dt>
                        <dd class="font-medium text-text-primary">{{ $acta->solicitud?->institucion_adscripcion }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Línea de investigación</dt>
                        <dd class="font-medium text-text-primary">{{ $acta->solicitud?->linea_investigacion }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Propósito del préstamo</dt>
                        <dd class="text-text-primary mt-0.5">{{ $acta->solicitud?->proposito_prestamo }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Especímenes --}}
            @if($acta->solicitud?->items && $acta->solicitud->items->count())
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
                            @foreach($acta->solicitud->items as $i => $item)
                                <tr class="{{ $loop->even ? 'bg-bg-main' : 'bg-white' }}">
                                    <td class="px-3 py-2 border-b border-border text-text-secondary">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 border-b border-border font-mono text-text-primary">{{ $item->especimen_codigo_externo }}</td>
                                    <td class="px-3 py-2 border-b border-border text-text-primary">{{ $item->cantidad_solicitada }}</td>
                                    <td class="px-3 py-2 border-b border-border text-text-secondary text-xs">{{ $item->condiciones_especificas ?? '—' }}</td>
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

                    @if($acta->condiciones_generales)
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-blue-navy"></span>
                            <p>{{ $acta->condiciones_generales }}</p>
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
                    íntegros al Laboratorio de Invertebrados de la Escuela Politécnica Nacional en la fecha de vencimiento indicada
                    o antes si el estudio concluye. Cualquier daño, pérdida o uso indebido de los especímenes será responsabilidad
                    del investigador y su institución.
                </p>
            </div>

            {{-- Firmas --}}
            <div class="grid grid-cols-2 gap-16 pt-8">
                <div>
                    <div class="border-t-2 border-text-primary pt-3 space-y-1">
                        <p class="text-sm font-semibold text-text-primary">Funcionario responsable</p>
                        <p class="text-xs text-text-secondary">Laboratorio de Invertebrados — EPN</p>
                        <p class="text-xs text-text-secondary">Fecha: ___________________</p>
                    </div>
                </div>
                <div>
                    <div class="border-t-2 border-text-primary pt-3 space-y-1">
                        <p class="text-sm font-semibold text-text-primary">Investigador solicitante</p>
                        <p class="text-xs text-text-secondary">{{ $acta->solicitud?->institucion_adscripcion }}</p>
                        <p class="text-xs text-text-secondary">Fecha: ___________________</p>
                    </div>
                </div>
            </div>

        </div>
    @endif

</div>
