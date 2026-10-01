@props(['tipo', 'datos'])
@php $indice = config('indices_portal.'.$tipo); @endphp
<div class="atlas-panel-tools" x-data="portalPanel(@js($tipo), @js($indice['titulo']), @js($datos))" x-on:keydown.escape.stop="if (abierto) cerrar()" x-on:click.outside="abierto = false">
    <button type="button" class="atlas-icon-button" x-ref="boton" aria-haspopup="menu" :aria-expanded="abierto.toString()" aria-controls="menu-{{ $tipo }}" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:click="abierto = !abierto; if (abierto) $nextTick(() => $refs.menu.querySelector('button').focus())"
        x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $refs.menu.querySelector('button').focus())">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
    </button>
    <div id="menu-{{ $tipo }}" class="atlas-menu" x-ref="menu" x-cloak x-show="abierto" role="menu" aria-label="Opciones de {{ $indice['titulo'] }}"
        x-on:keydown.arrow-down.prevent="mover($event)" x-on:keydown.arrow-up.prevent="mover($event)" x-on:keydown.home.prevent="mover($event)" x-on:keydown.end.prevent="mover($event)" x-on:focusout="if (!$el.contains($event.relatedTarget)) abierto = false">
        <button type="button" role="menuitem" wire:click="{{ $tipo === 'especies' ? 'descargarListaEspecies' : "descargarAnalisis('$tipo')" }}" x-on:click="cerrar()">{{ $tipo === 'especies' ? 'Lista completa CSV' : 'Descargar CSV' }}</button>
        <button type="button" role="menuitem" x-on:click="json()">Datos del panel JSON</button>
        @if($tipo === 'mapa')<button type="button" role="menuitem" x-on:click="geojson()">Cuadrículas GeoJSON · SIG</button>@endif
        <button type="button" role="menuitem" x-on:click="enlace()">Copiar enlace con filtros</button>
        <button type="button" role="menuitem" x-on:click="cita()">Descargar cita y consulta</button>
        <button type="button" role="menuitem" x-on:click="indice()">Índice</button>
    </div>
    <span class="sr-only" role="status" x-text="aviso"></span>
    <dialog class="atlas-index-dialog" x-ref="indice" aria-labelledby="indice-titulo-{{ $tipo }}" x-on:close="$refs.boton.focus()" x-on:click="if ($event.target === $el) $el.close()">
        <div class="atlas-index-content">
            <header><h2 id="indice-titulo-{{ $tipo }}">Índice · {{ $indice['titulo'] }}</h2><button type="button" autofocus x-on:click="$refs.indice.close()" aria-label="Cerrar explicación">×</button></header>
            <figure><img src="{{ asset('images/indices/'.$tipo.'.png') }}" alt="{{ $indice['foto'] }}" width="1448" height="1086" loading="lazy"><figcaption>Imagen ilustrativa creada con IA para este indicador.</figcaption></figure>
            @foreach($indice['parrafos'] as $parrafo)<p>{{ $parrafo }}</p>@endforeach
        </div>
    </dialog>
</div>
