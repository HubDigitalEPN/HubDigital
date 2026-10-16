@props(['paginator', 'label' => 'Resultados'])
    @php
        $currentPage = min($paginator->lastPage(), max(1, $paginator->currentPage()));
        $lastPage = $paginator->lastPage();
        $pageNumbers = collect(array_merge([1, $lastPage], range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2))))->unique()->sort()->values();
        $previousNumber = 0;
    @endphp
    <nav class="hub-ig-footer" aria-label="Paginación de {{ $label }}">
        <div class="hub-ig-page-controls">
            <button type="button" class="hub-ig-page-button" wire:click="gotoPage(1, '{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) aria-label="Primera página" title="Primera página"><flux:icon name="chevron-double-left" /></button>
            <button type="button" class="hub-ig-page-button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) aria-label="Página anterior" title="Página anterior"><flux:icon name="chevron-left" /></button>
            <span class="hub-ig-page-separator" aria-hidden="true"></span>
            @foreach($pageNumbers as $page)
                @if($previousNumber && $page > $previousNumber + 1)<span class="hub-ig-page-ellipsis" aria-hidden="true">…</span>@endif
                <button type="button" @class(['hub-ig-page-button hub-ig-page-number', 'hub-ig-page-distant' => abs($page - $currentPage) > 1]) wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" @if($page === $currentPage) aria-current="page" @endif aria-label="Página {{ $page }} de {{ $lastPage }}">{{ $page }}</button>
                @php $previousNumber = $page; @endphp
            @endforeach
            <span class="hub-ig-page-separator" aria-hidden="true"></span>
            <button type="button" class="hub-ig-page-button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(!$paginator->hasMorePages()) aria-label="Página siguiente" title="Página siguiente"><flux:icon name="chevron-right" /></button>
            <button type="button" class="hub-ig-page-button" wire:click="gotoPage({{ $lastPage }}, '{{ $paginator->getPageName() }}')" @disabled(!$paginator->hasMorePages()) aria-label="Última página" title="Última página"><flux:icon name="chevron-double-right" /></button>
        </div>
        <span class="hub-ig-range" role="status" aria-live="polite">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} de {{ number_format($paginator->total(), 0, ',', '.') }}</span>
    </nav>
