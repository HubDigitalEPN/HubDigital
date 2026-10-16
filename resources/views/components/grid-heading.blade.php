@props(['field' => null])
<th scope="col" @if($field) data-grid-field="{{ $field }}" aria-sort="{{ $this->gridSort === $field ? ($this->gridDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
    @if($field)
        <button type="button" wire:click="ordenarGrilla('{{ $field }}')" aria-label="Ordenar por {{ $slot }}">{{ $slot }}<flux:icon :name="$this->gridSort === $field ? ($this->gridDirection === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrows-up-down'" /></button>
    @else {{ $slot }} @endif
</th>
