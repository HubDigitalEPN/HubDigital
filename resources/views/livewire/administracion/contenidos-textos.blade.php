<div class="hub-workspace space-y-4 p-4 sm:p-6" data-contenidos-textos>
    <nav aria-label="Secciones de contenidos" class="flex flex-wrap gap-2 border-b border-border pb-3">
        @foreach($secciones as $id => $nombre)
            <a href="{{ route('admin.contenidos', ['seccion' => $id]) }}" wire:navigate
               @if($seccion === $id || ($seccion === 'laboratorio' && $id === 'portal')) aria-current="page" @endif
               @class(['inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-science-blue', 'border-blue-navy bg-blue-navy text-white!' => $seccion === $id || ($seccion === 'laboratorio' && $id === 'portal'), 'border-border bg-surface text-blue-navy' => $seccion !== $id && !($seccion === 'laboratorio' && $id === 'portal')])>{{ $nombre }}</a>
        @endforeach
        @if(in_array($seccion, ['portal', 'laboratorio', 'depositos', 'fichas'], true))
            <p class="hub-editor-ayuda font-bold" style="font-size: calc(.7rem + 1pt)">
                <span aria-hidden="true">*</span> {{ $seccion === 'fichas' ? 'Doble clic en una fila para editar la ficha' : 'Doble clic para modificar un texto o imagen' }}
            </p>
        @endif
    </nav>
    @if($seccion === 'portal')
        <livewire:administracion.configuracion-textos-portal />
    @elseif($seccion === 'laboratorio')
        <livewire:administracion.configuracion-laboratorio />
    @elseif($seccion === 'fichas')
        <livewire:administracion.fichas-taxonomicas-portal />
    @elseif($seccion === 'logos')
        @php
            $logos = [
                \App\Support\FotosEditoriales::CABECERA_SISTEMA => 'Barra superior',
                \App\Support\FotosEditoriales::ACCESO_SISTEMA => 'Pantallas de acceso',
            ];
        @endphp
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($logos as $id => $ubicacion)
                @php($alt = \App\Support\FotosEditoriales::alt($id, \App\Support\FotosEditoriales::ALT_SISTEMA))
                <section class="space-y-3 rounded-lg border border-border bg-surface p-4" aria-label="{{ $ubicacion }}">
                    <h2 class="font-semibold">{{ $ubicacion }}</h2>
                    <div @class(['flex min-h-28 items-center justify-center rounded p-4', 'bg-blue-navy' => $loop->first, 'bg-bg-main' => !$loop->first])>
                        <img src="{{ \App\Support\FotosEditoriales::url($id) }}" alt="{{ $alt }}"
                             class="max-h-16 max-w-full rounded bg-white object-contain {{ $loop->first ? 'p-1' : 'bg-transparent!' }}" />
                    </div>
                    <flux:button type="button" wire:click="seleccionarFotoEditorial('{{ $id }}')" icon="pencil">
                        Cambiar logo
                    </flux:button>
                </section>
            @endforeach
        </div>
        @include('livewire.administracion.partials.foto-editorial')
    @elseif($seccion === 'depositos')
        <livewire:administracion.configuracion-textos-wizard />
    @else
        <livewire:administracion.configuracion-textos-sistema />
    @endif
</div>
