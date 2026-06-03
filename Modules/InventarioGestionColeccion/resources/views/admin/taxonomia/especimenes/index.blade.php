<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Especímenes</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
            Nuevo especímen
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>
            {{ $successMessage }}
        </flux:callout>
    @endif

    @if($errorMessage && !$showModal)
        <flux:callout variant="danger" dismissible>
            {{ $errorMessage }}
        </flux:callout>
    @endif

    {{-- Formulario de búsqueda --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
        <flux:heading size="md" level="2" class="text-text-primary">Búsqueda</flux:heading>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <flux:field>
                <flux:label>Buscar por</flux:label>
                <flux:select wire:model="criterio">
                    <option value="taxon">Nombre de Taxón</option>
                    <option value="codigo">Código</option>
                    <option value="occurrence_id">Occurrence ID</option>
                    <option value="catalog_number">Catalog Number</option>
                    <option value="localidad">Localidad</option>
                    <option value="estado">Estado</option>
                </flux:select>
                <flux:error name="criterio" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>Valor</flux:label>
                <flux:input
                    wire:model="valor"
                    wire:keydown.enter="buscar"
                    placeholder="Ingrese el valor de búsqueda..."
                />
                <flux:error name="valor" />
            </flux:field>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="buscar">Buscar</span>
                <span wire:loading wire:target="buscar">Buscando...</span>
            </flux:button>
            @if($buscado)
                <flux:button variant="ghost" icon="x-mark" wire:click="limpiar">
                    Limpiar
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Resultados --}}
    @if($buscado)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-bg-main border-b border-border">
                <span class="text-sm font-medium text-text-primary">
                    {{ count($especimenes) }} resultado(s) encontrado(s)
                </span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Código Catálogo</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Identificadores</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Taxón</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Ubicación</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha Colecta</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Colector</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Preparación</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($especimenesPaginados as $especimen)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $especimen['codigoCatalogo'] }}</td>
                            <td class="px-4 py-3 text-xs text-text-secondary">
                                @if(!empty($especimen['occurrenceId']))
                                    <div>{{ $especimen['occurrenceId'] }}</div>
                                @endif
                                @if(!empty($especimen['catalogNumber']))
                                    <div>Cat. {{ $especimen['catalogNumber'] }}</div>
                                @endif
                                @if(!empty($especimen['oldCode']))
                                    <div>Ant. {{ $especimen['oldCode'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-text-secondary text-xs font-serif italic">{{ $especimen['taxonNombre'] ?? $especimen['taxonId'] }}</td>
                            <td class="px-4 py-3 text-text-primary">
                                <div>{{ $especimen['localityName'] ?? $especimen['localidad'] }}</div>
                                <div class="text-xs text-text-secondary">
                                    {{ collect([$especimen['municipality'] ?? null, $especimen['stateProvince'] ?? null, $especimen['country'] ?? null])->filter()->implode(', ') }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['fechaColecta'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['colector'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['preparations'] ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold
                                    @if($especimen['estado'] === 'disponible') bg-success text-white
                                    @elseif($especimen['estado'] === 'en_prestamo') bg-warning text-white
                                    @else bg-border text-text-primary
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $especimen['estado'])) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($especimen['estado'] === 'disponible')
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil"
                                        wire:click="abrirEditModal('{{ $especimen['id'] }}')"
                                    >
                                        Editar
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-text-secondary">
                                No se encontraron especímenes con los criterios especificados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <x-inventariogestioncoleccion::paginacion-tabla
                :pagina="$page"
                :total-paginas="$totalPaginas"
                :total-items="$totalItems"
                :inicio="$inicio"
                :fin="$fin"
            />
        </div>
    @endif

    {{-- Modal: Registrar especímen --}}
    <flux:modal wire:model="showModal" class="w-full max-w-4xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo especímen</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Código de Catálogo</flux:label>
                    <flux:input wire:model="codigoCatalogo" placeholder="COLEOP-001" />
                    <flux:error name="codigoCatalogo" />
                </flux:field>

                <flux:field>
                    <flux:label>Fecha de Colecta</flux:label>
                    <flux:input type="date" wire:model="fechaColecta" />
                    <flux:error name="fechaColecta" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Occurrence ID</flux:label>
                    <flux:input wire:model="occurrenceId" placeholder="MEPN:INV:1" />
                    <flux:error name="occurrenceId" />
                </flux:field>

                <flux:field>
                    <flux:label>Catalog Number</flux:label>
                    <flux:input wire:model="catalogNumber" placeholder="1" />
                    <flux:error name="catalogNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>Código anterior</flux:label>
                    <flux:input wire:model="oldCode" placeholder="560" />
                    <flux:error name="oldCode" />
                </flux:field>

                <flux:field>
                    <flux:label>Código cardex</flux:label>
                    <flux:input wire:model="cardexLiquidCollectionCode" placeholder="MIA-33256" />
                    <flux:error name="cardexLiquidCollectionCode" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Taxón</flux:label>
                <flux:select wire:model="taxonId">
                    <option value="">Seleccione un taxón...</option>
                    @foreach($taxones as $taxon)
                        <option value="{{ $taxon['id'] }}">{{ $taxon['label'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="taxonId" />
            </flux:field>

            <flux:field>
                <flux:label>Localidad</flux:label>
                <flux:input wire:model="localidad" placeholder="Ej. Pichincha, Ecuador" />
                <flux:error name="localidad" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>País</flux:label>
                    <flux:input wire:model="country" placeholder="Ecuador" />
                    <flux:error name="country" />
                </flux:field>

                <flux:field>
                    <flux:label>Provincia</flux:label>
                    <flux:input wire:model="stateProvince" placeholder="Orellana" />
                    <flux:error name="stateProvince" />
                </flux:field>

                <flux:field>
                    <flux:label>Municipio</flux:label>
                    <flux:input wire:model="municipality" placeholder="Aguarico" />
                    <flux:error name="municipality" />
                </flux:field>

                <flux:field>
                    <flux:label>Localidad Darwin Core</flux:label>
                    <flux:input wire:model="localityName" placeholder="Parque Nacional Yasuní" />
                    <flux:error name="localityName" />
                </flux:field>

                <flux:field>
                    <flux:label>Latitud</flux:label>
                    <flux:input wire:model="decimalLatitude" placeholder="-0.658" />
                    <flux:error name="decimalLatitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitud</flux:label>
                    <flux:input wire:model="decimalLongitude" placeholder="-76.452" />
                    <flux:error name="decimalLongitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Datum</flux:label>
                    <flux:input wire:model="geodeticDatum" placeholder="WGS84" />
                    <flux:error name="geodeticDatum" />
                </flux:field>

                <flux:field>
                    <flux:label>Elevación</flux:label>
                    <flux:input wire:model="elevationInMeters" placeholder="216.3" />
                    <flux:error name="elevationInMeters" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Colector</flux:label>
                <flux:input wire:model="colector" placeholder="Nombre del colector" />
                <flux:error name="colector" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Individuos</flux:label>
                    <flux:input wire:model="individualCount" placeholder="8" />
                    <flux:error name="individualCount" />
                </flux:field>

                <flux:field>
                    <flux:label>Preparación</flux:label>
                    <flux:input wire:model="preparations" placeholder="ethanol" />
                    <flux:error name="preparations" />
                </flux:field>

                <flux:field>
                    <flux:label>Disposición</flux:label>
                    <flux:input wire:model="disposition" placeholder="En colección" />
                    <flux:error name="disposition" />
                </flux:field>

                <flux:field>
                    <flux:label>Occurrence status</flux:label>
                    <flux:input wire:model="occurrenceStatus" placeholder="present" />
                    <flux:error name="occurrenceStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>Bioma</flux:label>
                    <flux:input wire:model="biome" placeholder="Amazonia" />
                    <flux:error name="biome" />
                </flux:field>

                <flux:field>
                    <flux:label>Hábitat</flux:label>
                    <flux:input wire:model="habitat" placeholder="Bosque de tierra firme" />
                    <flux:error name="habitat" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Notas del espécimen</flux:label>
                <flux:textarea wire:model="specimenNotes" />
                <flux:error name="specimenNotes" />
            </flux:field>

            <flux:field>
                <flux:label>Entidad depositante (opcional)</flux:label>
                <flux:select wire:model="entidadDepositanteId">
                    <option value="">Sin entidad depositante</option>
                    @foreach($entidades as $entidad)
                        <option value="{{ $entidad['id'] }}">{{ $entidad['label'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="registrarEspecimen" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrarEspecimen">Registrar</span>
                    <span wire:loading wire:target="registrarEspecimen">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar especímen --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-4xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar especímen</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Localidad</flux:label>
                <flux:input wire:model="editLocalidad" placeholder="Ej. Pichincha, Ecuador" />
                <flux:error name="editLocalidad" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>País</flux:label>
                    <flux:input wire:model="editCountry" />
                </flux:field>

                <flux:field>
                    <flux:label>Provincia</flux:label>
                    <flux:input wire:model="editStateProvince" />
                </flux:field>

                <flux:field>
                    <flux:label>Municipio</flux:label>
                    <flux:input wire:model="editMunicipality" />
                </flux:field>

                <flux:field>
                    <flux:label>Localidad Darwin Core</flux:label>
                    <flux:input wire:model="editLocalityName" />
                </flux:field>

                <flux:field>
                    <flux:label>Latitud</flux:label>
                    <flux:input wire:model="editDecimalLatitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitud</flux:label>
                    <flux:input wire:model="editDecimalLongitude" />
                </flux:field>

                <flux:field>
                    <flux:label>Datum</flux:label>
                    <flux:input wire:model="editGeodeticDatum" />
                </flux:field>

                <flux:field>
                    <flux:label>Elevación</flux:label>
                    <flux:input wire:model="editElevationInMeters" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Fecha de Colecta</flux:label>
                <flux:input type="date" wire:model="editFechaColecta" />
                <flux:error name="editFechaColecta" />
            </flux:field>

            <flux:field>
                <flux:label>Colector</flux:label>
                <flux:input wire:model="editColector" placeholder="Nombre del colector" />
                <flux:error name="editColector" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Preparación</flux:label>
                    <flux:input wire:model="editPreparations" />
                </flux:field>

                <flux:field>
                    <flux:label>Disposición</flux:label>
                    <flux:input wire:model="editDisposition" />
                </flux:field>

                <flux:field>
                    <flux:label>Occurrence status</flux:label>
                    <flux:input wire:model="editOccurrenceStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>Bioma</flux:label>
                    <flux:input wire:model="editBiome" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>Hábitat</flux:label>
                    <flux:input wire:model="editHabitat" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Notas del espécimen</flux:label>
                <flux:textarea wire:model="editSpecimenNotes" rows="3" />
            </flux:field>

            <flux:field>
                <flux:label>Entidad depositante (opcional)</flux:label>
                <flux:select wire:model="editEntidadDepositanteId">
                    <option value="">Sin entidad depositante</option>
                    @foreach($entidades as $entidad)
                        <option value="{{ $entidad['id'] }}">{{ $entidad['label'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="actualizarEspecimen" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarEspecimen">Guardar</span>
                    <span wire:loading wire:target="actualizarEspecimen">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
