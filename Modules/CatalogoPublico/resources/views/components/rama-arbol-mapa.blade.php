@php
    $descendientes = $hijosArbol[(string) $nodoDendrograma['id']] ?? [];
@endphp
<li class="atlas-tree-branch" wire:key="rama-mapa-{{ $nodoDendrograma['id'] }}" data-padre-id="{{ $nodoDendrograma['padre_id'] }}" data-hijo-id="{{ $nodoDendrograma['id'] }}">
    @include('catalogopublico::components.nodo-arbol-mapa', ['nodoDendrograma' => $nodoDendrograma, 'disposicionFluida' => true])
    @if($descendientes !== [])
        <ul class="atlas-tree-children">
            @foreach($descendientes as $hijoDendrograma)
                @include('catalogopublico::components.rama-arbol-mapa', ['nodoDendrograma' => $hijoDendrograma])
            @endforeach
        </ul>
    @endif
</li>
