<div class="hub-workspace space-y-4 p-4 sm:p-6" data-publicacion-catalogo>
    <h1 class="hub-page-title">Publicación del catálogo</h1>
    <nav aria-label="Estado de publicación" class="flex flex-wrap gap-2 border-b border-border pb-3">
        @foreach(['divulgados' => 'Divulgados', 'pendientes' => 'Pendientes de divulgar'] as $id => $nombre)
            <a href="{{ route('divulgacion.index', array_merge(request()->except(['vista', 'page']), ['vista' => $id])) }}" wire:navigate
               @if($vista === $id) aria-current="page" @endif
               @class(['inline-flex min-h-11 items-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-science-blue', 'border-blue-navy bg-blue-navy text-white!' => $vista === $id, 'border-border bg-surface text-blue-navy' => $vista !== $id])>{{ $nombre }}</a>
        @endforeach
    </nav>
    @if($vista === 'pendientes')
        @livewire(\Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes::class)
    @else
        @livewire(\Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados::class)
    @endif
</div>
