@props(['campo', 'opciones' => [], 'placeholder' => null])
@php($ayuda = \Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::campo($campo))
<flux:field class="relative" data-catalogo-campo="{{ $campo }}">
    <flux:label class="pe-9">{{ $ayuda['titulo'] }} @if($ayuda['obligatorio'])<span aria-hidden="true" class="text-error">*</span>@endif</flux:label>
    @if($ayuda['tipo'] === 'select')
        <flux:select wire:model="{{ $campo }}" id="captura-{{ $campo }}" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'" class="pe-16!">
            <option value="">{{ $placeholder ?? ($campo === 'taxonId' ? 'Seleccione un taxón...' : 'Sin entidad depositante') }}</option>
            @foreach($opciones as $opcion)<option value="{{ $opcion['id'] }}">{{ $opcion['label'] }}</option>@endforeach
        </flux:select>
    @elseif($ayuda['tipo'] === 'textarea')
        <flux:textarea wire:model="{{ $campo }}" id="captura-{{ $campo }}" :maxlength="$ayuda['maxlength']" class="pe-12!" />
    @else
        <flux:input wire:model="{{ $campo }}" id="captura-{{ $campo }}" :type="$ayuda['tipo']" :maxlength="$ayuda['maxlength']" :inputmode="$ayuda['inputmode']" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'" class="pe-12!" />
    @endif
    <x-catalogo-ayuda :campo="$campo" :detalle="$ayuda" :selector="$ayuda['tipo'] === 'select'" />
    <flux:error :name="$campo" />
</flux:field>
