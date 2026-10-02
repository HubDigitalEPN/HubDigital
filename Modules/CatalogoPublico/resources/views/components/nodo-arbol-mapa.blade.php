<button type="button" class="atlas-dendrogram-node {{ $nodoDendrograma['hoja'] ? 'is-leaf' : '' }} {{ $nodoDendrograma['en_linaje'] ? 'is-lineage' : '' }}"
    wire:key="dendrograma-mapa-{{ $nodoDendrograma['id'] }}" data-taxon-id="{{ $nodoDendrograma['id'] }}" data-rango="{{ $nodoDendrograma['rango'] }}"
    wire:click="navegarCelda(@js($nodoDendrograma['id']))" wire:loading.attr="disabled" wire:target="navegarCelda,paginarArbolCelda,volverCelda"
    aria-pressed="{{ $nodoDendrograma['seleccionado'] ? 'true' : 'false' }}" aria-controls="titulo-info-taxon"
    aria-label="Ver {{ $nodoDendrograma['nombre'] }}, {{ $nodoDendrograma['etiqueta'] }}, {{ $nodoDendrograma['total'] }} registros públicos.{{ $nodoDendrograma['padre_nombre'] ? ' Taxón padre: '.$nodoDendrograma['padre_nombre'].'.' : '' }}"
    title="{{ $nodoDendrograma['nombre'] }} · {{ $nodoDendrograma['etiqueta'] }} · {{ $nodoDendrograma['total'] }} registros públicos"
    style="left:{{ $nodoDendrograma['x'] / $dendrograma['ancho'] * 100 }}%;top:{{ $nodoDendrograma['superior'] }}px;height:{{ $nodoDendrograma['alto'] }}px">
    @if($nodoDendrograma['miniatura'])<img class="atlas-dendrogram-thumbnail" src="{{ $nodoDendrograma['miniatura']['url'] }}" alt="{{ $nodoDendrograma['miniatura']['alt'] }}" width="36" height="36" loading="lazy" decoding="async">@endif
    <span class="atlas-dendrogram-label"><strong>{{ $nodoDendrograma['nombre'] }}</strong><small>{{ $nodoDendrograma['etiqueta'] }} · <span>{{ number_format($nodoDendrograma['total'], 0, ',', '.') }} {{ $nodoDendrograma['total'] === 1 ? 'registro' : 'registros' }}</span></small></span>
</button>
