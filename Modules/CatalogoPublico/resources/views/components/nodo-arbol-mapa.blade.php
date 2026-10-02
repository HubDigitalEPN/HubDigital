@php
    $hijosNodo = $ramas[(string) $nodo['id']] ?? [];
    $etiquetaRango = ['reino' => 'Reino', 'phylum' => 'Filo', 'filo' => 'Filo', 'clase' => 'Clase', 'orden' => 'Orden', 'familia' => 'Familia', 'genero' => 'Género', 'especie' => 'Especie'][$nodo['rango']] ?? ucfirst($nodo['rango']);
@endphp
<li wire:key="rama-mapa-{{ $nodo['id'] }}">
    <button type="button" class="atlas-tree-node" wire:click="navegarCelda('{{ $nodo['id'] }}')" wire:loading.attr="disabled"
        aria-pressed="{{ (string) ($seleccionado['id'] ?? '') === (string) $nodo['id'] ? 'true' : 'false' }}">
        <small>{{ $etiquetaRango }}</small><strong>{{ $nodo['nombre'] }}</strong><span>{{ number_format((int) $nodo['total'], 0, ',', '.') }} registros</span>
    </button>
    @if($hijosNodo !== [])
        <ul>
            @foreach($hijosNodo as $hijoNodo)
                @include('catalogopublico::components.nodo-arbol-mapa', ['nodo' => $hijoNodo])
            @endforeach
        </ul>
    @endif
</li>
