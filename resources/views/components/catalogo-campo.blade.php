@props(['campo', 'opciones' => [], 'placeholder' => null])
@php($ayuda = \Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::campo($campo))
<flux:field data-catalogo-campo="{{ $campo }}">
    <flux:label>{{ $ayuda['titulo'] }} @if($ayuda['obligatorio'])<span aria-hidden="true" class="text-error">*</span>@endif</flux:label>
    <div class="flex items-start gap-2" data-catalogo-control-fila>
    <div class="min-w-0 flex-1" data-catalogo-control>
    @if($ayuda['tipo'] === 'select')
        <flux:select wire:model="{{ $campo }}" id="captura-{{ $campo }}" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'">
            <option value="">{{ $placeholder ?? ($campo === 'taxonId' ? 'Seleccione un taxón...' : 'Sin entidad depositante') }}</option>
            @foreach($opciones as $opcion)<option value="{{ $opcion['id'] }}">{{ $opcion['label'] }}</option>@endforeach
        </flux:select>
    @elseif($ayuda['tipo'] === 'textarea')
        <flux:textarea wire:model="{{ $campo }}" id="captura-{{ $campo }}" :maxlength="$ayuda['maxlength']" />
    @else
        <flux:input wire:model="{{ $campo }}" id="captura-{{ $campo }}" :type="$ayuda['tipo']" :maxlength="$ayuda['maxlength']" :inputmode="$ayuda['inputmode']" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'" />
    @endif
    </div>
    <x-catalogo-ayuda :campo="$campo" :detalle="$ayuda" />
    </div>
    <flux:error :name="$campo" />
</flux:field>
