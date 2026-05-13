<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Especímenes</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
            Nuevo Especímen
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
                        <th class="px-4 py-3 text-left font-medium text-white">Taxón</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Localidad</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Fecha Colecta</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Colector</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($especimenesPaginados as $especimen)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $especimen['codigoCatalogo'] }}</td>
                            <td class="px-4 py-3 text-text-secondary text-xs font-serif italic">{{ $especimen['taxonId'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['localidad'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['fechaColecta'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $especimen['colector'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold
                                    @if($especimen['estado'] === 'disponible') bg-success text-white
                                    @elseif($especimen['estado'] === 'en_prestamo') bg-warning text-white
                                    @else bg-border text-text-primary
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $especimen['estado'])) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-text-secondary">
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
    <flux:modal wire:model="showModal" class="w-full max-w-xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo Especímen</flux:heading>

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

            <flux:field>
                <flux:label>Colector</flux:label>
                <flux:input wire:model="colector" placeholder="Nombre del colector" />
                <flux:error name="colector" />
            </flux:field>

            <flux:field>
                <flux:label>Entidad Depositante (opcional)</flux:label>
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
</div>
