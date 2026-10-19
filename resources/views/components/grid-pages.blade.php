@props(['page', 'last', 'total', 'perPage', 'action' => 'cambiarPagina', 'label' => 'Resultados'])
@php
    $pages = collect(array_merge([1, $last], range(max(1, $page-2), min($last, $page+2))))->unique()->sort()->values();
    $previous = 0;
@endphp
<nav class="hub-ig-footer" aria-label="Paginación de {{ $label }}">
    <div class="hub-ig-page-controls">
        <button type="button" class="hub-ig-page-button" wire:click="{{ $action }}(1)" @disabled($page <= 1) aria-label="Primera página">«</button>
        <button type="button" class="hub-ig-page-button" wire:click="{{ $action }}({{ max(1, $page-1) }})" @disabled($page <= 1) aria-label="Página anterior">‹</button>
        <span class="hub-ig-page-separator" aria-hidden="true"></span>
        @foreach($pages as $number)
            @if($previous && $number > $previous+1)<span class="hub-ig-page-ellipsis" aria-hidden="true">…</span>@endif
            <button type="button" @class(['hub-ig-page-button hub-ig-page-number', 'hub-ig-page-distant' => abs($number-$page)>1]) wire:click="{{ $action }}({{ $number }})" @if($number === $page) aria-current="page" @endif aria-label="Página {{ $number }} de {{ $last }}">{{ $number }}</button>
            @php($previous = $number)
        @endforeach
        <span class="hub-ig-page-separator" aria-hidden="true"></span>
        <button type="button" class="hub-ig-page-button" wire:click="{{ $action }}({{ min($last, $page+1) }})" @disabled($page >= $last) aria-label="Página siguiente">›</button>
        <button type="button" class="hub-ig-page-button" wire:click="{{ $action }}({{ $last }})" @disabled($page >= $last) aria-label="Última página">»</button>
    </div>
    <span class="hub-ig-range" role="status" aria-live="polite">{{ $total ? ($page-1)*$perPage+1 : 0 }}–{{ min($page*$perPage, $total) }} de {{ number_format($total, 0, ',', '.') }}</span>
</nav>
