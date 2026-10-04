@props(['total', 'pagina', 'ultima'])
@if($ultima > 1)
    <nav class="mt-3 border-t border-border pt-3 text-xs text-text-secondary" aria-label="Páginas de taxones hermanos">
        <p class="mb-2">{{ number_format($total, 0, ',', '.') }} {{ $total === 1 ? 'taxón' : 'taxones' }} · página {{ $pagina }} de {{ $ultima }} · hasta 6 por página</p>
        <div class="flex items-center justify-between gap-2">
            <button type="button" wire:click="cambiarPaginaHermanos({{ $pagina - 1 }})" wire:loading.attr="disabled" wire:target="cambiarPaginaHermanos,navegar" @disabled($pagina <= 1) class="rounded border border-border px-2 py-1.5 hover:bg-science-blue/5 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Página anterior de taxones hermanos">Anterior</button>
            <button type="button" wire:click="cambiarPaginaHermanos({{ $pagina + 1 }})" wire:loading.attr="disabled" wire:target="cambiarPaginaHermanos,navegar" @disabled($pagina >= $ultima) class="rounded border border-border px-2 py-1.5 hover:bg-science-blue/5 disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Página siguiente de taxones hermanos">Siguiente</button>
        </div>
        <span wire:loading wire:target="cambiarPaginaHermanos" role="status">Actualizando taxones hermanos…</span>
    </nav>
@endif
