<div>
<div class="space-y-4" data-editor-visual-portal x-data="portalVisualEditor($wire, @js($mapa), @js('portal:'.\App\Support\EditorPortal::pagina($grupo)), @js($paginasEditor), @js($originales))" x-on:portal-texto-publicado.window="recargar(true)" x-on:foto-editorial-publicada.window="recargar()" wire:key="editor-visual-portal">
    <p class="sr-only">Navega por el portal. Usa doble clic o Intro para editar un texto o una fotografía.</p>
    @if(session('portal-textos'))<p role="status" x-show="!mensajePublicacion" class="hub-panel p-3 text-success">{{ session('portal-textos') }}</p>@endif
    <p role="status" x-show="mensajePublicacion" x-text="mensajePublicacion" x-cloak class="hub-panel p-3 text-success"></p>
    @if($errors->any())<div role="alert" class="hub-panel p-3 text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section x-ref="opciones" x-show="opciones.length" x-cloak class="hub-panel p-4" aria-label="Elegir ubicación del texto repetido">
        <p class="font-semibold">Elige la ubicación que deseas editar</p>
        <ul>@foreach($mapa as $clave => $valor)<li x-show="opciones.includes(@js($clave))"><button type="button" x-on:click="editar(@js($clave))" class="min-h-11 p-2 text-left underline">{{ $grupos[explode('.', $clave)[0]] ?? 'Texto de esta página' }} · {{ str_replace('_', ' ', explode('.', $clave)[1] ?? $clave) }}: {{ \Illuminate\Support\Str::limit($valor, 80) }}</button></li>@endforeach</ul>
    </section>
    @if($vista)
        <section class="hub-panel overflow-hidden" aria-label="Vista del portal para seleccionar textos">
            <iframe wire:ignore sandbox="allow-same-origin" x-ref="portal" x-on:load="iniciar()" src="{{ $vista }}" title="Vista editable: {{ $grupos[$grupo] }}" class="hub-editor-portal-frame"></iframe>
        </section>
    @endif
    @include('livewire.administracion.partials.foto-editorial')
    <flux:modal name="portal-texto-inmediato" aria-labelledby="editor-texto-titulo" class="w-full max-w-xl">
        <form x-on:submit.prevent="publicarTexto()" class="space-y-4" data-copy-preserve>
            <h2 id="editor-texto-titulo" class="hub-section-title">Editar texto seleccionado</h2>
            <p role="alert" x-show="errorSeleccion" x-text="errorSeleccion"></p>
            <p class="whitespace-pre-line rounded border border-border p-3 text-sm"><strong>Texto original:</strong> <span x-text="originalLocal"></span></p>
            <label wire:ignore class="grid gap-2 text-sm font-semibold">Texto que se publicará<textarea x-model="textoLocal" x-bind:disabled="publicando" rows="6" maxlength="5000" required class="w-full rounded border border-border bg-surface p-3"></textarea></label>
            <p class="text-xs text-text-secondary">Conserva los marcadores entre llaves cuando el texto contiene enlaces o cifras automáticas.</p>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="button" x-on:click="$flux.modal('portal-texto-inmediato').close()">Cancelar</flux:button><flux:button type="submit" variant="primary" :loading="false" x-bind:disabled="publicando">Guardar y publicar texto</flux:button></div>
        </form>
    </flux:modal>
</div>

</div>
