<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Taxones</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
            Nuevo taxón
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>
            {{ $successMessage }}
        </flux:callout>
    @endif

    @if($errorMessage && !$showModal && !$showEditModal)
        <flux:callout variant="danger" dismissible>
            {{ $errorMessage }}
        </flux:callout>
    @endif

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-navy border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-white">Nombre Científico</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Rango</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Taxón Padre</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Autor</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Año</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($taxonesPaginados as $taxon)
                    <tr class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-medium text-text-primary font-serif italic">{{ $taxon['nombreCientifico'] }}</td>
                        <td class="px-4 py-3 text-text-primary capitalize">{{ $taxon['rango'] }}</td>
                        <td class="px-4 py-3 text-text-secondary text-xs font-serif italic">
                            {{ $taxon['padreNombre'] ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-text-primary">{{ $taxon['autor'] }}</td>
                        <td class="px-4 py-3 text-text-primary">{{ $taxon['anioDescripcion'] }}</td>
                        <td class="px-4 py-3">
                            @if($taxon['estado'] === 'activo')
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activo</span>
                            @else
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 flex items-center gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil"
                                wire:click="abrirEditModal('{{ $taxon['id'] }}')"
                            >
                                Editar
                            </flux:button>
                            @if($taxon['estado'] === 'activo')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="x-circle"
                                    wire:click="desactivarTaxon('{{ $taxon['id'] }}')"
                                    wire:confirm="¿Desactivar este taxón?"
                                >
                                    Desactivar
                                </flux:button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-text-secondary">
                            No hay taxones registrados.
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

    {{-- Modal: Registrar taxón --}}
    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo taxón</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre Científico</flux:label>
                <flux:input wire:model="nombreCientifico" placeholder="Ej. Morpho peleides" />
                <flux:error name="nombreCientifico" />
            </flux:field>

            <flux:field>
                <flux:label>Rango Taxonómico</flux:label>
                <flux:select wire:model="rango">
                    <option value="">Seleccione un rango...</option>
                    @foreach($rangos as $r)
                        <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="rango" />
            </flux:field>

            <flux:field>
                <flux:label>Autor</flux:label>
                <flux:input wire:model="autor" placeholder="Ej. Linnaeus" />
                <flux:error name="autor" />
            </flux:field>

            <flux:field>
                <flux:label>Año de Descripción</flux:label>
                <flux:input type="number" wire:model="anioDescripcion" min="1700" max="2100" />
                <flux:error name="anioDescripcion" />
            </flux:field>

            <flux:field>
                <flux:label>Taxón Padre (opcional)</flux:label>
                <flux:select wire:model="padreId">
                    <option value="">Sin padre (taxón raíz)</option>
                    @foreach($taxones as $t)
                        <option value="{{ $t['id'] }}">{{ $t['nombreCientifico'] }} ({{ $t['rango'] }})</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="registrarTaxon" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrarTaxon">Registrar Taxón</span>
                    <span wire:loading wire:target="registrarTaxon">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar taxón --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-lg">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar taxón</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre Científico</flux:label>
                <flux:input wire:model="editNombreCientifico" />
                <flux:error name="editNombreCientifico" />
            </flux:field>

            <flux:field>
                <flux:label>Autor</flux:label>
                <flux:input wire:model="editAutor" />
                <flux:error name="editAutor" />
            </flux:field>

            <flux:field>
                <flux:label>Año de Descripción</flux:label>
                <flux:input type="number" wire:model="editAnioDescripcion" min="1700" max="2100" />
                <flux:error name="editAnioDescripcion" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="actualizarTaxon" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarTaxon">Guardar</span>
                    <span wire:loading wire:target="actualizarTaxon">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
