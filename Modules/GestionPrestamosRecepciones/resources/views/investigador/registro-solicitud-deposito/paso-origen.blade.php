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
        seleccionarZona(titulo, wireVal) {
            this.provincia = wireVal;
            $dispatch('radio-card-select', { grupo: 'provincia', valor: titulo });
            $wire.set('provincia', wireVal);
        },
    }"
>

    <div>
        <flux:heading size="lg" level="2" class="font-display">Origen de los especímenes</flux:heading>
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

        <div class="space-y-3">
            <flux:label>Zona de recolección <span class="text-error">*</span></flux:label>
            <flux:error name="provincia" />

            <div class="grid gap-3 sm:grid-cols-2">
                <x-gestionprestamosrecepciones::radio-card
                    :activo="$provincia === 'Pichincha'"
                    titulo="Dentro de Pichincha"
                    grupo="provincia"
                    descripcion="Los especímenes fueron recolectados en la provincia de Pichincha."
                    x-on:click="seleccionarZona('Dentro de Pichincha', 'Pichincha')"
                >
                    <x-slot:icono>
                        <flux:icon name="map-pin" class="size-5 text-bio-green" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>

                <x-gestionprestamosrecepciones::radio-card
                    :activo="$provincia === 'Fuera de Pichincha'"
                    titulo="Fuera de Pichincha"
                    grupo="provincia"
                    descripcion="Los especímenes provienen de otra provincia del Ecuador."
                    x-on:click="seleccionarZona('Fuera de Pichincha', 'Fuera de Pichincha')"
                >
                    <x-slot:icono>
                        <flux:icon name="map" class="size-5 text-science-blue" />
                    </x-slot:icono>
                </x-gestionprestamosrecepciones::radio-card>
            </div>
        </div>

    </div>

    <div x-show="origen === 'Exterior (Extranjero)'" x-cloak>
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                Para especímenes del <strong>exterior</strong> se requiere una <strong>Carta de procedencia</strong> firmada por el responsable de la colección de origen.
            </flux:text>
        </flux:callout>
    </div>

</div>
