@props(['campo', 'opciones' => [], 'placeholder' => null, 'lista' => false, 'admiteTexto' => false, 'valor' => ''])
@php
    $ayuda = \Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::campo($campo);
@endphp
<flux:field data-catalogo-campo="{{ $campo }}">
    @unless($lista)<flux:label>{{ $ayuda['titulo'] }}</flux:label>@endunless
    <div class="flex items-start gap-2" data-catalogo-control-fila>
    <div class="min-w-0 flex-1" data-catalogo-control>
    @if($lista)
        <x-gestionprestamosrecepciones::lov-popup
            :campo="$campo" :label="$ayuda['titulo']" :valor="$valor" :opciones="$opciones"
            :placeholder="$placeholder ?? 'Selecciona una opción'" :required="$ayuda['obligatorio']"
            busqueda-remota="buscarOpcionesCaptura" busqueda-por-campo :admite-texto="$admiteTexto"
            :max-busqueda="$ayuda['maxlength'] ?? 120" :control-id="'captura-'.$campo" />
    @elseif($ayuda['tipo'] === 'select')
        <flux:select wire:model="{{ $campo }}" id="captura-{{ $campo }}" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'">
            <option value="">{{ $placeholder ?? ($campo === 'taxonId' ? 'Seleccione un taxón...' : 'Sin entidad depositante') }}</option>
            @foreach($opciones as $opcion)<option value="{{ $opcion['id'] }}">{{ $opcion['label'] }}</option>@endforeach
        </flux:select>
    @elseif($ayuda['tipo'] === 'textarea')
        <flux:textarea wire:model="{{ $campo }}" id="captura-{{ $campo }}" :maxlength="$ayuda['maxlength']" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'" />
    @else
        <flux:input wire:model="{{ $campo }}" id="captura-{{ $campo }}" :type="$ayuda['tipo']" :maxlength="$ayuda['maxlength']" :inputmode="$ayuda['inputmode']" :aria-required="$ayuda['obligatorio'] ? 'true' : 'false'" />
    @endif
    </div>
    <x-catalogo-ayuda :campo="$campo" :detalle="$ayuda" />
    </div>
    @unless($lista)<flux:error :name="$campo" />@endunless
</flux:field>
