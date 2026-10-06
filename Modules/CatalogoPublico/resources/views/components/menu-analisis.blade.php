@props(['tipo', 'datos'])
@php $indice = config('indices_portal.'.$tipo); @endphp
<div wire:key="panel-{{ $tipo }}-{{ sha1(json_encode($datos)) }}" class="atlas-panel-tools" x-data="portalPanel(@js($tipo), @js($indice['titulo']), @js($datos))" x-on:keydown.escape="if (abierto) { $event.stopPropagation(); $event.preventDefault(); cerrar() }" x-on:click.outside="abierto = false">
    <button type="button" class="atlas-icon-button" x-ref="boton" aria-haspopup="menu" :aria-expanded="abierto.toString()" aria-controls="menu-{{ $tipo }}" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:click="abierto = !abierto; if (abierto) $nextTick(() => $refs.menu.querySelector('button').focus())"
        x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $refs.menu.querySelector('button').focus())">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
    </button>
    <div id="menu-{{ $tipo }}" class="atlas-menu" x-ref="menu" x-cloak x-show="abierto" role="menu" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:keydown.arrow-down.prevent="mover($event)" x-on:keydown.arrow-up.prevent="mover($event)" x-on:keydown.home.prevent="mover($event)" x-on:keydown.end.prevent="mover($event)" x-on:focusout="if (!$el.contains($event.relatedTarget)) abierto = false">
        <button type="button" role="menuitem" wire:click="{{ $tipo === 'especies' ? 'descargarListaEspecies' : "descargarAnalisis('$tipo')" }}" x-on:click="cerrar()">{{ $tipo === 'especies' ? 'Lista completa CSV' : 'Descargar CSV' }}</button>
        @if($tipo === 'mapa')<button type="button" role="menuitem" x-on:click="geojson()">Coordenadas GeoJSON · SIG</button>@endif
        @if($tipo === 'mapa')<button type="button" role="menuitem" x-on:click="pdf()">Exportar a PDF</button>@endif
        @if($tipo !== 'filos')<button type="button" role="menuitem" x-on:click="alternarGrafico()">{{ $tipo === 'mapa' ? 'Alternar agrupaciones y ubicaciones' : 'Alternar tipo de gráfico' }}</button>@endif
        <button type="button" role="menuitem" x-on:click="indice()">Indicador</button>
    </div>
    <span class="sr-only" role="status" x-text="aviso"></span>
    <dialog class="atlas-index-dialog" x-ref="indice" aria-labelledby="indice-titulo-{{ $tipo }}" x-on:keydown.escape.stop.prevent="$el.close()" x-on:cancel.stop.prevent="$el.close()" x-on:close="$refs.boton.focus()" x-on:click="if ($event.target === $el) $el.close()">
        <div class="atlas-index-content">
            <header><h2 id="indice-titulo-{{ $tipo }}">Indicador · {{ $indice['titulo'] }}</h2><button type="button" autofocus x-on:click="$refs.indice.close()" aria-label="Cerrar explicación">×</button></header>
            <figure>
                <img src="{{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-640.webp') }}" srcset="{{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-320.webp') }} 320w, {{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-640.webp') }} 640w" sizes="(max-width: 480px) calc(94vw - 32px), 300px" alt="Ilustración generada: {{ $indice['foto'] }}" width="640" height="480" loading="lazy" decoding="async">
                <figcaption>Ilustración generada con IA para explicar el indicador; no es una fotografía de un ejemplar de la colección.</figcaption>
            </figure>
            @if(config('figuras_portal.'.$tipo))<x-catalogopublico::figura-panel :tipo="$tipo" contexto="indicador" />@endif
            @foreach($indice['parrafos'] as $parrafo)<p>{{ $parrafo }}</p>@endforeach
        </div>
    </dialog>
</div>
