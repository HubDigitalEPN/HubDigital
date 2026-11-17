<div class="space-y-4">
    <div class="border-b border-blue-navy/10 pb-3 sm:flex sm:items-baseline sm:gap-4">
        <flux:heading size="lg" level="2" class="shrink-0 font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('origen.titulo') }}</flux:heading>

    </div>

    <flux:select id="finalidad-actividad" wire:model.live="finalidadActividad" label="Finalidad de la actividad" required>
        <flux:select.option value="">Selecciona una finalidad</flux:select.option>
        <flux:select.option value="Investigación">Investigación</flux:select.option>
        <flux:select.option value="Consultoría">Consultoría</flux:select.option>
    </flux:select>
    <p class="text-sm text-text-secondary">La finalidad de la actividad es independiente del trámite de depósito o donación.</p>
    <p class="text-sm font-semibold text-blue-navy">Zona de recolección</p>

        <div class="hub-origin-fields">
            <x-gestionprestamosrecepciones::lov-popup campo="provincia" label="Provincia"
                :opciones="array_column($provinciasCatalogo, 'nombre')" :valor="$provincia"
                placeholder="Selecciona una provincia" accion="seleccionarProvincia" input-id="provincia-recoleccion" />
            <x-gestionprestamosrecepciones::lov-popup campo="localidadOrigenCodigo" label="Localidad INEC"
                :opciones="$localidadesOrigenCatalogo"
                :valor="$localidadOrigenCodigo" :disabled="!$provincia" :contexto="$provincia"
                placeholder="Selecciona una localidad" accion="seleccionarLocalidadOrigen"
                busqueda-remota="buscarLocalidadesOrigenLov" input-id="localidad-recoleccion" />
        </div>
        <flux:error name="localidad" />
        <p class="text-sm text-text-secondary">Si el lugar no figura, solicita al administrador su alta en el catálogo de localidades antes de continuar.</p>

        <div class="space-y-2">
            @if($finalidadActividad === 'Consultoría')
                <p class="text-sm text-text-secondary">La consultoría requiere permisos de recolección y movilización.</p>
            @endif
            <p class="text-sm font-semibold text-blue-navy">Situación regulatoria <span class="text-error">*</span></p>
            <flux:error name="situacionRegulatoria" />
            <div class="grid max-w-4xl gap-3 sm:grid-cols-2">
                @foreach([
                    ['valor' => 'Posee permisos del MAE', 'icono' => 'document-check', 'descripcion' => \App\Support\WizardCopy::text('origen.con_permisos')],
                    ['valor' => 'Sin permisos del MAE', 'icono' => 'exclamation-triangle', 'descripcion' => \App\Support\WizardCopy::text('origen.sin_permisos')],
                ] as $opcion)
                    <button type="button" wire:click="$set('situacionRegulatoria', '{{ $opcion['valor'] }}')" data-situacion="{{ $opcion['valor'] }}" aria-pressed="{{ $situacionRegulatoria === $opcion['valor'] ? 'true' : 'false' }}"
                        @disabled($finalidadActividad === 'Consultoría' && $opcion['valor'] === 'Sin permisos del MAE')
                        class="flex min-h-20 items-start gap-3 rounded-lg border p-3 text-left transition {{ $situacionRegulatoria === $opcion['valor'] ? 'border-blue-navy bg-blue-navy/5' : 'border-border hover:border-science-blue' }}">
                        <flux:icon :name="$opcion['icono']" class="mt-0.5 size-5 shrink-0 text-science-blue" />
                        <span><strong class="block text-sm text-blue-navy">{{ $opcion['valor'] }}</strong></span>
                    </button>
                @endforeach
            </div>
        </div>
</div>
