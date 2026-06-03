<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Localidades</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
            Nueva Localidad
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage && !$showModal && !$showEditModal)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-navy border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-white">Nombre canónico</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Rango</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Padre</th>
                    <th class="px-4 py-3 text-left font-medium text-white">País</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Provincia</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Coordenadas</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($localidadesPaginadas as $l)
                    <tr class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-medium text-text-primary">{{ $l['nombreCanonico'] }}</td>
                        <td class="px-4 py-3 text-text-primary capitalize">{{ str_replace('_', ' ', $l['rango']) }}</td>
                        <td class="px-4 py-3 text-text-secondary text-xs">{{ $l['padreNombre'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-primary">{{ $l['country'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-primary">{{ $l['stateProvince'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary text-xs">
                            @if($l['latitud'] !== null && $l['longitud'] !== null)
                                {{ number_format((float) $l['latitud'], 4) }}, {{ number_format((float) $l['longitud'], 4) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil"
                                wire:click="abrirEditModal('{{ $l['id'] }}')"
                            >
                                Editar
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-text-secondary">
                            No hay localidades registradas.
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

    {{-- Modal: Registrar localidad --}}
    <flux:modal wire:model="showModal" class="w-full max-w-xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nueva Localidad</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre canónico</flux:label>
                <flux:input wire:model="nombreCanonico" placeholder="Ej. Estación Yasuní" />
                <flux:error name="nombreCanonico" />
            </flux:field>

            <flux:field>
                <flux:label>Rango</flux:label>
                <flux:select wire:model="rango">
                    <option value="">Seleccione un rango...</option>
                    @foreach($rangos as $r)
                        <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="rango" />
            </flux:field>

            <flux:field>
                <flux:label>Localidad padre (opcional)</flux:label>
                <flux:select wire:model="padreId">
                    <option value="">Sin padre (raíz)</option>
                    @foreach($localidades as $cand)
                        <option value="{{ $cand['id'] }}">{{ $cand['nombreCanonico'] }} ({{ $cand['rango'] }})</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Latitud</flux:label>
                    <flux:input type="number" step="0.0000001" wire:model="latitud" />
                    <flux:error name="latitud" />
                </flux:field>
                <flux:field>
                    <flux:label>Longitud</flux:label>
                    <flux:input type="number" step="0.0000001" wire:model="longitud" />
                    <flux:error name="longitud" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Datum geodésico (opcional)</flux:label>
                <flux:input wire:model="geodeticDatum" placeholder="Ej. WGS84" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>País</flux:label>
                    <flux:input wire:model="country" />
                </flux:field>
                <flux:field>
                    <flux:label>Provincia</flux:label>
                    <flux:input wire:model="stateProvince" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="registrarLocalidad" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrarLocalidad">Registrar</span>
                    <span wire:loading wire:target="registrarLocalidad">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar localidad --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar Localidad</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre canónico</flux:label>
                <flux:input wire:model="editNombreCanonico" />
                <flux:error name="editNombreCanonico" />
            </flux:field>

            <flux:field>
                <flux:label>Rango</flux:label>
                <flux:select wire:model="editRango">
                    @foreach($rangos as $r)
                        <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="editRango" />
            </flux:field>

            <flux:field>
                <flux:label>Localidad padre (opcional)</flux:label>
                <flux:select wire:model="editPadreId">
                    <option value="">Sin padre</option>
                    @foreach($localidades as $cand)
                        @if($cand['id'] !== $editandoId)
                            <option value="{{ $cand['id'] }}">{{ $cand['nombreCanonico'] }} ({{ $cand['rango'] }})</option>
                        @endif
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Latitud</flux:label>
                    <flux:input type="number" step="0.0000001" wire:model="editLatitud" />
                </flux:field>
                <flux:field>
                    <flux:label>Longitud</flux:label>
                    <flux:input type="number" step="0.0000001" wire:model="editLongitud" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>País</flux:label>
                    <flux:input wire:model="editCountry" />
                </flux:field>
                <flux:field>
                    <flux:label>Provincia</flux:label>
                    <flux:input wire:model="editStateProvince" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="actualizarLocalidad" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarLocalidad">Guardar</span>
                    <span wire:loading wire:target="actualizarLocalidad">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
