<div class="space-y-6 p-4 sm:p-6 max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Publicación GBIF</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Datos de tu museo/colección que se necesitan una sola vez para poder publicar en GBIF.
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
                    <flux:label>Código de la institución <span class="text-text-secondary font-normal">(institutionCode)</span> <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="institutionCode" placeholder="MEPN" />
                    <flux:description>Código corto del museo. Ej: <code>MEPN</code>.</flux:description>
                    <flux:error name="institutionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Código de la colección <span class="text-text-secondary font-normal">(collectionCode)</span> <span class="text-error">*</span></flux:label>
                    <flux:input wire:model="collectionCode" placeholder="INV" />
                    <flux:description>Código de la colección. Ej: <code>INV</code> para invertebrados.</flux:description>
                    <flux:error name="collectionCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Identificador global de la institución <span class="text-text-secondary font-normal">(institutionID)</span></flux:label>
                    <flux:input wire:model="institutionId" placeholder="ROR / GRBio / GBIF Org ID" />
                    <flux:description>Identificador global del museo (opcional).</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Identificador global de la colección <span class="text-text-secondary font-normal">(collectionID)</span></flux:label>
                    <flux:input wire:model="collectionId" placeholder="GBIF Collection ID" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>Institución propietaria (si difiere) <span class="text-text-secondary font-normal">(ownerInstitutionCode)</span></flux:label>
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
                    <flux:label>Licencia de uso (URL) <span class="text-text-secondary font-normal">(license)</span></flux:label>
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

        <div class="flex justify-end gap-3">
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">{{ $existeConfig ? 'Actualizar configuración' : 'Guardar configuración' }}</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </flux:button>
        </div>
    </form>

    {{-- Sección: Exportar Darwin Core Archive --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="px-5 py-4 bg-bg-main border-b border-border">
            <flux:heading size="md" level="2" class="text-text-primary">
                <span class="inline-flex items-center gap-2">
                    <flux:icon name="cloud-arrow-down" class="size-5 text-bio-green" />
                    Exportar Darwin Core Archive
                </span>
            </flux:heading>
            <p class="text-xs text-text-secondary mt-1">
                Genera el ZIP listo para publicar en GBIF/IPT con los 4 archivos: <code>meta.xml</code>, <code>eml.xml</code>, <code>occurrence.txt</code> y <code>taxon.txt</code>.
            </p>
        </div>
        <div class="p-5 space-y-3">
            @if($existeConfig)
                <p class="text-sm text-text-primary">
                    Solo se incluyen especímenes que pasan <code class="text-xs">CriteriosCalidadGbif</code>: con taxón identificado, coordenadas válidas y no descartados.
                </p>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-text-secondary">
                        También desde terminal:
                        <code class="text-xs">php artisan inventario:exportar-gbif</code>
                    </p>
                    <a href="{{ route('inventario.taxonomia.dwc.descargar') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-bio-green text-white px-4 py-2 text-sm font-medium hover:opacity-90 transition-opacity">
                        <flux:icon name="arrow-down-tray" class="size-4" />
                        Descargar DwC-A (.zip)
                    </a>
                </div>
            @else
                <flux:callout variant="warning">
                    Configura primero el dataset (arriba) antes de exportar.
                </flux:callout>
            @endif
        </div>
    </div>

    {{-- Sección: Exportar selección de especímenes a Excel --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm">
        <div class="px-5 py-4 bg-bg-main border-b border-border">
            <flux:heading size="md" level="2" class="text-text-primary">
                <span class="inline-flex items-center gap-2">
                    <flux:icon name="table-cells" class="size-5 text-science-blue" />
                    Exportar especímenes seleccionados (Excel)
                </span>
            </flux:heading>
            <p class="text-xs text-text-secondary mt-1">
                Elige especímenes concretos con casillas y descárgalos como tabla Excel formateada.
            </p>
        </div>
        <div class="p-5">
            <livewire:inventario-exportar-especimenes-gbif />
        </div>
    </div>
</div>
