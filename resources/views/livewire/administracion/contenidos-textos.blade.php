<div class="hub-workspace space-y-4 p-4 sm:p-6" data-contenidos-textos>
    <nav aria-label="Secciones de contenidos" class="flex flex-wrap gap-2 border-b border-border pb-3">
        @foreach($secciones as $id => $nombre)
            <a href="{{ route('admin.contenidos', ['seccion' => $id]) }}" wire:navigate
               @if($seccion === $id || ($seccion === 'laboratorio' && $id === 'portal')) aria-current="page" @endif
               @class(['inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-science-blue', 'border-blue-navy bg-blue-navy text-white!' => $seccion === $id || ($seccion === 'laboratorio' && $id === 'portal'), 'border-border bg-surface text-blue-navy' => $seccion !== $id && !($seccion === 'laboratorio' && $id === 'portal')])>{{ $nombre }}</a>
        @endforeach
        @if(in_array($seccion, ['portal', 'laboratorio', 'depositos'], true))
            <p class="hub-editor-ayuda"><span aria-hidden="true">*</span> Doble clic para modificar un texto o imagen</p>
        @endif
    </nav>
    @if($seccion === 'portal')
        <livewire:administracion.configuracion-textos-portal />
    @elseif($seccion === 'laboratorio')
        <livewire:administracion.configuracion-laboratorio />
    @elseif($seccion === 'fichas')
        <livewire:administracion.fichas-taxonomicas-portal />
    @elseif($seccion === 'depositos')
        <livewire:administracion.configuracion-textos-wizard />
    @else
        <livewire:administracion.configuracion-textos-sistema />
    @endif
</div>
