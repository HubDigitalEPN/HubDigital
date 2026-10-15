@php
    $gruposDetalle = [
        'Identificación del material' => [
            ['recordNumber', 'Código de campo', 'text'],
            ['origin', 'Origen del material', 'lov', ['research' => 'Investigación', 'consulting' => 'Consultoría']],
            ['identifiedBy', 'Identificado por', 'text'],
            ['dateIdentified', 'Fecha de identificación', 'date'],
        ],
        'Lugar de recolección' => [
            ['country', 'País', 'lov', collect($catalogoPaises)->mapWithKeys(fn ($pais) => [$pais['nombre'] => $pais['nombre']])->all()],
            ['stateProvince', 'Provincia o estado', 'text'],
            ['municipality', 'Cantón o municipio', 'text'],
            ['verbatimLocality', 'Localidad (opcional)', 'text', [], true],
            ['decimalLatitude', 'Latitud decimal', 'number'],
            ['decimalLongitude', 'Longitud decimal', 'number'],
        ],
        'Datos de colecta' => [
            ['eventDate', 'Fecha de colecta', 'date'],
            ['recordedBy', 'Colector', 'text'],
            ['individualCount', 'Número de individuos', 'number'],
            ['samplingProtocol', 'Método de colecta', 'lov', ['hand_collection' => 'Colecta manual', 'aquatic_net' => 'Red acuática', 'malaise_trap' => 'Trampa Malaise', 'light_trap' => 'Trampa de luz', 'pitfall_trap' => 'Trampa de caída', 'leaf_litter' => 'Hojarasca', 'beating_sheet' => 'Paraguas entomológico', 'fogging' => 'Nebulización', 'other' => 'Otro documentado']],
        ],
        'Conservación y observaciones' => [
            ['preparations', 'Preparación del material', 'lov', ['ethanol' => 'Preservado en etanol', 'dry_pin' => 'Montado en alfiler', 'slide' => 'Portaobjetos', 'other' => 'Otra preparación']],
            ['occurrenceRemarks', 'Observaciones del espécimen o lote', 'textarea', [], true],
        ],
        'Documentación asociada' => [
            ['researchPermit', 'Permiso de investigación', 'readonly'],
            ['transportPermit', 'Permiso de transporte', 'readonly'],
        ],
    ];
@endphp

<div class="hub-detail-sections">
@foreach([
    ['Identificación del material', 'Datos de colecta', 'Documentación asociada'],
    ['Lugar de recolección', 'Conservación y observaciones'],
] as $titulosColumna)
<div class="hub-detail-column">
@foreach($titulosColumna as $tituloGrupo)
    @php $camposGrupo = $gruposDetalle[$tituloGrupo]; @endphp
    <fieldset class="hub-detail-section">
        <legend>{{ $tituloGrupo }}</legend>
        <div class="hub-detail-grid">
        @foreach($camposGrupo as $campoDetalle)
            @php
                [$claveDetalle, $tituloDetalle, $tipoDetalle] = $campoDetalle;
                $anchoDetalle = $campoDetalle[4] ?? false;
                $rutaDetalle = 'registroNativo.'.$claveDetalle;
            @endphp
            <div @class(['hub-detail-wide' => $anchoDetalle])>
            @if($tipoDetalle === 'lov')
                <x-gestionprestamosrecepciones::lov-popup :campo="$rutaDetalle" :label="$tituloDetalle"
                    :valor="$registroNativo[$claveDetalle] ?? ''" :opciones="$campoDetalle[3]" />
            @else
                <flux:field>
                    <flux:label>{{ $tituloDetalle }}</flux:label>
                    @if($tipoDetalle === 'textarea')
                        <flux:textarea wire:model="{{ $rutaDetalle }}" rows="3" />
                    @else
                        <flux:input wire:model="{{ $rutaDetalle }}" :type="$tipoDetalle === 'readonly' ? 'text' : $tipoDetalle"
                            :readonly="$tipoDetalle === 'readonly'"
                            :required="in_array($claveDetalle, ['recordNumber', 'identifiedBy', 'dateIdentified', 'decimalLatitude', 'decimalLongitude', 'eventDate', 'recordedBy', 'individualCount'], true)"
                            :step="$tipoDetalle === 'number' ? ($claveDetalle === 'individualCount' ? '1' : 'any') : null"
                            :min="$claveDetalle === 'individualCount' ? 1 : null" />
                    @endif
                    <flux:error :name="$rutaDetalle" />
                </flux:field>
            @endif
            </div>
        @endforeach
        </div>
    </fieldset>
@endforeach
</div>
@endforeach
</div>
