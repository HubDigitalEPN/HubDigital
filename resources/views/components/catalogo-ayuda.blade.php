@props(['campo', 'detalle' => null])
@php
    $ayuda = $detalle ?? \Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::campo($campo);
    $idAyuda = 'ayuda-catalogo-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $campo);
@endphp
<span x-data class="inline-flex shrink-0 self-start pt-1.5" data-catalogo-ayuda="{{ $campo }}">
    <button type="button" x-on:click="$refs.ayuda.showModal()" aria-haspopup="dialog" aria-controls="{{ $idAyuda }}"
        aria-label="Ayuda: {{ $ayuda['titulo'] }}" title="Ayuda: {{ $ayuda['titulo'] }}"
        class="inline-flex size-7 items-center justify-center rounded-full border border-border bg-surface text-science-blue hover:bg-bg-main focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-science-blue">
        <flux:icon name="question-mark-circle" class="size-5" />
    </button>
    <dialog x-ref="ayuda" id="{{ $idAyuda }}" aria-labelledby="{{ $idAyuda }}-titulo" wire:ignore.self
        x-on:click="if ($event.target === $refs.ayuda) $refs.ayuda.close()"
        class="fixed inset-0 m-auto max-h-[80vh] w-[min(24rem,calc(100vw-2rem))] overflow-auto rounded-lg border border-border bg-surface p-5 text-left text-sm text-text-primary shadow-xl backdrop:bg-blue-navy/30">
        <h3 id="{{ $idAyuda }}-titulo" class="text-lg font-semibold">{{ $ayuda['titulo'] }}</h3>
        <p class="mt-3 leading-relaxed">{{ $ayuda['descripcion'] }}</p>
        <p class="mt-3"><strong>Ejemplo:</strong> {{ $ayuda['ejemplo'] }}</p>
        <p class="mt-3 text-text-secondary">{{ $ayuda['regla'] }}</p>
        <div class="mt-4 flex justify-end">
            <button type="button" autofocus x-on:click="$refs.ayuda.close()" class="hub-ig-button" aria-label="Cerrar ayuda: {{ $ayuda['titulo'] }}">Cerrar ayuda</button>
        </div>
    </dialog>
</span>
