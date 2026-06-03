<div class="space-y-6 p-4 sm:p-6 max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Configuración Dataset GBIF</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Metadatos institucionales requeridos por el exportador Darwin Core Archive.
                @if($existeConfig)
                    <span class="inline-flex items-center gap-1 text-success">
                        <flux:icon name="check-circle" class="size-3.5" />
                        Configurado
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-warning">
                        <flux:icon name="exclamation-triangle" class="size-3.5" />
                        Sin configurar — requerido antes de poder exportar a GBIF
                    </span>
                @endif
            </p>
        </div>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <form wire:submit="guardar" class="space-y-6">

        {{-- Sección 1: Identificación del museo --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-5 py-4 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-error"></span>
                        Identificación del museo
                    </span>
                </flux:heading>
                <p class="text-xs text-text-secondary mt-1">Requeridos por GBIF para que el dataset sea publicable.</p>
            </div>
            <div class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>institutionCode <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="institutionCode" placeholder="MEPN" />
                    <flux:description>Código corto del museo. Ej: <code>MEPN</code>.</flux:description>
                    <flux:error name="institutionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>collectionCode <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="collectionCode" placeholder="INV" />
                    <flux:description>Código de la colección. Ej: <code>INV</code> para invertebrados.</flux:description>
                    <flux:error name="collectionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>institutionID</flux:label>
                    <flux:input wire:model="institutionId" placeholder="ROR / GRBio / GBIF Org ID" />
                    <flux:description>Identificador global del museo (opcional).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>collectionID</flux:label>
                    <flux:input wire:model="collectionId" placeholder="GBIF Collection ID" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>ownerInstitutionCode</flux:label>
                    <flux:input wire:model="ownerInstitutionCode" placeholder="Código de la institución propietaria si difiere de institutionCode" />
                </flux:field>
            </div>
        </div>

        {{-- Sección 2: Tipo de registros --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-5 py-4 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-warning"></span>
                        Tipo de registros
                    </span>
                </flux:heading>
            </div>
            <div class="p-5">
                <flux:field>
                    <flux:label>basisOfRecord</flux:label>
                    <flux:select wire:model="basisOfRecord">
                        @foreach($basisOfRecords as $br)
                            <option value="{{ $br->value }}">{{ $br->value }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        Tipo Darwin Core de los registros publicados. Para una colección entomológica seca o líquida usa <code>PreservedSpecimen</code>.
                    </flux:description>
                </flux:field>
            </div>
        </div>

        {{-- Sección 3: Licencia y derechos --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-5 py-4 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-warning"></span>
                        Licencia y derechos
                    </span>
                </flux:heading>
                <p class="text-xs text-text-secondary mt-1">Define las condiciones de uso de los datos en GBIF.</p>
            </div>
            <div class="p-5 space-y-4">
                <flux:field>
                    <flux:label>License (URL)</flux:label>
                    <flux:input wire:model="license" placeholder="https://creativecommons.org/licenses/by-nc/4.0/" />
                    <flux:description>
                        Licencias frecuentes:
                        @foreach($licenciasFrecuentes as $lic)
                            <button type="button" class="text-info hover:underline mr-2"
                                    wire:click.prevent="$set('license', '{{ $lic['value'] }}')">
                                {{ $lic['label'] }}
                            </button>
                        @endforeach
                    </flux:description>
                </flux:field>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>rightsHolder</flux:label>
                        <flux:input wire:model="rightsHolder" placeholder='Museo de Historia Natural "Gustavo Orces V"' />
                    </flux:field>

                    <flux:field>
                        <flux:label>accessRights</flux:label>
                        <flux:input wire:model="accessRights" placeholder="Términos adicionales de acceso (opcional)" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>informationWithheld</flux:label>
                    <flux:textarea wire:model="informationWithheld"
                                   rows="2"
                                   placeholder="Información sensible omitida intencionalmente (e.g. coordenadas exactas de especies amenazadas)" />
                </flux:field>
            </div>
        </div>

        {{-- Sección 4: Metadatos EML --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-5 py-4 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-text-secondary"></span>
                        Metadatos EML
                    </span>
                </flux:heading>
                <p class="text-xs text-text-secondary mt-1">Información descriptiva del dataset para el archivo <code>eml.xml</code>.</p>
            </div>
            <div class="p-5 space-y-4">
                <flux:field>
                    <flux:label>datasetName</flux:label>
                    <flux:input wire:model="datasetName" placeholder="Catálogo de invertebrados — Museo de Historia Natural Gustavo Orces V" />
                </flux:field>

                <flux:field>
                    <flux:label>EML — Título</flux:label>
                    <flux:input wire:model="emlTitulo" placeholder="Título descriptivo del dataset" />
                </flux:field>

                <flux:field>
                    <flux:label>EML — Contacto</flux:label>
                    <flux:textarea wire:model="emlContacto"
                                   rows="3"
                                   placeholder="Nombre, email y filiación del responsable del dataset" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">{{ $existeConfig ? 'Actualizar configuración' : 'Guardar configuración' }}</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </flux:button>
        </div>
    </form>
</div>
