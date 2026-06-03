<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">Cajas entomológicas</flux:heading>
        <flux:button icon="plus" variant="primary" class="w-full sm:w-auto" wire:click="$set('showCrearModal', true)">
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

    {{-- Tabla (desktop) --}}
    <div class="hidden md:block rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Código</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Taxonomía</th>
                        <th class="px-4 py-3 text-left font-medium text-white">RFID</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Nombre</th>
                        <th class="hidden lg:table-cell px-4 py-3 text-left font-medium text-white">Observación</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Especial</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($cajasFiltradas as $caja)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $caja['codigo'] }}</td>
                            <td class="px-4 py-3">
                                <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                                    :subfamilia="$caja['subfamilia']"
                                    :genero="$caja['genero']"
                                    :especie="$caja['especie']"
                                />
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-text-secondary">{{ $caja['codigoRfid'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $caja['nombre'] ?? '—' }}</td>
                            <td class="hidden lg:table-cell px-4 py-3 text-text-secondary max-w-xs">
                                @if($caja['observacion'])
                                    <flux:tooltip :content="$caja['observacion']">
                                        <span class="truncate block max-w-[180px] cursor-default">{{ $caja['observacion'] }}</span>
                                    </flux:tooltip>
                                @else
                                    <span class="text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-inventariogestioncoleccion::seguimiento-fisico.caja-estado-badge
                                    :estado="$caja['estado']"
                                />
                            </td>
                            <td class="px-4 py-3">
                                @if($caja['esEspecial'])
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-info text-white">Especial</span>
                                @else
                                    <span class="text-text-secondary text-xs">—</span>
                                @endif
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
                            <td colspan="9" class="px-4 py-8 text-center text-text-primary">
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
    </div>

    {{-- Tarjetas (móvil) --}}
    <div class="md:hidden space-y-3">
        @forelse($cajasFiltradas as $caja)
            <div class="rounded-lg border border-border bg-surface p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-medium text-text-primary">{{ $caja['codigo'] }}</span>
                    <x-inventariogestioncoleccion::seguimiento-fisico.caja-estado-badge
                        :estado="$caja['estado']"
                    />
                </div>
                <x-inventariogestioncoleccion::seguimiento-fisico.taxonomia-resumen
                    :subfamilia="$caja['subfamilia']"
                    :genero="$caja['genero']"
                    :especie="$caja['especie']"
                />
                <dl class="space-y-1.5 text-sm">
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="RFID">
                        <span class="font-mono text-xs">{{ $caja['codigoRfid'] }}</span>
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Nombre">
                        {{ $caja['nombre'] ?? '—' }}
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    @if($caja['observacion'])
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Observación">
                            {{ $caja['observacion'] }}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    @endif
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Especial">
                        @if($caja['esEspecial'])
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-info text-white">Especial</span>
                        @else
                            —
                        @endif
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                </dl>
                <div class="flex flex-wrap gap-2 pt-1">
                    @if($caja['estado'] === 'en_transito')
                        <flux:button
                            variant="ghost"
                            icon="arrow-down-tray"
                            wire:click="abrirIngresoModal('{{ $caja['id'] }}')"
                        >
                            Ingresar
                        </flux:button>
                    @endif
                    @if($caja['estado'] === 'en_gabinete')
                        <flux:button
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
                        variant="ghost"
                        icon="pencil"
                        wire:click="abrirEditCajaModal('{{ $caja['id'] }}')"
                    >
                        Editar
                    </flux:button>
                    @if($caja['estado'] === 'en_transito')
                        <flux:button
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
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-border p-8 text-center text-text-primary">
                @if($busqueda !== '')
                    No se encontraron cajas para "{{ $busqueda }}".
                @else
                    No hay cajas registradas.
                @endif
            </div>
        @endforelse
    </div>

    {{-- Modal: Crear caja --}}
    <flux:modal wire:model="showCrearModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nueva caja entomológica</flux:heading>

            <flux:field>
                <flux:label>Código</flux:label>
                <flux:input wire:model="codigo" placeholder="CAJ-001" />
                <flux:error name="codigo" />
            </flux:field>

            <flux:field
                x-data="{
                    nfcSupported: false,
                    scanning: false,
                    nfcError: null,
                    abort: null,
                    init() { this.nfcSupported = ('NDEFReader' in window); },
                    sanitize(e) {
                        e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-F]/g, '').slice(0, 8);
                    },
                    async scan() {
                        if (!this.nfcSupported || this.scanning) return;
                        this.nfcError = null;
                        this.scanning = true;
                        try {
                            this.abort = new AbortController();
                            const reader = new NDEFReader();
                            await reader.scan({ signal: this.abort.signal });
                            reader.onreadingerror = () => { this.nfcError = 'No se pudo leer la tarjeta. Reintenta.'; };
                            reader.onreading = (event) => {
                                const uid = (event.serialNumber || '').replace(/:/g, '').toUpperCase();
                                if (uid) { this.$wire.set('codigoRfid', uid); }
                                this.stop();
                            };
                        } catch (err) {
                            this.nfcError = (err && err.name === 'NotAllowedError')
                                ? 'Permiso de NFC denegado.'
                                : 'No se pudo iniciar el escaneo NFC.';
                            this.scanning = false;
                        }
                    },
                    stop() { if (this.abort) { this.abort.abort(); this.abort = null; } this.scanning = false; },
                }"
            >
                <flux:label>Código RFID</flux:label>
                <div class="flex items-start gap-2">
                    <flux:input
                        wire:model="codigoRfid"
                        x-on:input="sanitize($event)"
                        placeholder="A1B2C3D4"
                        maxlength="8"
                        class="flex-1 font-mono uppercase"
                    />
                    <flux:button
                        type="button"
                        x-show="nfcSupported"
                        x-on:click="scanning ? stop() : scan()"
                        x-bind:variant="scanning ? 'danger' : 'primary'"
                        icon="wifi"
                    >
                        <span x-text="scanning ? 'Acerca la tarjeta…' : 'Escanear NFC'"></span>
                    </flux:button>
                </div>
                <flux:description>8 caracteres hexadecimales del tag NFC (ej. A1B2C3D4)</flux:description>
                <template x-if="nfcError">
                    <p class="text-sm text-error" x-text="nfcError"></p>
                </template>
                <flux:error name="codigoRfid" />
            </flux:field>

            <flux:field>
                <flux:label>Nombre <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="nombre" placeholder="Caja Lepidópteros A" />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>Observación <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:textarea wire:model="observacion" rows="2" placeholder="Ej. Especímenes incautados - origen no determinado" />
                <flux:error name="observacion" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="esEspecial" id="crear-es-especial" />
                <flux:label for="crear-es-especial">Caja especial</flux:label>
                <flux:description>Marca si la caja tiene origen especial o no determinado.</flux:description>
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
            <flux:heading size="lg" class="text-text-primary">Editar caja</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:input wire:model="editNombre" placeholder="Caja Lepidópteros A" />
                <flux:error name="editNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Observación <flux:badge size="sm" color="zinc">Opcional</flux:badge></flux:label>
                <flux:textarea wire:model="editObservacion" rows="2" placeholder="Ej. Especímenes incautados - origen no determinado" />
                <flux:error name="editObservacion" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model="editEsEspecial" id="edit-es-especial" />
                <flux:label for="edit-es-especial">Caja especial</flux:label>
                <flux:description>Marca si la caja tiene origen especial o no determinado.</flux:description>
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
            <flux:heading size="lg" class="text-text-primary">Registrar ingreso manual</flux:heading>
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
                    <span wire:loading.remove wire:target="registrarIngreso">Confirmar ingreso</span>
                    <span wire:loading wire:target="registrarIngreso">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
