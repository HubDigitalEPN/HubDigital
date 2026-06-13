{{-- Leyenda tipográfica de los resúmenes de taxonomía: cada estilo de letra
     corresponde a un rango taxonómico distinto, para leer la columna de un vistazo. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-border bg-bg-main px-3 py-2 text-xs text-text-secondary']) }}>
    <span class="inline-flex items-center gap-1.5 font-medium text-text-primary">
        <flux:icon name="information-circle" class="size-4 text-science-blue" />
        Leyenda taxonómica
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="font-serif italic text-text-primary">Aa bb</span>
        Especie
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="font-serif text-text-primary"><span class="italic">Aa</span> sp.</span>
        Género
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="font-serif text-text-primary">Aa</span>
        Subfamilia
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="font-sans font-semibold text-text-primary">Aa</span>
        Familia
    </span>
    <span class="inline-flex items-center gap-1.5">
        <span class="font-sans uppercase tracking-wide text-text-primary">Aa</span>
        Orden / rango superior
    </span>
</div>
