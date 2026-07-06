<div class="p-6 space-y-4">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between">
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
        {{-- Documento: se sirve la plantilla canónica (pdf.acta-documento) vía el
             embed, la misma que genera el PDF de descarga. Fuente de verdad única. --}}
        <iframe src="{{ route('prestamos.acta.embed', $acta->id) }}"
            style="height: 85vh;"
            class="w-full rounded-lg border border-border bg-white shadow-sm"
            title="Acta de préstamo"></iframe>
    @endif

</div>

@if(request('download') == '1')
<script>
    document.addEventListener("DOMContentLoaded", () => {
        window.location.href = '{{ route('prestamos.acta.descargar-pdf', $acta?->id) }}';
    });
</script>
@endif
