<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-7xl">

        {{-- Encabezado --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-serif text-2xl font-bold text-blue-navy">Imágenes por nivel taxonómico</h1>
                <p class="mt-1 text-sm text-text-secondary">
                    Sube imágenes a un espécimen y elige la portada de género y especie.
                </p>
            </div>
        </div>

        {{-- Avisos --}}
        @if($mensaje)
            <div class="mb-4 rounded-lg border border-success/40 bg-success/10 px-4 py-3 text-sm text-success">
                {{ $mensaje }}
            </div>
        @endif
        @if($error)
            <div class="mb-4 rounded-lg border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                {{ $error }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[20rem_1fr]">

            {{-- Lista de especies divulgadas --}}
            <aside class="rounded-lg border border-border bg-surface shadow-sm">
                <div class="border-b border-border p-3">
                    <flux:input wire:model.live.debounce.300ms="buscar" placeholder="Buscar especie…" icon="magnifying-glass" />
                </div>
                <div class="max-h-[28rem] overflow-y-auto p-1.5">
                    @forelse($especies as $especie)
                        <button
                            wire:click="seleccionarEspecie('{{ $especie->scientific_name }}', '{{ $especie->genus }}')"
                            @class([
                                'w-full text-left rounded px-3 py-2 transition-colors',
                                'bg-science-blue/10 text-science-blue' => $especieSeleccionada === $especie->scientific_name,
                                'hover:bg-bg-main text-text-primary' => $especieSeleccionada !== $especie->scientific_name,
                            ])
                        >
                            <div class="font-serif italic text-sm truncate">{{ $especie->scientific_name }}</div>
                            <div class="text-xs text-text-secondary">{{ $especie->genus }}</div>
                        </button>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-text-secondary">Sin especies divulgadas.</p>
                    @endforelse
                </div>
            </aside>

            {{-- Detalle --}}
            <div class="min-w-0">
                @if($especieSeleccionada === '')
                    <div class="flex items-center justify-center rounded-lg border border-dashed border-border bg-surface py-20 text-sm text-text-secondary">
                        Selecciona una especie para gestionar sus imágenes.
                    </div>
                @else
                    {{-- Formulario de subida --}}
                    <section class="mb-6 rounded-lg border border-border bg-surface shadow-sm p-4 sm:p-5">
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-text-primary">
                            <flux:icon name="arrow-up-tray" class="size-4 text-text-secondary" />
                            Subir imagen a <span class="font-serif italic">{{ $especieSeleccionada }}</span>
                        </h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:select wire:model="occurrenceSeleccionado" label="Espécimen">
                                @foreach($ocurrencias as $occ)
                                    <option value="{{ $occ }}">{{ $occ }}</option>
                                @endforeach
                            </flux:select>
                            <div>
                                <flux:input type="file" wire:model="imagen" label="Imagen" accept="image/*" />

                                {{-- Indicador de carga del archivo --}}
                                <div wire:loading wire:target="imagen" class="mt-2 flex items-center gap-2 text-sm text-science-blue">
                                    <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                    Cargando imagen…
                                </div>

                                {{-- Confirmación de archivo cargado --}}
                                @if($imagen)
                                    <div wire:loading.remove wire:target="imagen" class="mt-2 flex items-center gap-2 text-sm text-success">
                                        <flux:icon name="check-circle" class="size-4" />
                                        Imagen lista para subir.
                                    </div>
                                @endif
                            </div>
                            <flux:select wire:model.live="vista" label="Tipo de vista (estándar AntWeb)">
                                @foreach(\Modules\CatalogoPublico\Presentation\Http\Controllers\GestionImagenesTaxonomicas::VISTAS_ESTANDAR as $codigo => $etiqueta)
                                    <option value="{{ $codigo }}">
                                        {{ $etiqueta }}@if($codigo !== 'otra') (_{{ $codigo }})@endif
                                    </option>
                                @endforeach
                            </flux:select>
                            @if($vista === 'otra')
                                <flux:input
                                    wire:model="sufijoPersonalizado"
                                    label="Sufijo personalizado"
                                    placeholder="Ej. V, LD, ant"
                                    description="Si no entra en las categorías anteriores, escribe la inicial o iniciales de la vista."
                                    maxlength="4"
                                />
                            @else
                                <div class="hidden sm:block"></div>
                            @endif
                            <flux:input wire:model="nombreAutor" label="Nombre del autor" placeholder="María" />
                            <flux:input wire:model="apellidoAutor" label="Apellido del autor" placeholder="Gómez" />
                        </div>

                        <div class="mt-4 flex justify-end">
                            <flux:button
                                wire:click="subir"
                                wire:loading.attr="disabled"
                                wire:target="subir,imagen"
                                variant="primary"
                                icon="arrow-up-tray"
                                class="w-full sm:w-auto"
                            >
                                <span wire:loading.remove wire:target="subir">Subir imagen</span>
                                <span wire:loading wire:target="subir" class="flex items-center gap-2">
                                    <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                    Subiendo…
                                </span>
                            </flux:button>
                        </div>
                    </section>

                    {{-- Galerías de género y especie --}}
                    <x-catalogopublico::galeria-portada
                        titulo="Especie"
                        :etiqueta="$especieSeleccionada"
                        nivel="species"
                        :valor="$especieSeleccionada"
                        :imagenes="$galeriaEspecie"
                    />

                    <x-catalogopublico::galeria-portada
                        titulo="Género"
                        :etiqueta="$generoSeleccionado"
                        nivel="genus"
                        :valor="$generoSeleccionado"
                        :imagenes="$galeriaGenero"
                    />
                @endif
            </div>
        </div>
    </div>
</div>
