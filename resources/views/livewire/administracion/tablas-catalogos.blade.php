<div class="hub-workspace space-y-4 p-4 sm:p-6" data-tablas-catalogos>
    <header class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="hub-page-title">{{ $gestionaMatrices ? 'Matrices' : 'Tablas de catálogos' }}</h1>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nuevo registro</flux:button>
    </header>

    <div class="w-full sm:max-w-sm">
        <flux:select wire:model.live="tabla" id="selector-tabla-catalogo" :label="$gestionaMatrices ? 'Matriz' : 'Catálogo'">
            <option value="">Todas las tablas</option>
            @foreach($catalogos as $id => $catalogo)
                <option value="{{ $id }}">{{ $catalogo['nombre'] }}</option>
            @endforeach
        </flux:select>
    </div>
    @if(session('catalogo-actualizado'))
        <p role="status" class="text-sm font-semibold text-success">{{ session('catalogo-actualizado') }}</p>
    @endif

    <section aria-label="Registros administrables" data-tabla-catalogo="{{ $tabla }}">
        <x-interactive-grid :rows="$filas" :label="$gestionaMatrices ? 'Registros de matrices' : 'Registros de catálogos'"
            :columns="['Registro', 'Tabla', 'Detalle', 'Estado', 'Acciones']" scroll-only data-registros-catalogos>
                    <thead>
                        <tr>
                            <x-grid-heading field="nombre">Registro</x-grid-heading>
                            <x-grid-heading field="catalogo">Tabla</x-grid-heading>
                            <x-grid-heading field="detalle">Detalle</x-grid-heading>
                            <x-grid-heading field="estado">Estado</x-grid-heading>
                            <x-grid-heading>Acciones</x-grid-heading>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($filas as $fila)
                            @php
                                $acciones = $catalogos[$fila->tabla];
                            @endphp
                            <tr wire:key="catalogo-{{ $fila->tabla }}-{{ $fila->id }}" data-catalogo-fila="{{ $fila->tabla }}">
                                <th scope="row">{{ $fila->nombre }}</th>
                                <td>{{ $fila->catalogo }}</td>
                                <td>{{ $fila->detalle ?: '—' }}</td>
                                <td>{{ $fila->estado ?: '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <button type="button" class="hub-ig-button" aria-label="Editar {{ $fila->nombre }}" wire:click="abrirRegistro(@js($fila->tabla), @js($fila->id))">{{ isset($acciones['editar']) ? 'Editar' : 'Configurar' }}</button>
                                        @if(isset($acciones['estado']) && ($fila->tabla === 'instituciones' ? $fila->estado_id : $fila->estado !== 'Inactivo' || $fila->tabla !== 'taxones'))
                                            <button type="button" class="hub-ig-button" wire:click="abrirRegistro(@js($fila->tabla), @js($fila->id), 'estado')" @if($fila->tabla === 'taxones') wire:confirm="¿Desactivar este taxón?" @endif>{{ in_array($fila->estado, ['Inactivo', 'Desactivado'], true) ? 'Activar' : 'Desactivar' }}</button>
                                        @endif
                                        @if(isset($acciones['acta']) && $fila->acta_id)
                                            <button type="button" class="hub-ig-button" wire:click="abrirRegistro(@js($fila->tabla), @js($fila->id), 'acta')">Generar acta</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay registros para esta búsqueda.</td></tr>
                        @endforelse
                    </tbody>
        </x-interactive-grid>
    </section>

    <flux:modal name="catalogo-alta" class="w-full max-w-md">
        <form wire:submit="abrirAlta" class="space-y-4">
            <flux:heading size="lg">Nuevo registro</flux:heading>
            <flux:select wire:model="tablaNueva" label="Tabla para el nuevo registro">
                @foreach($catalogos as $id => $catalogo)
                    @if(isset($catalogo['nuevo']))
                        <option value="{{ $id }}">{{ $catalogo['nombre'] }}</option>
                    @endif
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Continuar</flux:button>
            </div>
        </form>
    </flux:modal>

    @if($editor)
        @php
            $configuracion = $catalogos[$editor['tabla']];
            $parametros = $editor['accion'] === null ? [] : [
                'soloEditorCatalogo' => true,
                'accionCatalogo' => $editor['accion'],
                'argumentoCatalogo' => $editor['argumento'],
            ];
        @endphp
        @if($configuracion['panel'] ?? false)
            <flux:modal name="catalogo-configuracion" class="w-full max-w-5xl" wire:key="panel-catalogo-{{ $versionEditor }}">
                @livewire($configuracion['componente'], $parametros, key('editor-catalogo-'.$versionEditor))
                <div class="flex justify-end">
                    <flux:modal.close><flux:button wire:click="cerrarEditor">Cerrar</flux:button></flux:modal.close>
                </div>
            </flux:modal>
        @else
            @livewire($configuracion['componente'], $parametros, key('editor-catalogo-'.$versionEditor))
        @endif
    @endif
</div>
