@props(['field' => null, 'resizable' => false])
<th {{ $attributes->merge(['scope' => 'col']) }} @if($field) data-grid-field="{{ $field }}" aria-sort="{{ $this->gridSort === $field ? ($this->gridDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
    @if($field)
        <button type="button" wire:click="ordenarGrilla('{{ $field }}')" aria-label="Ordenar por {{ $slot }}">{{ $slot }}<flux:icon :name="$this->gridSort === $field ? ($this->gridDirection === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrows-up-down'" /></button>
    @else {{ $slot }} @endif
    @if($resizable && $field)
        <x-grid-resize :field="$field" :label="$slot" />
    @endif
</th>
