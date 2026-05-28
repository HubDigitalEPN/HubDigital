<div
    class="space-y-6"
    x-data="{
        tipo: @js($tipoTramite),
        seleccionar(val) {
            this.tipo = val;
            $dispatch('radio-card-select', { grupo: 'tipoTramite', valor: val });
            $wire.set('tipoTramite', val);
        }
    }"
>

    <div>
        <flux:heading size="lg" level="2" class="font-display">Tipo de trámite</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Selecciona cómo deseas registrar tus especímenes entomológicos en la colección.
        </flux:text>
    </div>

    @if($limiteAlcanzado)
        <flux:callout variant="danger" icon="x-circle">
            <flux:heading>Límite anual de depósitos alcanzado</flux:heading>
            <flux:text>Has utilizado los 3 depósitos permitidos este año. Puedes continuar registrando como <strong>Donación</strong>.</flux:text>
        </flux:callout>
    @endif

    <flux:error name="tipoTramite" />

    <div class="grid gap-4 sm:grid-cols-2" wire:ignore>
        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Depósito'"
            :deshabilitado="$limiteAlcanzado"
            titulo="Depósito"
            grupo="tipoTramite"
            descripcion="Custodia temporal con devolución programada. Cupo anual limitado a 3 solicitudes."
            x-on:click="!{{ $limiteAlcanzado ? 'true' : 'false' }} && seleccionar('Depósito')"
        >
            <x-slot:icono>
                <flux:icon name="archive-box" class="size-5 text-blue-navy" />
            </x-slot:icono>
            <x-slot:badge>
                @if($solicitudesPreviasDeposito >= 3)
                    <flux:badge color="red" size="sm">Cupo lleno</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ $solicitudesPreviasDeposito }}/3</flux:badge>
                @endif
            </x-slot:badge>
        </x-gestionprestamosrecepciones::radio-card>

        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Donación'"
            titulo="Donación"
            grupo="tipoTramite"
            descripcion="Cesión definitiva al patrimonio de la colección. Requiere carta de cesión de derechos."
            x-on:click="seleccionar('Donación')"
        >
            <x-slot:icono>
                <flux:icon name="heart" class="size-5 text-bio-green" />
            </x-slot:icono>
            <x-slot:badge>
                <flux:badge color="green" size="sm">Sin límite</flux:badge>
            </x-slot:badge>
        </x-gestionprestamosrecepciones::radio-card>
    </div>

    <div x-show="tipo === 'Depósito'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                El <strong>Depósito</strong> es temporal. Los especímenes permanecerán en custodia y serán devueltos según el acta de préstamo correspondiente.
            </flux:text>
        </flux:callout>
        <div class="rounded-lg border border-border bg-bg-main p-4">
            <p class="text-xs font-medium text-text-primary mb-2">Documentos que necesitarás preparar:</p>
            <ul class="text-xs text-text-secondary space-y-1">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Formato de solicitud de depósito
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    Autorización de recolección y/o permiso de movilización <span class="text-text-secondary/60">(según origen)</span>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Carta de procedencia o justificación <span class="text-text-secondary/60">(según situación regulatoria)</span>
                </li>
            </ul>
            <p class="text-xs text-text-secondary/60 mt-2 italic">Los documentos exactos se determinarán en el siguiente paso según el origen de los especímenes.</p>
        </div>
    </div>
    <div x-show="tipo === 'Donación'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                La <strong>Donación</strong> transfiere permanentemente la propiedad de los especímenes a la colección. Se requiere carta de cesión de derechos con firma del donante.
            </flux:text>
        </flux:callout>
        <div class="rounded-lg border border-border bg-bg-main p-4">
            <p class="text-xs font-medium text-text-primary mb-2">Documentos que necesitarás preparar:</p>
            <ul class="text-xs text-text-secondary space-y-1">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    Formato de solicitud de donación
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    Carta de cesión de derechos / origen lícito
                </li>
            </ul>
        </div>
    </div>

</div>
