<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Cajas Entomológicas</flux:heading>
        <flux:button icon="plus" variant="primary" wire:click="$set('showCrearModal', true)">
            Nueva Caja
        </flux:button>
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    <flux:field>
        <flux:input
            wire:model.live="busqueda"
            icon="magnifying-glass"
            placeholder="Buscar por código o RFID..."
            class="max-w-sm"
        />
    </flux:field>

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-blue-navy border-b border-border">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-white">Código</th>
                    <th class="px-4 py-3 text-left font-medium text-white">RFID</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Nombre</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Cap. máx.</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                    <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($cajasFiltradas as $caja)
                    <tr class="hover:bg-bg-main transition-colors">
                        <td class="px-4 py-3 font-medium text-text-primary">{{ $caja['codigo'] }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ $caja['codigoRfid'] }}</td>
                        <td class="px-4 py-3 text-text-primary">{{ $caja['nombre'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-primary text-center">
                            {{ $caja['capacidadMaxima'] ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-inventariogestioncoleccion::seguimiento-fisico.caja-estado-badge
                                :estado="$caja['estado']"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if($caja['estado'] === 'en_transito')
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-down-tray"
                                        wire:click="abrirIngresoModal('{{ $caja['id'] }}')"
                                    >
                                        Ingresar
                                    </flux:button>
                                @endif
                                @if($caja['estado'] === 'en_gabinete')
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-up-tray"
                                        wire:click="registrarRetiro('{{ $caja['id'] }}')"
                                        wire:confirm="¿Registrar retiro de la caja {{ $caja['codigo'] }}? Quedará en tránsito."
                                        wire:loading.attr="disabled"
                                        wire:target="registrarRetiro('{{ $caja['id'] }}')"
                                    >
                                        Retirar
                                    </flux:button>
                                @endif
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="abrirEditCajaModal('{{ $caja['id'] }}')"
                                >
                                    Editar
                                </flux:button>
                                @if($caja['estado'] === 'en_transito')
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="eliminarCaja('{{ $caja['id'] }}')"
                                        wire:confirm="¿Eliminar la caja {{ $caja['codigo'] }}? Esta acción no se puede deshacer."
                                        class="text-error hover:text-error"
                                    >
                                        Eliminar
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-text-primary">
                            @if($busqueda !== '')
                                No se encontraron cajas para "{{ $busqueda }}".
                            @else
                                No hay cajas registradas.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal: Crear caja --}}
    <flux:modal wire:model="showCrearModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nueva Caja Entomológica</flux:heading>

            <flux:field>
                <flux:label>Código</flux:label>
                <flux:input wire:model="codigo" placeholder="CAJ-001" />
                <flux:error name="codigo" />
            </flux:field>

            <flux:field>
                <flux:label>Código RFID</flux:label>
                <flux:input wire:model="codigoRfid" placeholder="A1B2C3D4" maxlength="8" />
                <flux:description>8 caracteres hexadecimales del tag NFC (ej. A1B2C3D4)</flux:description>
                <flux:error name="codigoRfid" />
            </flux:field>

            <flux:field>
                <flux:label>Nombre <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="nombre" placeholder="Caja Lepidópteros A" />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>Familia taxonómica <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="familiaTaxonomicaId" placeholder="Cerambycidae" />
                <flux:error name="familiaTaxonomicaId" />
            </flux:field>

            <flux:field>
                <flux:label>Capacidad máxima <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input type="number" wire:model="capacidadMaxima" min="1" max="32767"
                    x-on:keydown="if(!/^\d$/.test($event.key) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'].includes($event.key)) $event.preventDefault()" />
                <flux:error name="capacidadMaxima" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showCrearModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="crearCaja" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="crearCaja">Crear Caja</span>
                    <span wire:loading wire:target="crearCaja">Creando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar caja --}}
    <flux:modal wire:model="showEditCajaModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar Caja</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="editNombre" placeholder="Caja Lepidópteros A" />
                <flux:error name="editNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Familia taxonómica <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="editFamiliaTaxonomicaId" placeholder="Cerambycidae" />
                <flux:error name="editFamiliaTaxonomicaId" />
            </flux:field>

            <flux:field>
                <flux:label>Capacidad máxima <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input type="number" wire:model="editCapacidadMaxima" min="1" max="32767"
                    x-on:keydown="if(!/^\d$/.test($event.key) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'].includes($event.key)) $event.preventDefault()" />
                <flux:error name="editCapacidadMaxima" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditCajaModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="actualizarCaja" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarCaja">Guardar</span>
                    <span wire:loading wire:target="actualizarCaja">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Registrar ingreso manual --}}
    <flux:modal wire:model="showIngresoModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Registrar Ingreso Manual</flux:heading>
            <p class="text-sm text-text-primary">Selecciona el gabinete y la ranura de destino.</p>

            <flux:field>
                <flux:label>Gabinete</flux:label>
                <flux:select wire:model.live="gabineteIdSeleccionado">
                    <option value="">Seleccionar gabinete...</option>
                    @foreach($gabinetes as $g)
                        <option value="{{ $g['id'] }}">{{ $g['label'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if($gabineteIdSeleccionado)
                <flux:field>
                    <flux:label>Ranura disponible</flux:label>
                    <flux:select wire:model.live="ranuraIdSeleccionada">
                        <option value="">Seleccionar ranura...</option>
                        @foreach($ranurasDisponibles as $r)
                            <option value="{{ $r['id'] }}">{{ $r['label'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="ranuraIdSeleccionada" />
                    @if(count($ranurasDisponibles) === 0)
                        <flux:description class="text-error">No hay ranuras disponibles en este gabinete.</flux:description>
                    @endif
                </flux:field>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showIngresoModal', false)">Cancelar</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="registrarIngreso"
                    wire:loading.attr="disabled"
                    :disabled="$ranuraIdSeleccionada === ''"
                >
                    <span wire:loading.remove wire:target="registrarIngreso">Confirmar Ingreso</span>
                    <span wire:loading wire:target="registrarIngreso">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
