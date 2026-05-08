<div
    class="space-y-6"
    x-data="{
        origen: @js($origenRecoleccion),
        situacion: @js($situacionRegulatoria),
        provincia: @js($provincia),
        seleccionarOrigen(val) {
            this.origen = val;
            if (val === 'Exterior (Extranjero)') {
                this.situacion = 'Proviene de colección foránea';
            } else if (this.situacion === 'Proviene de colección foránea') {
                this.situacion = '';
            }
            $dispatch('radio-card-select', { grupo: 'origenRecoleccion', valor: val });
            $wire.set('origenRecoleccion', val);
        },
        seleccionarSituacion(val) {
            this.situacion = val;
            $dispatch('radio-card-select', { grupo: 'situacionRegulatoria', valor: val });
            $wire.set('situacionRegulatoria', val);
        },
    }"
>

    <div>
        <flux:heading size="lg" level="2">Origen de los Especímenes</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Declara la procedencia de los especímenes y tu situación regulatoria actual.
        </flux:text>
    </div>

    <div class="space-y-3">
        <flux:label>Procedencia geográfica <span class="text-error">*</span></flux:label>
        <flux:error name="origenRecoleccion" />

        <div class="grid gap-3 sm:grid-cols-2">
            <x-gestionprestamosrecepciones::radio-card
                :activo="$origenRecoleccion === 'Nacional (Ecuador)'"
                titulo="Nacional (Ecuador)"
                grupo="origenRecoleccion"
                descripcion="Especímenes recolectados dentro del territorio ecuatoriano."
                x-on:click="seleccionarOrigen('Nacional (Ecuador)')"
            >
                <x-slot:icono>
                    <flux:icon name="flag" class="size-5 text-science-blue" />
                </x-slot:icono>
            </x-gestionprestamosrecepciones::radio-card>

            <x-gestionprestamosrecepciones::radio-card
                :activo="$origenRecoleccion === 'Exterior (Extranjero)'"
                titulo="Exterior (Extranjero)"
                grupo="origenRecoleccion"
                descripcion="Provenientes de una colección o expedición fuera del país."
                x-on:click="seleccionarOrigen('Exterior (Extranjero)')"
            >
                <x-slot:icono>
                    <flux:icon name="globe-alt" class="size-5 text-science-blue" />
                </x-slot:icono>
            </x-gestionprestamosrecepciones::radio-card>
        </div>
    </div>

    <div x-show="origen === 'Nacional (Ecuador)'" x-cloak class="space-y-6">

        <div class="space-y-3">
            <flux:label>Situación regulatoria <span class="text-error">*</span></flux:label>
            <flux:error name="situacionRegulatoria" />

            <div class="grid gap-3 sm:grid-cols-2">
                <x-gestionprestamosrecepciones::radio-card
                    :activo="$situacionRegulatoria === 'Posee permisos del MAATE'"
                    titulo="Posee permisos del MAATE"
                    grupo="situacionRegulatoria"
                    descripcion="Cuenta con autorización vigente de recolección y permiso de movilización emitidos por el MAATE."
                    x-on:click="seleccionarSituacion('Posee permisos del MAATE')"
                >
                    <x-slot:icono>
                        <flux:icon name="document-check" class="size-5 text-bio-green" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>

                <x-gestionprestamosrecepciones::radio-card
                    :activo="$situacionRegulatoria === 'Sin permisos del MAATE'"
                    titulo="Sin permisos del MAATE"
                    grupo="situacionRegulatoria"
                    descripcion="Solo dispone de carta de justificación institucional o personal que explica la ausencia de permisos."
                    x-on:click="seleccionarSituacion('Sin permisos del MAATE')"
                >
                    <x-slot:icono>
                        <flux:icon name="exclamation-triangle" class="size-5 text-warning" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Provincia de origen</flux:label>
                <flux:select
                    wire:model="provincia"
                    x-on:change="provincia = $event.target.value"
                    placeholder="Selecciona una provincia…"
                >
                    @foreach([
                        'Azuay', 'Bolívar', 'Cañar', 'Carchi', 'Chimborazo', 'Cotopaxi',
                        'El Oro', 'Esmeraldas', 'Galápagos', 'Guayas', 'Imbabura', 'Loja',
                        'Los Ríos', 'Manabí', 'Morona Santiago', 'Napo', 'Orellana',
                        'Pastaza', 'Pichincha', 'Santa Elena', 'Santo Domingo de los Tsáchilas',
                        'Sucumbíos', 'Tungurahua', 'Zamora Chinchipe',
                    ] as $prov)
                        <option value="{{ $prov }}">{{ $prov }}</option>
                    @endforeach
                </flux:select>
                <flux:description>Se verificará si el Permiso de Movilización es exigible.</flux:description>
            </flux:field>

            <flux:field>
                <flux:label>Localidad / sector</flux:label>
                <flux:input wire:model="localidad" placeholder="Ej. Reserva Cuyabeno, sector río Aguarico…" />
                <flux:description>Se completará automáticamente desde el Permiso de Movilización si aplica.</flux:description>
            </flux:field>
        </div>

        <div x-show="provincia === 'Guayas'" x-cloak>
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:text class="text-sm">
                    En la provincia de <strong>Guayas</strong> el Permiso de Movilización es <strong>obligatorio</strong>. Asegúrate de tenerlo disponible para el siguiente paso.
                </flux:text>
            </flux:callout>
        </div>
        <div x-show="provincia && provincia !== 'Guayas'" x-cloak>
            <flux:callout variant="info" icon="information-circle">
                <flux:text class="text-sm">
                    Para la provincia seleccionada el Permiso de Movilización es opcional, pero se recomienda adjuntarlo si está disponible.
                </flux:text>
            </flux:callout>
        </div>

    </div>

    <div x-show="origen === 'Exterior (Extranjero)'" x-cloak>
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                Para especímenes del <strong>exterior</strong> se requiere una <strong>Carta de Procedencia</strong> firmada por el responsable de la colección de origen.
            </flux:text>
        </flux:callout>
    </div>

</div>
