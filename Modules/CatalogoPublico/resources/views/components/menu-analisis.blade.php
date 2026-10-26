@props(['tipo', 'datos'])
@php $indice = \App\Support\PortalCopy::configuration('indices_portal')[$tipo]; @endphp
<div wire:key="panel-{{ $tipo }}-{{ sha1(json_encode($datos)) }}" class="atlas-panel-tools" x-data="portalPanel(@js($tipo), @js($indice['titulo']), @js($datos))" x-on:keydown.escape="if (abierto) { $event.stopPropagation(); $event.preventDefault(); cerrar() }" x-on:click.outside="abierto = false">
    <button type="button" class="atlas-icon-button" x-ref="boton" aria-haspopup="menu" :aria-expanded="abierto.toString()" aria-controls="menu-{{ $tipo }}" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:click="abierto = !abierto; if (abierto) $nextTick(() => $refs.menu.querySelector('button').focus())"
        x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $refs.menu.querySelector('button').focus())">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
    </button>
    <div id="menu-{{ $tipo }}" class="atlas-menu" x-ref="menu" x-cloak x-show="abierto" role="menu" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:keydown.arrow-down.prevent="mover($event)" x-on:keydown.arrow-up.prevent="mover($event)" x-on:keydown.home.prevent="mover($event)" x-on:keydown.end.prevent="mover($event)" x-on:focusout="if (!$el.contains($event.relatedTarget)) abierto = false">
        @if(\App\Support\DescargasCatalogo::permitidas())
        <button type="button" role="menuitem" data-catalogo-descarga x-on:click="window.location.assign(@js($this->urlDescarga($tipo))); cerrar()">{{ \App\Support\PortalCopy::text($tipo === 'especies' ? 'catalogo.lista_csv' : 'catalogo.descargar_csv') }}</button>
        @if($tipo === 'mapa')<button type="button" role="menuitem" x-on:click="geojson()">{{ \App\Support\PortalCopy::text('catalogo.geojson') }}</button>@endif
        @if($tipo === 'mapa')<button type="button" role="menuitem" x-on:click="pdf()">{{ \App\Support\PortalCopy::text('catalogo.pdf') }}</button>@endif
        @endif
        @if(!in_array($tipo, ['filos', 'mapa'], true))<button type="button" role="menuitem" x-on:click="alternarGrafico()">{{ \App\Support\PortalCopy::text('catalogo.alternar_grafico') }}</button>@endif
        <button type="button" role="menuitem" x-on:click="indice()">{{ \App\Support\PortalCopy::text('catalogo.explicar_metrica') }}</button>
    </div>
    <span class="sr-only" role="status" x-text="aviso"></span>
    <dialog class="atlas-index-dialog" x-ref="indice" aria-labelledby="indice-titulo-{{ $tipo }}" x-on:keydown.escape.stop.prevent="$el.close()" x-on:cancel.stop.prevent="$el.close()" x-on:close="$refs.boton.focus()" x-on:click="if ($event.target === $el) $el.close()">
        <div class="atlas-index-content">
            <header><h2 id="indice-titulo-{{ $tipo }}">{{ $indice['titulo'] }}</h2><button type="button" autofocus x-on:click="$refs.indice.close()" aria-label="{{ \App\Support\PortalCopy::text('catalogo.cerrar_explicacion') }}">×</button></header>
            <figure>
                <img src="{{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-640.webp') }}" srcset="{{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-320.webp') }} 320w, {{ asset('images/indices/'.($indice['imagen'] ?? $tipo).'-640.webp') }} 640w" sizes="(max-width: 480px) calc(94vw - 32px), 300px" alt="Ilustración generada: {{ $indice['foto'] }}" width="640" height="480" loading="lazy" decoding="async">
            </figure>
            @if(config('figuras_portal.'.$tipo))<x-catalogopublico::figura-panel :tipo="$tipo" contexto="indicador" />@endif
            @foreach($indice['parrafos'] as $parrafo)<p>{{ $parrafo }}</p>@endforeach
        </div>
    </dialog>
</div>
