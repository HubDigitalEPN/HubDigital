<div class="hub-workspace space-y-2 px-4 pb-4 pt-2" data-tablas-catalogos>
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-4">
        <div class="w-full shrink-0 sm:max-w-sm">
            <x-gestionprestamosrecepciones::lov-popup campo="tabla" label="Tabla"
                :opciones="array_map(fn ($definicion) => $definicion[0], $tablas)"
                :valor="$tabla" accion="seleccionarTabla" :required="false" />
        </div>
        <div class="min-w-0 text-sm">
            <p><code class="break-words">{{ $definicion[1] }}</code> (<strong>{{ $definicion['donde'] }} - {{ $definicion['uso'] }}</strong>)</p>
            @if($unica)<p class="text-text-secondary">Admite un solo registro</p>@endif
        </div>
    </div>

    @if(session('catalogo-actualizado'))
        <p role="status" class="text-sm font-semibold text-success">{{ session('catalogo-actualizado') }}</p>
    @endif
    <section aria-label="{{ $definicion[0] }}" data-tabla-catalogo="{{ $tabla }}" wire:key="tabla-administrada-{{ $tabla }}">
        <x-interactive-grid :rows="$filas" :label="$definicion[0]" :columns="['Acciones', 'N.º', ...array_keys($columnas)]"
            scroll-only data-registros-catalogos data-grid-resizable>
            <x-slot:toolbarActions>
                @if($puedeCrear)
                    <flux:button class="hub-ig-new-record" variant="primary" icon="plus" wire:click="nuevo" x-on:click="closeMenu(false)">Nuevo registro</flux:button>
                @endif
            </x-slot:toolbarActions>
            <thead>
                <tr>
                    <th scope="col" data-grid-action data-grid-fixed="56" data-grid-label="Acciones"><span class="sr-only">Acciones</span></th>
                    <th scope="col" data-grid-fixed="64" class="text-center">N.º</th>
                    @foreach($columnas as $nombre => $columna)
                        @if($correo && $nombre === 'password')
                            <x-grid-heading>{{ $nombre }}</x-grid-heading>
                        @else
                            <x-grid-heading :field="$nombre" resizable>{{ $nombre }}</x-grid-heading>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    @php
                        $id = (string) $fila->{$definicion[2]};
                        $ordinal = $filas->firstItem() + $loop->index;
                        $menuId = 'catalogo-fila-'.$tabla.'-'.hash('sha256', $id);
                    @endphp
                    <tr wire:key="{{ $menuId }}" data-catalogo-fila="{{ $tabla }}">
                        <td data-grid-action>
                            <x-catalogo-menu-fila :id="$menuId" :etiqueta="'la fila '.$ordinal">
                                <button type="button" role="menuitem" wire:click="editar(@js($id))"><flux:icon name="pencil" />Editar</button>
                                <button type="button" role="menuitem" wire:click="solicitarEliminar(@js($id))"><flux:icon name="trash" />Eliminar</button>
                            </x-catalogo-menu-fila>
                        </td>
                        <td data-ordinal="{{ $ordinal }}" class="tabular-nums">{{ $ordinal }}</td>
                        @foreach($columnas as $nombre => $columna)
                            @php
                                $valor = $fila->{$nombre};
                            @endphp
                            <td>{{ is_bool($valor) ? ($valor ? 'Sí' : 'No') : ($valor ?? '—') }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columnas) + 2 }}">No hay registros para esta búsqueda.</td></tr>
                @endforelse
            </tbody>
        </x-interactive-grid>
    </section>

    <flux:modal name="registro-tabla-catalogo" class="w-full max-w-5xl">
        <form x-data="{ clave: '' }" x-on:submit.prevent="const dato = clave; clave = ''; $wire.guardar(dato)" class="space-y-4">
            <flux:heading size="lg">{{ $editando === null ? 'Nuevo registro' : 'Editar registro' }}</flux:heading>
            <flux:error name="registro" />
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($campos as $nombre => $columna)
                    @php
                        $obligatorio = !$columna['nullable'] && ($editando !== null || $columna['default'] === null);
                        $booleano = $columna['type_name'] === 'bool';
                        $valores = $booleano ? ['1' => 'Sí', '0' => 'No'] : ($opciones[$nombre] ?? null);
                        $predeterminado = $editando === null && $columna['default'] !== null;
                    @endphp
                    @if(isset($relaciones[$nombre]))
                        <x-gestionprestamosrecepciones::lov-popup :campo="'datos.'.$nombre" :label="$nombre"
                            :valor="(string) ($datos[$nombre] ?? '')" :opciones="$relaciones[$nombre]"
                            :contexto="$tabla.'-'.($editando ?? 'nuevo')" :required="$obligatorio"
                            busqueda-remota="buscarOpcionesRelacion" busqueda-por-campo />
                    @elseif($valores !== null)
                        <flux:select wire:model="datos.{{ $nombre }}" :label="$nombre" :required="$obligatorio">
                            @if($columna['nullable'] || (!$booleano && $predeterminado))
                                <option value="">{{ $predeterminado ? 'Predeterminado' : 'Sin valor' }}</option>
                            @elseif(!isset($datos[$nombre]))
                                <option value="" disabled>Selecciona un valor</option>
                            @endif
                            @foreach($valores as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </flux:select>
                    @elseif(in_array($columna['type_name'], ['json', 'jsonb', 'text'], true))
                        <flux:textarea wire:model="datos.{{ $nombre }}" :label="$nombre" :required="$obligatorio" rows="3" />
                    @else
                        <flux:input wire:model="datos.{{ $nombre }}" :label="$nombre" :required="$obligatorio" />
                    @endif
                    <flux:error :name="$nombre" />
                @endforeach
                @if($correo)
                    <flux:input type="password" x-model="clave" label="password" autocomplete="new-password"
                        :placeholder="$editando === null ? null : 'Deja el campo vacío para conservar la contraseña'" :required="$editando === null" />
                    <flux:error name="contraseña" />
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="eliminar-registro-tabla" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Eliminar registro</flux:heading>
            <p>Se eliminará el registro seleccionado de {{ $definicion[1] }}.</p>
            <flux:error name="registro" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="eliminar" wire:loading.attr="disabled" wire:target="eliminar">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
