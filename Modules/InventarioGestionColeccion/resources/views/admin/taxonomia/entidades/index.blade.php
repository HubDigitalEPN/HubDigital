<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Entidades depositantes</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal">
            Nueva entidad
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
                    <th class="px-4 py-3 text-left font-medium text-white">Nombre</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Tipo</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Contacto</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($entidadesPaginadas as $entidad)
                    <tr class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-medium text-text-primary">{{ $entidad['nombre'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold
                                @if($entidad['tipo'] === 'institucion') bg-science-blue text-white
                                @else bg-border text-text-primary
                                @endif">
                                {{ ucfirst($entidad['tipo']) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-text-primary">{{ $entidad['contacto'] }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="abrirEditModal('{{ $entidad['id'] }}')"
                                >
                                    Editar
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="document-text"
                                    wire:click="generarActa('{{ $entidad['id'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="generarActa('{{ $entidad['id'] }}')"
                                    wire:confirm="¿Generar acta de entrega para {{ $entidad['nombre'] }}?"
                                >
                                    Generar acta
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-text-secondary">
                            No hay entidades depositantes registradas.
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

    {{-- Modal: Registrar entidad --}}
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nueva entidad depositante</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Universidad Central" />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select wire:model="tipo">
                    <option value="">Seleccione un tipo...</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="tipo" />
            </flux:field>

            <flux:field>
                <flux:label>Contacto</flux:label>
                <flux:input wire:model="contacto" placeholder="Ej. contacto@universidad.edu.ec" />
                <flux:error name="contacto" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="registrarEntidad" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrarEntidad">Registrar</span>
                    <span wire:loading wire:target="registrarEntidad">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar entidad --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar entidad depositante</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="editNombre" />
                <flux:error name="editNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select wire:model="editTipo">
                    @foreach($tipos as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="editTipo" />
            </flux:field>

            <flux:field>
                <flux:label>Contacto</flux:label>
                <flux:input wire:model="editContacto" />
                <flux:error name="editContacto" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="actualizarEntidad" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarEntidad">Guardar</span>
                    <span wire:loading wire:target="actualizarEntidad">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Acta generada --}}
    <flux:modal wire:model="showActaModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Acta generada</flux:heading>

            <flux:callout variant="success">
                El acta de entrega para <strong>{{ $actaEntidadNombre }}</strong> ha sido generada correctamente.
            </flux:callout>

            @if($actaPdfRuta)
                <p class="text-sm text-text-secondary">
                    El archivo se ha guardado en:
                    <code class="text-xs bg-bg-main px-1 py-0.5 rounded">{{ $actaPdfRuta }}</code>
                </p>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button variant="primary" wire:click="$set('showActaModal', false)">
                    Cerrar
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
