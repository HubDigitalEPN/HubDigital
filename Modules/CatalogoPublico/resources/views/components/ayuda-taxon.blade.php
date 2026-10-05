@props(['nombre', 'nivel', 'registros' => 0, 'stats' => [], 'jerarquia' => [], 'fotoPublica' => null])
@php
    $jerarquiaAyuda = array_merge($jerarquia, [$nivel => $nombre]);
    $ilustracion = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon($jerarquiaAyuda);
    $etiqueta = ['kingdom' => 'Reino', 'phylum' => 'Filo', 'class' => 'Clase', 'order' => 'Orden', 'family' => 'Familia', 'genus' => 'Género', 'species' => 'Especie'][$nivel] ?? $nivel;
    $fotografiaAyuda = $nivel === 'species' && is_string($fotoPublica) && trim($fotoPublica) !== ''
        ? view('catalogopublico::components.fotografia-ejemplar', ['url' => $fotoPublica, 'nombre' => $nombre])->render()
        : view('catalogopublico::components.fotografia-mosaico', ['fotos' => \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaTaxon($jerarquiaAyuda), 'taxon' => $ilustracion['taxon_consulta'] ?? $jerarquiaAyuda, 'contexto' => 'ayuda-taxon', 'limite' => 1, 'retrato' => true, 'compacto' => true])->render();
    $datosAyuda = ['nombre' => $nombre, 'nivel' => $etiqueta, 'jerarquia' => $jerarquiaAyuda, 'registros' => (int) $registros, 'stats' => $stats, 'fotografia' => $fotografiaAyuda];
@endphp
<button type="button" class="collection-taxon-help" x-on:click.prevent.stop="abrirTaxon($event.currentTarget, @js($datosAyuda))" aria-label="Resumen de {{ $nombre }}" title="Resumen de {{ $nombre }}">?</button>
