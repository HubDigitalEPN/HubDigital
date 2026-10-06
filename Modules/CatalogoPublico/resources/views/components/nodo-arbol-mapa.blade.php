<button type="button" class="atlas-dendrogram-node {{ $nodoDendrograma['hoja'] ? 'is-leaf' : '' }} {{ $nodoDendrograma['en_linaje'] ? 'is-lineage' : '' }}"
    wire:key="dendrograma-mapa-{{ $nodoDendrograma['id'] }}" data-taxon-id="{{ $nodoDendrograma['id'] }}" data-rango="{{ $nodoDendrograma['rango'] }}"
    wire:click="navegarCelda(@js($nodoDendrograma['id']))" wire:loading.attr="disabled" wire:target="navegarCelda,volverCelda"
    aria-pressed="{{ $nodoDendrograma['seleccionado'] ? 'true' : 'false' }}" aria-controls="titulo-info-taxon"
    aria-label="Ver {{ $nodoDendrograma['nombre'] }}, {{ $nodoDendrograma['etiqueta'] }}{{ $nodoDendrograma['mostrar_total'] ? ', '.$nodoDendrograma['total'].' registros públicos' : '' }}.{{ $nodoDendrograma['padre_nombre'] ? ' Taxón padre: '.$nodoDendrograma['padre_nombre'].'.' : '' }}"
    title="{{ $nodoDendrograma['nombre'] }} · {{ $nodoDendrograma['etiqueta'] }}{{ $nodoDendrograma['mostrar_total'] ? ' · '.$nodoDendrograma['total'].' registros públicos' : '' }}"
    @if($disposicionFluida ?? false) :style="estiloNodo(@js($nodoDendrograma['id']))" @endif
    @if(!($disposicionFluida ?? false)) style="left:{{ $nodoDendrograma['x'] / $dendrograma['ancho'] * 100 }}%;top:{{ $nodoDendrograma['superior'] }}px;width:{{ $nodoDendrograma['ancho_nodo'] / $dendrograma['ancho'] * 100 }}%;height:{{ $nodoDendrograma['alto'] }}px" @endif>
    <span class="atlas-dendrogram-label"><strong>{{ $nodoDendrograma['nombre'] }}</strong><small>{{ $nodoDendrograma['etiqueta'] }}@if($nodoDendrograma['mostrar_total']) · <span>{{ number_format($nodoDendrograma['total'], 0, ',', '.') }} {{ $nodoDendrograma['total'] === 1 ? 'registro' : 'registros' }}</span>@endif</small></span>
</button>
