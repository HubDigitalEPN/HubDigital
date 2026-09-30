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

    <div class="border-b border-blue-navy/10 pb-3">
        <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('tramite.titulo') }}</flux:heading>
        <flux:text class="mt-1 text-sm leading-5 text-text-secondary">{{ \App\Support\WizardCopy::text('tramite.intro') }}</flux:text>
    </div>

    <flux:error name="tipoTramite" />

    <div class="grid gap-4 sm:grid-cols-2" wire:ignore aria-label="Modalidad del trámite">
        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Depósito'"
            titulo="Depósito"
            grupo="tipoTramite"
            descripcion="{{ \App\Support\WizardCopy::text('tramite.deposito') }}"
            role="radio"
            x-bind:aria-checked="active"
            tabindex="0"
            x-on:click="seleccionar('Depósito')"
            x-on:keydown.enter.prevent="seleccionar('Depósito')"
            x-on:keydown.space.prevent="seleccionar('Depósito')"
        >
            <x-slot:icono>
                <flux:icon name="archive-box" class="size-5 text-blue-navy" />
            </x-slot:icono>
        </x-gestionprestamosrecepciones::radio-card>

        <x-gestionprestamosrecepciones::radio-card
            :activo="$tipoTramite === 'Donación'"
            titulo="Donación"
            grupo="tipoTramite"
            descripcion="{{ \App\Support\WizardCopy::text('tramite.donacion') }}"
            role="radio"
            x-bind:aria-checked="active"
            tabindex="0"
            x-on:click="seleccionar('Donación')"
            x-on:keydown.enter.prevent="seleccionar('Donación')"
            x-on:keydown.space.prevent="seleccionar('Donación')"
        >
            <x-slot:icono>
                <flux:icon name="heart" class="size-5 text-bio-green" />
            </x-slot:icono>
        </x-gestionprestamosrecepciones::radio-card>
    </div>

    <div x-show="tipo === 'Depósito'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                {{ \App\Support\WizardCopy::text('tramite.aviso_deposito') }}
            </flux:text>
        </flux:callout>
        <div class="border-l-2 border-blue-navy/20 bg-[#F8FAFC] px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.1em] text-blue-navy">{{ \App\Support\WizardCopy::text('tramite.documentos_titulo') }}</p>
            <ul class="grid gap-x-4 gap-y-2 text-xs leading-5 text-text-secondary sm:grid-cols-2">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_deposito_solicitud') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_deposito_permisos') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_deposito_carta') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_datos') }}
                </li>
            </ul>
            <p class="text-xs text-text-secondary/60 mt-2 italic">{{ \App\Support\WizardCopy::text('tramite.nota_requisitos') }}</p>
        </div>
    </div>
    <div x-show="tipo === 'Donación'" x-cloak class="space-y-3">
        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                {{ \App\Support\WizardCopy::text('tramite.aviso_donacion') }}
            </flux:text>
        </flux:callout>
        <div class="border-l-2 border-bio-green/30 bg-[#F8FAFC] px-4 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.1em] text-blue-navy">{{ \App\Support\WizardCopy::text('tramite.documentos_titulo') }}</p>
            <ul class="grid gap-x-4 gap-y-2 text-xs leading-5 text-text-secondary sm:grid-cols-2">
                <li class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_donacion_solicitud') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="document-check" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_donacion_carta') }}
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-3.5 text-text-secondary shrink-0" />
                    {{ \App\Support\WizardCopy::text('tramite.requisito_datos') }}
                </li>
            </ul>
        </div>
    </div>

</div>
