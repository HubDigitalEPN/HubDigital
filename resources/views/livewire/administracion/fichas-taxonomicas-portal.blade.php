<div class="w-full space-y-3" data-fichas-editor>
    <header><h2 class="hub-section-title">Fichas del explorador taxonómico</h2><p class="text-sm text-text-secondary">1. Busca el taxón en la tabla. 2. Pulsa «Editar ficha». 3. Agrega su fotografía y explicación y guarda. Marca «Publicar ficha» para mostrarla al pulsar ese taxón en el explorador de la colección. Desplázate sobre la tabla para cargar más taxones.</p></header>
    @if (!$disponible)<p role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">La tabla de fichas está pendiente de actualizar. Puedes consultar los taxones; el guardado se habilitará al actualizar la base.</p>@endif
    @if (session('ficha-guardada'))<p data-hub-feedback="success" role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('ficha-guardada') }}</p>@endif
    <div class="hub-fichas-columnas">
        <x-interactive-grid :rows="$taxones" label="Taxones para fichas" :columns="['Nombre científico', 'Rango', 'Fotografía', 'Publicación', 'Acciones']">
            <thead><tr><x-grid-heading field="nombre">Nombre científico</x-grid-heading><x-grid-heading field="rango">Rango</x-grid-heading><x-grid-heading>Fotografía</x-grid-heading><x-grid-heading>Publicación</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
            <tbody>@forelse($taxones as $item)
                <tr wire:key="ficha-taxon-{{ $item->id }}"><td>{{ $item->nombre_cientifico }}</td><td>{{ $item->rango }}</td><td>{{ empty($item->foto_ruta) ? 'Sin foto' : (str_ends_with($item->foto_ruta, '.avif') ? 'AVIF' : 'WebP') }}</td><td>{{ ($item->publicada ?? false) ? 'Publicada' : 'Borrador' }}</td><td><button type="button" class="hub-ig-button" wire:click="seleccionar('{{ $item->id }}')" aria-pressed="{{ $taxonId === $item->id ? 'true' : 'false' }}">Editar ficha</button></td></tr>
            @empty<tr><td colspan="5">No hay taxones con ese nombre.</td></tr>@endforelse</tbody>
        </x-interactive-grid>
        <section class="hub-panel p-5" aria-label="Editar ficha">
            @if ($taxon)
                <div data-ficha-visual x-data="{disposicion:editorDisposicionContenido($wire, @js('ficha:'.$taxonId), @js(\App\Support\EditorPortal::version(\App\Support\DisposicionContenido::clave('ficha:'.$taxonId))), @js((object) \App\Support\DisposicionContenido::leer('ficha:'.$taxonId))), init(){this.$nextTick(()=>this.disposicion.conectar(this.$refs.ficha))}}" wire:key="ficha-visual-{{ $taxonId }}-{{ $version }}-{{ \App\Support\EditorPortal::version(\App\Support\DisposicionContenido::clave('ficha:'.$taxonId)) }}">
                    <h2 class="hub-section-title">{{ $taxon->nombre_cientifico }}</h2><p class="text-sm text-text-secondary">{{ $taxon->rango }}</p>
                    <div x-ref="ficha" data-ficha-vista data-disposicion-grupo="g-4444444444444444" class="space-y-3 p-3" x-on:dblclick="if(!$event.target.closest('[data-disposicion-handle]')) $wire.set('editorFichaAbierto',true)" x-on:keydown.enter="if(!$event.target.closest('button')) $wire.set('editorFichaAbierto',true)">
                        <figure data-disposicion-id="i-1111111111111111" tabindex="0">
                            @if($ficha?->foto_ruta && !$quitarFoto)<img src="{{ route('admin.ficha-foto', ['taxon' => $taxonId, 'v' => $ficha->foto_sha256]) }}" alt="{{ $fotoAlt }}" class="h-44 w-full object-contain">
                            @else<div class="rounded border border-dashed border-border p-8 text-center text-sm">Doble clic para agregar fotograf&iacute;a</div>@endif
                        </figure>
                        <div data-disposicion-id="i-2222222222222222" tabindex="0" class="text-xs text-text-secondary">{{ $credito ?: 'Autor y licencia' }} @if($fuenteUrl)<span>{{ $fuenteUrl }}</span>@endif</div>
                        <p data-disposicion-id="i-3333333333333333" tabindex="0" class="whitespace-pre-line text-sm">{{ $descripcion ?: 'Doble clic para editar la leyenda' }}</p>
                    </div>
                    @include('livewire.administracion.partials.herramientas-disposicion')
                    <flux:modal wire:model="editorFichaAbierto" aria-label="Editar ficha" class="w-full max-w-xl">
                <form x-on:submit.prevent="if(!disposicion.pendiente || await disposicion.guardar()) await $wire.guardar()" class="space-y-4" wire:key="ficha-form-{{ $taxonId }}">
                    <div><h2 class="hub-section-title">{{ $taxon->nombre_cientifico }}</h2><p class="text-sm text-text-secondary">{{ $taxon->rango }}</p></div>
                    @if ($ficha?->foto_ruta && !$quitarFoto)<figure><img src="{{ route('admin.ficha-foto', ['taxon' => $taxonId, 'v' => $ficha->foto_sha256]) }}" alt="{{ $ficha->foto_alt }}" class="h-44 w-full rounded-lg bg-slate-50 object-contain"><figcaption class="mt-1 text-xs text-text-secondary">{{ $ficha->credito }}</figcaption></figure>@endif
                    <div><label for="ficha-foto" class="block text-sm font-semibold">Fotografía</label><input id="ficha-foto" type="file" accept="image/jpeg,image/png,image/webp,image/avif" wire:model="foto" class="mt-1 block w-full text-sm"><p wire:loading wire:target="foto" role="status" class="text-sm">Cargando fotografía…</p>@error('foto')<p data-hub-feedback="error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    @if ($ficha?->foto_ruta)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="quitarFoto">Retirar la fotografía actual al guardar</label>@endif
                    <div><label for="ficha-alt" class="block text-sm font-semibold">Descripción de la foto para accesibilidad</label><input id="ficha-alt" wire:model="fotoAlt" maxlength="180" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('fotoAlt')<p data-hub-feedback="error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-descripcion" class="block text-sm font-semibold">Leyenda del taxón</label><textarea id="ficha-descripcion" wire:model="descripcion" rows="4" maxlength="800" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></textarea>@error('descripcion')<p data-hub-feedback="error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-credito" class="block text-sm font-semibold">Autor y licencia de la fotografía</label><input id="ficha-credito" wire:model="credito" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('credito')<p data-hub-feedback="error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-fuente" class="block text-sm font-semibold">Enlace de la fuente (HTTPS)</label><input id="ficha-fuente" type="url" wire:model="fuenteUrl" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('fuenteUrl')<p data-hub-feedback="error" role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="publicada">Publicar ficha en el explorador</label>

                    <flux:button type="button" wire:click="$set('editorFichaAbierto', false)">Cancelar</flux:button>
                    <button type="submit" @disabled(!$disponible) wire:loading.attr="disabled" wire:target="guardar,foto" class="rounded-lg bg-blue-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar y publicar</button>
                </form>
                    </flux:modal>
                </div>
            @else<p class="py-5 text-center text-sm text-text-secondary">Pulsa «Editar ficha» en la fila del taxón. Aquí aparecerán su fotografía, leyenda, autor, licencia y estado de publicación.</p>@endif
        </section>
    </div>
</div>
