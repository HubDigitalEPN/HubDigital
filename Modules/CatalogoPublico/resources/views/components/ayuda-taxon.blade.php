@props(['nombre', 'nivel', 'registros' => 0, 'stats' => [], 'jerarquia' => []])
@php
    $jerarquiaAyuda = array_merge($jerarquia, [$nivel => $nombre]);
    $ilustracion = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($jerarquiaAyuda);
    $etiqueta = ['kingdom' => 'Reino', 'phylum' => 'Filo', 'class' => 'Clase', 'order' => 'Orden', 'family' => 'Familia', 'genus' => 'Género', 'species' => 'Especie'][$nivel] ?? $nivel;
    $representacionAyuda = $nivel === 'species' ? view('catalogopublico::components.representacion-especie', ['nombre' => $nombre, 'jerarquia' => $jerarquiaAyuda, 'ilustracion' => $ilustracion, 'contexto' => 'ayuda-taxon'])->render() : null;
    $fotografiaAyuda = view('catalogopublico::components.fotografia-mosaico', ['fotos' => \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($jerarquiaAyuda), 'taxon' => $ilustracion['taxon_consulta'] ?? $jerarquiaAyuda, 'contexto' => 'ayuda-taxon'])->render();
    $datosAyuda = ['nombre' => $nombre, 'nivel' => $etiqueta, 'registros' => (int) $registros, 'stats' => $stats, 'ilustracion' => $ilustracion, 'representacion' => $representacionAyuda, 'fotografia' => $fotografiaAyuda];
@endphp
<button type="button" class="collection-taxon-help" x-on:click.prevent.stop="abrirTaxon($event.currentTarget, @js($datosAyuda))" aria-label="¿Qué es {{ $nombre }}?" title="¿Qué es {{ $nombre }}?">?</button>
