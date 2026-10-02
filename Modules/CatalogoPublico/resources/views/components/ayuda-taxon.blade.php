@props(['nombre', 'nivel', 'registros' => 0, 'stats' => [], 'jerarquia' => []])
@php
    $jerarquiaAyuda = array_merge($jerarquia, [$nivel => $nombre]);
    $ilustracion = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($jerarquiaAyuda);
    $etiqueta = ['kingdom' => 'Reino', 'phylum' => 'Filo', 'class' => 'Clase', 'order' => 'Orden', 'family' => 'Familia', 'genus' => 'Género', 'species' => 'Especie'][$nivel] ?? $nivel;
    $representacionAyuda = $nivel === 'species' ? view('catalogopublico::components.representacion-especie', ['nombre' => $nombre, 'jerarquia' => $jerarquiaAyuda, 'ilustracion' => $ilustracion])->render() : null;
    $datosAyuda = ['nombre' => $nombre, 'nivel' => $etiqueta, 'registros' => (int) $registros, 'stats' => $stats, 'ilustracion' => $ilustracion, 'representacion' => $representacionAyuda];
@endphp
<button type="button" class="collection-taxon-help" x-on:click.prevent.stop="abrirTaxon($event.currentTarget, @js($datosAyuda))" aria-label="¿Qué es {{ $nombre }}?" title="¿Qué es {{ $nombre }}?">?</button>
