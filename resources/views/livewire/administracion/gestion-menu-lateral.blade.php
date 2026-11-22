<div class="hub-workspace space-y-3 px-4 pb-4 pt-2">
    <flux:error name="eliminar" />
    <section class="hub-ig" data-menu-lateral-arbol aria-label="Árbol del menú lateral" x-data="{ abiertos: @js($gruposAbiertos), mostrarAcciones: false }">
        <div class="hub-ig-toolbar">
            <div class="w-full sm:max-w-sm" data-hub-apex-toolbar-field>
                <x-gestionprestamosrecepciones::lov-popup campo="rolVista" label="Rol" :opciones="$rolesVista"
                    :valor="$rolVista" accion="seleccionarRol" :required="false" />
            </div>
            <div class="hub-ig-actions hub-ig-action-pair" x-on:click.outside="mostrarAcciones = false"
                x-on:keydown.escape.stop="if (mostrarAcciones) { mostrarAcciones = false; $refs.acciones.focus() }">
                <button type="button" class="hub-ig-button" x-ref="acciones" x-on:click="mostrarAcciones = !mostrarAcciones"
                    aria-controls="acciones-arbol-menu" x-bind:aria-expanded="mostrarAcciones.toString()">Acciones <flux:icon name="chevron-down" /></button>
                <flux:button class="hub-ig-new-record" variant="primary" icon="plus" wire:click="nueva"
                    x-on:click="mostrarAcciones = false">Nueva opción</flux:button>
                <div id="acciones-arbol-menu" class="hub-ig-menu" x-cloak x-show="mostrarAcciones">
                    <button type="button" x-on:click="abiertos = @js(array_keys($hijosPorGrupo)); mostrarAcciones = false; $refs.acciones.focus()">Expandir todos</button>
                    <button type="button" x-on:click="abiertos = []; mostrarAcciones = false; $refs.acciones.focus()">Contraer todos</button>
                </div>
            </div>
        </div>

        <ul class="px-2 py-2 sm:px-3" role="list" data-menu-raices>
            @foreach($raices as $clave => $opcion)
                @include('livewire.administracion.menu-lateral-nodo', ['clave' => $clave, 'opcion' => $opcion, 'hijos' => $hijosPorGrupo[$clave] ?? []])
            @endforeach
        </ul>
    </section>

    <flux:modal name="editor-menu-lateral" class="w-full max-w-2xl">
        <form wire:submit="guardar" class="space-y-4">
            <flux:heading size="lg">{{ $edicion ? 'Editar opción' : 'Nueva opción' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="texto" label="Texto en el menú" maxlength="80" required />
                <flux:select wire:model.live="tipo" label="Tipo" :disabled="$edicion !== null">
                    <option value="enlace">Opción</option>
                    <option value="grupo">Grupo</option>
                </flux:select>
                @if($tipo === 'enlace')
                    <flux:select wire:model.live="ruta" label="Destino" required>
                        <option value="">Selecciona una pantalla</option>
                        @foreach($destinos as $nombreRuta => $datosRuta)
                            <option value="{{ $nombreRuta }}">{{ $datosRuta['texto'] }}{{ $datosRuta['grupo'] && isset($grupos[$datosRuta['grupo']]) ? ' · '.$grupos[$datosRuta['grupo']]['texto'] : '' }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="grupo" label="Grupo">
                        <option value="">Menú principal</option>
                        @foreach($grupos as $claveGrupo => $grupoOpcion)
                            <option value="{{ $claveGrupo }}">{{ $grupoOpcion['texto'] }}</option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
            @if($rolesDisponibles !== [])
                <fieldset class="space-y-2" wire:key="roles-menu-{{ $tipo }}-{{ $ruta }}">
                    <legend class="font-semibold">Roles con acceso a la opción</legend>
                    <div class="flex flex-wrap gap-4">
                        @foreach($rolesDisponibles as $rol)
                            <flux:checkbox wire:model="roles" :value="$rol->value" :label="$rol->etiqueta()" />
                        @endforeach
                    </div>
                    <flux:error name="roles" />
                    <flux:error name="roles.*" />
                </fieldset>
            @endif
            <flux:checkbox wire:model="visible" label="Mostrar en el menú" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
