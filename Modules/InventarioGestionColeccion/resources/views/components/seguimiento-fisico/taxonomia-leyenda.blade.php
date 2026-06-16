{{-- Leyenda tipográfica de los resúmenes de taxonomía: cada estilo de letra
     corresponde a un rango taxonómico distinto, para leer la columna de un vistazo. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-border bg-bg-main px-3 py-2 text-xs text-text-secondary']) }}>
    <span class="inline-flex items-center gap-1.5 font-medium text-text-primary">
        <flux:icon name="information-circle" class="size-4 text-science-blue" />
        Leyenda taxonómica
    </span>
    <span class="inline-flex items-center gap-1.5">
        <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="especie" texto="Aa bb" class="text-text-primary" />
        Especie
    </span>
    <span class="inline-flex items-center gap-1.5">
        <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="genero" texto="Aa" class="text-text-primary" />
        Género
    </span>
    <span class="inline-flex items-center gap-1.5">
        <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="subfamilia" texto="Aa" class="text-text-primary" />
        Subfamilia
    </span>
    <span class="inline-flex items-center gap-1.5">
        <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="familia" texto="Aa" class="text-text-primary" />
        Familia
    </span>
    <span class="inline-flex items-center gap-1.5">
        <x-inventariogestioncoleccion::seguimiento-fisico.taxon-tipografico estilo="superior" texto="Aa" class="text-text-primary" />
        Orden / rango superior
    </span>
</div>
