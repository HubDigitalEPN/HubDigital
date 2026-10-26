<div class="w-full space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Publicación GBIF</flux:heading>
        </div>
    </div>

    <p role="status" class="text-sm {{ $existeConfig ? 'text-success' : 'text-warning' }}">{{ $existeConfig ? 'Configurado' : 'Sin configurar' }}</p>

    @if($successMessage)<flux:callout data-hub-feedback="success" variant="success">{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage)<flux:callout data-hub-feedback="danger" variant="danger">{{ $errorMessage }}</flux:callout>@endif

    <form wire:submit="guardar" class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">

        {{-- Sección 1: Identificación y tipo de registros --}}
        <div class="min-w-0 rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-4 py-3 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-error"></span>
                        Identificación y tipo de registros
                    </span>
                </flux:heading>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Código de la institución <span class="text-text-secondary font-normal">(institutionCode)</span> <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="institutionCode" placeholder="MEPN" />

                    <flux:error name="institutionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Código de la colección <span class="text-text-secondary font-normal">(collectionCode)</span> <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="collectionCode" placeholder="INV" />

                    <flux:error name="collectionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Identificador global de la institución <span class="text-text-secondary font-normal">(institutionID)</span></flux:label>
                    <flux:input wire:model="institutionId" placeholder="ROR / GRBio / GBIF Org ID" />
                </flux:field>

                <flux:field>
                    <flux:label>Identificador global de la colección <span class="text-text-secondary font-normal">(collectionID)</span></flux:label>
                    <flux:input wire:model="collectionId" placeholder="GBIF Collection ID" />
                </flux:field>

                <flux:field>
                    <flux:label>Institución propietaria (si difiere) <span class="text-text-secondary font-normal">(ownerInstitutionCode)</span></flux:label>
                    <flux:input wire:model="ownerInstitutionCode" placeholder="Código de la institución propietaria si difiere de institutionCode" />
                </flux:field>
                <flux:field>
                    <flux:label>Tipo de registro <span class="text-text-secondary font-normal">(basisOfRecord)</span></flux:label>
                    <flux:select wire:model="basisOfRecord">
                        @foreach($basisOfRecords as $br)
                            @php($legible = match($br->value) {
                                'PreservedSpecimen' => 'Espécimen preservado',
                                'LivingSpecimen' => 'Espécimen vivo',
                                'FossilSpecimen' => 'Espécimen fósil',
                                'MaterialSample' => 'Muestra de material',
                                'HumanObservation' => 'Observación humana',
                                'MachineObservation' => 'Observación por instrumento',
                                default => $br->value,
                            })
                            <option value="{{ $br->value }}">{{ $legible }} ({{ $br->value }})</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        </div>

        {{-- Sección 3: Licencia y derechos --}}
        <div class="min-w-0 rounded-lg border border-border bg-surface shadow-sm">
            <div class="px-4 py-3 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-warning"></span>
                        Licencia y derechos
                    </span>
                </flux:heading>
            </div>
            <div class="p-4 space-y-3">
                <flux:field>
                    <flux:label>Licencia de uso (URL) <span class="text-text-secondary font-normal">(license)</span></flux:label>
                    <flux:input wire:model="license" placeholder="https://creativecommons.org/licenses/by-nc/4.0/" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($licenciasFrecuentes as $lic)
                            <button type="button" class="text-info hover:underline mr-2"
                                    wire:click.prevent="$set('license', '{{ $lic['value'] }}')">
                                {{ $lic['label'] }}
                            </button>
                        @endforeach
                    </div>
                </flux:field>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Titular de los derechos <span class="text-text-secondary font-normal">(rightsHolder)</span></flux:label>
                        <flux:input wire:model="rightsHolder" placeholder='Museo de Historia Natural "Gustavo Orces V"' />
                    </flux:field>

                    <flux:field>
                        <flux:label>Derechos de acceso <span class="text-text-secondary font-normal">(accessRights)</span></flux:label>
                        <flux:input wire:model="accessRights" placeholder="Términos adicionales de acceso (opcional)" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Información omitida a propósito <span class="text-text-secondary font-normal">(informationWithheld)</span></flux:label>
                    <flux:textarea wire:model="informationWithheld"
                                   rows="2"
                                   placeholder="Información sensible omitida intencionalmente (e.g. coordenadas exactas de especies amenazadas)" />
                </flux:field>
            </div>
        </div>

        {{-- Sección 4: Metadatos EML --}}
        <div class="min-w-0 rounded-lg border border-border bg-surface shadow-sm xl:col-span-2">
            <div class="px-4 py-3 bg-bg-main border-b border-border">
                <flux:heading size="md" level="2" class="text-text-primary">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-block size-2.5 rounded-full bg-text-secondary"></span>
                        Metadatos EML
                    </span>
                </flux:heading>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 lg:grid-cols-3">
                <flux:field>
                    <flux:label>Nombre del conjunto de datos <span class="text-text-secondary font-normal">(datasetName)</span></flux:label>
                    <flux:input wire:model="datasetName" placeholder="Catálogo de invertebrados — Museo de Historia Natural Gustavo Orces V" />
                </flux:field>

                <flux:field>
                    <flux:label>Título descriptivo del conjunto <span class="text-text-secondary font-normal">(EML)</span></flux:label>
                    <flux:input wire:model="emlTitulo" placeholder="Título descriptivo del dataset" />
                </flux:field>

                <flux:field>
                    <flux:label>Contacto responsable del conjunto <span class="text-text-secondary font-normal">(EML)</span></flux:label>
                    <flux:textarea wire:model="emlContacto"
                                   rows="3"
                                   placeholder="Nombre, email y filiación del responsable del dataset" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end gap-3 xl:col-span-2">
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">{{ $existeConfig ? 'Actualizar configuración' : 'Guardar configuración' }}</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </flux:button>
        </div>
    </form>

    {{-- Sección: Exportar Darwin Core Archive --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="px-4 py-3 bg-bg-main border-b border-border">
            <flux:heading size="md" level="2" class="text-text-primary">
                <span class="inline-flex items-center gap-2">
                    <flux:icon name="cloud-arrow-down" class="size-5 text-bio-green" />
                    Exportar Darwin Core Archive
                </span>
            </flux:heading>
        </div>
        <div class="p-4">
            @if($existeConfig)

                <div class="flex justify-end">

                    <a href="{{ route('inventario.taxonomia.dwc.descargar') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-bio-green text-white px-4 py-2 text-sm font-medium hover:opacity-90 transition-opacity">
                        <flux:icon name="arrow-down-tray" class="size-4" />
                        Descargar DwC-A (.zip)
                    </a>
                </div>
            @else
                <flux:callout data-hub-feedback="warning" variant="warning">
                    Configura primero el dataset (arriba) antes de exportar.
                </flux:callout>
            @endif
        </div>
    </div>

    {{-- Sección: Exportar selección de especímenes a Excel --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="min-w-0 p-4">
            <livewire:inventario-exportar-especimenes-gbif />
        </div>
    </div>
</div>
