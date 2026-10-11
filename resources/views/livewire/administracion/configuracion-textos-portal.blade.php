<div class="hub-workspace mx-auto max-w-7xl space-y-5 p-3 sm:p-5">
    <header class="hub-page-header">
        <div><p class="hub-page-kicker">Administración de contenido</p><h1 class="hub-page-title">Textos del portal</h1>
            <p class="mt-2 text-sm text-text-secondary">Edita títulos, descripciones, etiquetas, ayudas y pies de figura. Los cambios se publican al guardar.</p></div>
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue underline">Abrir portal ↗</a>
    </header>
    @if(session('portal-textos'))<p role="status" class="rounded-lg border border-success/30 bg-success/5 p-3 text-success">{{ session('portal-textos') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-error/30 p-3 text-error"><p>No se guardaron los cambios. Revisa estos textos:</p><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form wire:submit="guardar" class="space-y-4" x-data="{busqueda: ''}">
        <div class="hub-panel grid gap-4 p-4 sm:grid-cols-2">
            <label class="grid gap-2 text-sm font-semibold">Sección<select wire:model.live="grupo" class="rounded-md border border-border bg-surface p-2">@foreach($grupos as $claveGrupo => $nombreGrupo)<option value="{{ $claveGrupo }}">{{ $nombreGrupo }}</option>@endforeach</select></label>
            <label class="grid gap-2 text-sm font-semibold">Buscar texto<input type="search" x-model="busqueda" class="rounded-md border border-border p-2" placeholder="Escribe parte del texto original"></label>
        </div>
        <div class="grid gap-4 lg:grid-cols-2" wire:key="editor-{{ $grupo }}">
            @foreach($campos as $clave => $defecto)
                <section class="hub-panel space-y-3 p-4" wire:key="texto-{{ $grupo }}-{{ $clave }}" x-show="@js(mb_strtolower($defecto)).includes(busqueda.toLowerCase().trim())">
                    <label for="texto-{{ $grupo }}-{{ $clave }}" class="block text-sm font-semibold text-blue-navy">{{ \Illuminate\Support\Str::limit($defecto, 100) }}</label>
                    <textarea id="texto-{{ $grupo }}-{{ $clave }}" wire:model="textos.{{ $grupo }}.{{ $clave }}" rows="{{ mb_strlen($defecto) > 150 ? 5 : 2 }}" maxlength="5000" class="w-full rounded-md border border-border bg-surface p-3 text-sm focus:ring-2 focus:ring-science-blue" aria-describedby="original-{{ $grupo }}-{{ $clave }}"></textarea>
                    <details id="original-{{ $grupo }}-{{ $clave }}" class="text-xs text-text-secondary"><summary class="cursor-pointer">Ver texto original</summary><p class="mt-2 whitespace-pre-line">{{ $defecto }}</p></details>
                    @error('textos.'.$grupo.'.'.$clave)<p role="alert" class="text-xs text-error">{{ $message }}</p>@enderror
                    <button type="button" wire:click="restaurar(@js($grupo), @js($clave))" class="min-h-11 text-sm text-science-blue underline">Restaurar original</button>
                </section>
            @endforeach
        </div>
        <div class="sticky bottom-3 flex justify-end"><button type="submit" wire:loading.attr="disabled" class="min-h-12 rounded-lg bg-blue-navy px-5 py-3 font-semibold text-white shadow-lg disabled:opacity-50">Guardar textos</button></div>
    </form>
</div>
