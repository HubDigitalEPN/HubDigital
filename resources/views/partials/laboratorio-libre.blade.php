<div class="lab-free-canvas" data-lab-free data-lab-personal>
    @foreach(\App\Services\DisenoLaboratorio::disponer($pagina) as $id => $elemento)
        @php($p = $elemento['posicion'])
        <div class="lab-free-item" data-lab-element="{{ $id }}" data-lab-label="{{ $elemento['etiqueta'] }}" data-lab-kind="{{ $elemento['tipo'] }}" data-lab-y="{{ $p['y'] }}" @if(!isset($pagina['posiciones'][$id])) data-lab-auto @endif style="left:{{ $p['x'] }}%;top:{{ $p['y'] }}cqw;width:{{ $p['ancho'] }}%;font-size:{{ $p['fuente'] }}cqw;z-index:{{ $p['capa'] }};{{ $elemento['tipo'] === 'foto' ? 'height:'.$p['alto'].'cqw;' : '' }}">
            @if($elemento['tipo'] === 'foto')
                <x-laboratorio-foto :clave="$elemento['clave']" :foto="$elemento['foto']" :marcar="false" />
            @elseif($elemento['tipo'] === 'enlace')
                <a href="mailto:{{ $pagina['textos']['contacto_email'] }}">{{ $elemento['texto'] }}</a>
            @elseif($elemento['tag'] === 'h1')<h1>{{ $elemento['texto'] }}</h1>
            @elseif($elemento['tag'] === 'h2')<h2>{{ $elemento['texto'] }}</h2>
            @elseif($elemento['tag'] === 'h3')<h3>{{ $elemento['texto'] }}</h3>
            @else<x-laboratorio-texto :texto="$elemento['texto']" />
            @endif
        </div>
    @endforeach
</div>
