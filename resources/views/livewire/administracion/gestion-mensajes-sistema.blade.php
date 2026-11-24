<div class="hub-workspace space-y-2 px-4 pb-4 pt-2">
    @if(session('mensajes-guardados'))
        <p role="status" class="text-sm font-semibold text-success">{{ session('mensajes-guardados') }}</p>
    @endif
    <x-grid-frame label="Mensajes del Sistema" :allow-download="false" :continuous-window="$gridVentana" data-grid-resizable>
        <x-slot:search>
            <form class="hub-ig-search" wire:submit="buscar">
                <flux:icon name="magnifying-glass" />
                <input type="search" wire:model="busqueda" maxlength="200" aria-label="Buscar mensajes" placeholder="Buscar mensajes" />
                <button type="submit" class="hub-ig-button hub-ig-search-go">Ir</button>
            </form>
        </x-slot:search>
        <x-slot:toolbarActions>
            <flux:button class="hub-ig-new-record" variant="primary" icon="plus" wire:click="nuevo">Nuevo mensaje</flux:button>
        </x-slot:toolbarActions>
        <x-slot:reset>
            <button type="button" class="hub-ig-button hub-ig-reset" wire:click="restablecer" x-on:click="reset()">
                <flux:icon name="arrow-uturn-left" /> Restablecer vista
            </button>
        </x-slot:reset>
        <table class="hub-ig-movable">
            <thead>
                <tr>
                    <th scope="col" data-grid-action data-grid-fixed="56"><span class="sr-only">Acciones</span></th>
                    @foreach(['uso' => 'Pantalla y uso', 'clave' => 'Clave', 'contenido' => 'Mensaje mostrado', 'estado' => 'Estado'] as $campo => $etiqueta)
                        <th scope="col" data-grid-field="{{ $campo }}">
                            {{ $etiqueta }}
                            <x-grid-resize :field="$campo" :label="$etiqueta" />
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $id => $fila)
                    @php
                        $menuId = 'acciones-mensaje-'.hash('sha256', $id);
                        $literal = str_starts_with($id, 'literal.');
                    @endphp
                    <tr wire:key="{{ $menuId }}">
                        <td data-grid-action>
                            <x-catalogo-menu-fila :id="$menuId" :etiqueta="$literal ? \Illuminate\Support\Str::limit($fila['original'], 75) : $id">
                                <button type="button" role="menuitem" wire:click="editar(@js($id))"><flux:icon name="pencil" />Editar</button>
                                @if($fila['estado'] === 'Personalizado')
                                    <button type="button" role="menuitem" wire:click="eliminar(@js($id))" wire:confirm="¿Restaurar el mensaje original?">
                                        <flux:icon name="arrow-uturn-left" />Restaurar original
                                    </button>
                                @elseif($fila['estado'] === 'Adicional')
                                    <button type="button" role="menuitem" wire:click="eliminar(@js($id))" wire:confirm="¿Eliminar este mensaje?">
                                        <flux:icon name="trash" />Eliminar
                                    </button>
                                @endif
                            </x-catalogo-menu-fila>
                        </td>
                        <td>{{ $fila['uso'] }}</td>
                        <td><code class="break-all">{{ $literal ? '—' : $id }}</code></td>
                        <td data-copy-preserve class="whitespace-pre-wrap break-words">{{ $fila['contenido'] }}</td>
                        <td>{{ $fila['estado'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay mensajes que coincidan con la búsqueda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-grid-frame>

    <flux:modal name="editor-mensaje-sistema" class="w-full max-w-2xl">
        <form wire:submit="guardar" class="space-y-4" data-copy-preserve>
            <flux:heading size="lg">{{ $editando === null ? 'Nuevo mensaje' : 'Editar mensaje' }}</flux:heading>
            @if($editando === null)
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <flux:textarea label="Texto original" wire:model="textoOriginal" required maxlength="5000" rows="3" />
                    </div>
                    <x-catalogo-ayuda campo="original-mensaje-sistema" :detalle="[
                        'titulo' => 'Texto original', 'descripcion' => 'Copia el mensaje tal como aparece. El nuevo texto se aplicará donde coincida el original.',
                        'ejemplo' => 'La solicitud {numero} fue enviada.', 'regla' => 'Para valores que cambian, escribe un marcador entre llaves. Si el mensaje está en la lista, edítalo desde su fila.',
                    ]" />
                </div>
            @elseif(!str_starts_with($editando, 'literal.'))
                <p class="text-sm text-text-secondary"><code class="break-all">{{ $editando }}</code></p>
            @endif
            @if($original !== null)<p class="text-sm text-text-secondary">Texto original<br>{{ $original }}</p>@endif
            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                        <flux:textarea label="Mensaje" wire:model="contenido" required maxlength="5000" rows="4" />
                </div>
                <x-catalogo-ayuda campo="contenido-mensaje-sistema" :detalle="[
                    'titulo' => 'Mensaje', 'descripcion' => 'Texto que verá el usuario en el proceso correspondiente.',
                    'ejemplo' => 'Valida primero este archivo y revisa el resumen.', 'regla' => 'Escribe texto plano y conserva los marcadores indicados.',
                ]" />
            </div>
            @if($marcadores !== [])
                <p class="text-sm text-text-secondary">Conserva estos marcadores en el mensaje. {{ implode(', ', $marcadores) }}</p>
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
