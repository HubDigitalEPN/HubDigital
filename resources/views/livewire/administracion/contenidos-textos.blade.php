<div class="hub-workspace space-y-4 p-4 sm:p-6" data-contenidos-textos>
    <h1 class="hub-page-title">Contenidos y textos</h1>
    <nav aria-label="Secciones de contenidos" class="flex flex-wrap gap-2 border-b border-border pb-3">
        @foreach($secciones as $id => $nombre)
            <a href="{{ route('admin.contenidos', ['seccion' => $id]) }}" wire:navigate
               @if($seccion === $id) aria-current="page" @endif
               @class(['inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-science-blue', 'border-blue-navy bg-blue-navy text-white!' => $seccion === $id, 'border-border bg-surface text-blue-navy' => $seccion !== $id])>{{ $nombre }}</a>
        @endforeach
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
