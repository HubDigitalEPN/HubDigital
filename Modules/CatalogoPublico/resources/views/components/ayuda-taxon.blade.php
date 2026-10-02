@props(['nombre', 'nivel', 'registros' => 0, 'stats' => [], 'jerarquia' => []])
@php
    $ilustracion = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon(array_merge($jerarquia, [$nivel => $nombre]));
    $etiqueta = ['kingdom' => 'Reino', 'phylum' => 'Filo', 'class' => 'Clase', 'order' => 'Orden', 'family' => 'Familia', 'genus' => 'Género', 'species' => 'Especie'][$nivel] ?? $nivel;
    $datosAyuda = ['nombre' => $nombre, 'nivel' => $etiqueta, 'registros' => (int) $registros, 'stats' => $stats, 'ilustracion' => $ilustracion];
@endphp
<button type="button" class="collection-taxon-help" x-on:click.prevent.stop="abrirTaxon($event.currentTarget, @js($datosAyuda))" aria-label="¿Qué es {{ $nombre }}?" title="¿Qué es {{ $nombre }}?">?</button>
