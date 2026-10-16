<div class="mx-auto w-full max-w-6xl space-y-5">
    <header><h1 class="hub-page-title">Fichas del explorador taxonómico</h1></header>
    @if (!$disponible)<p role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">La tabla de fichas está pendiente de actualizar. Puedes consultar los taxones; el guardado se habilitará al actualizar la base.</p>@endif
    @if (session('ficha-guardada'))<p role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('ficha-guardada') }}</p>@endif
    <div class="grid gap-5 xl:grid-cols-2">
        <x-interactive-grid :rows="$taxones" label="Taxones para fichas" :columns="['Nombre científico', 'Rango', 'Acciones']">
            <thead><tr><x-grid-heading field="nombre">Nombre científico</x-grid-heading><x-grid-heading field="rango">Rango</x-grid-heading><x-grid-heading>Acciones</x-grid-heading></tr></thead>
            <tbody>@forelse($taxones as $item)
                <tr wire:key="ficha-taxon-{{ $item->id }}"><td>{{ $item->nombre_cientifico }}</td><td>{{ $item->rango }}</td><td><button type="button" class="hub-ig-button" wire:click="seleccionar('{{ $item->id }}')" aria-pressed="{{ $taxonId === $item->id ? 'true' : 'false' }}">Editar ficha</button></td></tr>
            @empty<tr><td colspan="3">No hay taxones con ese nombre.</td></tr>@endforelse</tbody>
        </x-interactive-grid>
        <section class="hub-panel p-5" aria-label="Editar ficha">
            @if ($taxon)
                <form wire:submit="guardar" class="space-y-4" wire:key="ficha-form-{{ $taxonId }}">
                    <div><h2 class="hub-section-title">{{ $taxon->nombre_cientifico }}</h2><p class="text-sm text-text-secondary">{{ $taxon->rango }}</p></div>
                    @if ($ficha?->foto_ruta && !$quitarFoto)<figure><img src="{{ route('admin.ficha-foto', ['taxon' => $taxonId, 'v' => $ficha->foto_sha256]) }}" alt="{{ $ficha->foto_alt }}" class="h-44 w-full rounded-lg bg-slate-50 object-contain"><figcaption class="mt-1 text-xs text-text-secondary">{{ $ficha->credito }}</figcaption></figure>@endif
                    <div><label for="ficha-foto" class="block text-sm font-semibold">Fotografía</label><input id="ficha-foto" type="file" accept="image/jpeg,image/png,image/webp" wire:model="foto" class="mt-1 block w-full text-sm" aria-describedby="ficha-foto-ayuda"><p id="ficha-foto-ayuda" class="mt-1 text-xs text-text-secondary">JPG, PNG o WebP, hasta 8 MB. Se guarda como WebP de hasta 360 × 270 píxeles. Usa una imagen representativa con permiso de uso.</p><p wire:loading wire:target="foto" role="status" class="text-sm">Cargando fotografía…</p>@error('foto')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    @if ($ficha?->foto_ruta)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="quitarFoto">Retirar la fotografía actual al guardar</label>@endif
                    <div><label for="ficha-alt" class="block text-sm font-semibold">Descripción de la foto para accesibilidad</label><input id="ficha-alt" wire:model="fotoAlt" maxlength="180" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('fotoAlt')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-descripcion" class="block text-sm font-semibold">Explicación breve</label><textarea id="ficha-descripcion" wire:model="descripcion" rows="4" maxlength="800" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm" aria-describedby="ficha-texto-ayuda"></textarea><p id="ficha-texto-ayuda" class="text-xs text-text-secondary">Hasta 800 caracteres; se muestra como texto, sin HTML.</p>@error('descripcion')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-credito" class="block text-sm font-semibold">Autor y licencia de la fotografía</label><input id="ficha-credito" wire:model="credito" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('credito')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="ficha-fuente" class="block text-sm font-semibold">Enlace de la fuente (HTTPS)</label><input id="ficha-fuente" type="url" wire:model="fuenteUrl" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">@error('fuenteUrl')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="publicada">Publicar ficha en el explorador</label>
                    <p class="text-xs text-text-secondary">La publicación de la ficha respeta la visibilidad de los registros: un taxón privado no se hace público por tener una ficha.</p>
                    <button type="submit" @disabled(!$disponible) wire:loading.attr="disabled" wire:target="guardar,foto" class="rounded-lg bg-blue-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar ficha</button>
                </form>
            @else<p class="py-12 text-center text-sm text-text-secondary">Selecciona un taxón para administrar su fotografía y explicación.</p>@endif
        </section>
    </div>
</div>
