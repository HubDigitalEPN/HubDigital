<x-layouts::app :title="'Documento de ingreso'">
    <section class="hub-workspace mx-auto max-w-3xl space-y-4 p-4 sm:p-6" data-documento-ingreso-listo x-data x-init="$nextTick(() => $refs.descargar.click())">
        <h1 class="text-2xl font-semibold">Tu documento de ingreso</h1>
        <p>Solicitud {{ $solicitud->numero }} · {{ $solicitud->codigo_qr }}</p>
        <p role="status">La descarga se inicia automáticamente. Si tu dispositivo la bloquea, utiliza el botón.</p>
        <a x-ref="descargar" download href="{{ route('depositos.ingreso.documento', $solicitud->id) }}" class="inline-flex rounded-lg bg-science-blue px-4 py-3 font-semibold text-white">Descargar documento de ingreso</a>
        <p>El documento incluye tu QR único. Preséntalo junto con el material físico al Técnico del Laboratorio.</p>
        <a class="text-science-blue underline" href="{{ route('prestamos.investigador.deposito.detalle', $solicitud->id) }}" wire:navigate>Ver mi solicitud</a>
    </section>
</x-layouts::app>
