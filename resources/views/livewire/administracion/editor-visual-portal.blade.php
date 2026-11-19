<div class="space-y-4" data-editor-visual-portal x-data="portalVisualEditor($wire, @js($mapa), @js('portal:'.\App\Support\EditorPortal::pagina($grupo)), @js(\App\Support\EditorPortal::version(\App\Support\DisposicionContenido::clave('portal:'.\App\Support\EditorPortal::pagina($grupo)))), @js((object) \App\Support\DisposicionContenido::leer('portal:'.\App\Support\EditorPortal::pagina($grupo))))" x-on:portal-texto-publicado.window="recargar()" wire:key="editor-visual-{{ $grupo }}">
    <header><h2 class="hub-section-title">Edita los textos sobre el portal</h2><p class="mt-2 text-sm text-text-secondary">Selecciona un texto en su ubicación original. Los cambios se publican al guardar. Los datos científicos y los documentos firmados conservan sus propios procesos.</p></header>
    <div class="hub-panel hub-editor-filtros flex flex-wrap items-end gap-4 p-4">
        <label class="grid min-w-56 flex-1 gap-2 text-sm font-semibold">Página o sección<select wire:model.live="grupo" class="min-h-11 rounded border border-border bg-surface p-2">@foreach($grupos as $id => $nombre)<option value="{{ $id }}">{{ $nombre }}</option>@endforeach</select></label>
        @if($vista)<a href="{{ $vista }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm text-science-blue underline">Abrir página publicada ↗</a>@endif
    </div>
    @include('livewire.administracion.partials.herramientas-disposicion')
    @if(session('portal-textos'))<p role="status" class="hub-panel p-3 text-success">{{ session('portal-textos') }}</p>@endif
    @if($errors->any())<div role="alert" class="hub-panel p-3 text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section x-ref="opciones" x-show="opciones.length" x-cloak class="hub-panel p-4" aria-label="Elegir ubicación del texto repetido">
        <p class="font-semibold">Elige la ubicación que deseas editar</p>
        <ul>@foreach($mapa as $clave => $valor)<li x-show="opciones.includes(@js($clave))"><button type="button" x-on:click="editar(@js($clave))" class="min-h-11 p-2 text-left underline">{{ $grupos[explode('.', $clave)[0]] ?? 'Texto de esta página' }} · {{ str_replace('_', ' ', explode('.', $clave)[1] ?? $clave) }}: {{ \Illuminate\Support\Str::limit($valor, 80) }}</button></li>@endforeach</ul>
    </section>
    @if($vista)
        <section class="hub-panel overflow-hidden" aria-label="Vista del portal para seleccionar textos">
            <p class="border-b border-border p-3 text-sm" role="status" x-text="mensaje"></p>
            <iframe wire:ignore x-ref="portal" x-on:load="iniciar()" src="{{ $vista }}" title="Vista editable: {{ $grupos[$grupo] }}" class="hub-editor-portal-frame"></iframe>
        </section>
    @endif
    <details class="hub-panel p-4" x-data="{buscar:''}">
        <summary class="cursor-pointer font-semibold">Todos los campos de esta sección, incluidos textos accesibles y ventanas</summary>
        <label class="mt-4 grid gap-2 text-sm">Buscar campo<input type="search" x-model="buscar" class="min-h-11 rounded border border-border p-2"></label>
        <ul class="mt-3 divide-y divide-border">@foreach($campos as $clave => $defecto)
            <li x-show="@js(mb_strtolower($defecto)).includes(buscar.toLowerCase().trim())"><button type="button" x-on:click="editar(@js($grupo.'.'.$clave))" class="block min-h-11 w-full whitespace-pre-line p-3 text-left text-sm hover:bg-science-blue/5"><span class="block font-semibold">{{ \Illuminate\Support\Str::limit($defecto, 110) }}</span><span class="text-text-secondary">{{ \Illuminate\Support\Str::limit($textos[$grupo][$clave], 200) }}</span></button></li>
        @endforeach</ul>
    </details>
    <flux:modal name="portal-texto-inmediato" wire:model="editorAbierto" aria-labelledby="editor-texto-titulo" class="w-full max-w-xl">
        <form x-on:submit.prevent="publicarTexto()" class="space-y-4" data-copy-preserve>
            <h2 id="editor-texto-titulo" class="hub-section-title">Editar texto seleccionado</h2>
            <p role="status" x-show="seleccionOcupada || errorSeleccion" x-text="errorSeleccion || 'Cargando el texto guardado…'"></p>
            <p class="whitespace-pre-line rounded border border-border p-3 text-sm"><strong>Texto original:</strong> <span x-text="originalLocal"></span></p>
            <label wire:ignore class="grid gap-2 text-sm font-semibold">Texto que se publicará<textarea x-model="textoLocal" x-bind:disabled="seleccionOcupada || !!errorSeleccion || publicando" rows="6" maxlength="5000" required class="w-full rounded border border-border bg-surface p-3"></textarea></label>
            @error('textoActual')<p class="text-sm text-error" role="alert">{{ $message }}</p>@enderror
            <p class="text-xs text-text-secondary">Conserva los marcadores entre llaves cuando el texto contiene enlaces o cifras automáticas.</p>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" x-on:click="$flux.modal('portal-texto-inmediato').close()">Cancelar</flux:button><flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="seleccionOcupada || !!errorSeleccion || publicando">Guardar y publicar texto</flux:button></div>
        </form>
    </flux:modal>
</div>
