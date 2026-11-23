@php
    $esImportacion = $tabla === \App\Support\RegistrosAdministracionCatalogos::IMPORTACION;
    $esIpt = $tabla === \App\Support\RegistrosAdministracionCatalogos::IPT;
    $esDepositos = $tabla === \App\Support\RegistrosAdministracionCatalogos::DEPOSITOS;
@endphp
<div class="hub-workspace space-y-2 px-4 pb-4 pt-2" data-editor-matrices data-tablas-catalogos>
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-4">
        <div class="w-full shrink-0 sm:max-w-sm">
            <x-gestionprestamosrecepciones::lov-popup campo="tabla" label="Matriz"
                :opciones="array_map(fn ($matriz) => $matriz['nombre'], $matrices)"
                :valor="$tabla" accion="seleccionarTabla" :required="false" />
        </div>
        <p class="text-sm font-semibold">{{ $seleccion['uso'] }}</p>
    </div>

    @if(session('catalogo-actualizado'))
        <p role="status" class="text-sm font-semibold text-success">{{ session('catalogo-actualizado') }}</p>
    @endif

    <section aria-label="{{ $seleccion['nombre'] }}" data-tabla-catalogo="{{ $tabla }}" wire:key="matriz-{{ $tabla }}">
        <x-interactive-grid :rows="$filas" :label="$seleccion['nombre']" :columns="['Acciones', ...array_values($columnas)]"
            scroll-only data-grid-scroll-only data-registros-catalogos data-grid-resizable>
            <x-slot:toolbarActions>
                @if($puedeCrear)
                    <flux:button class="hub-ig-new-record" variant="primary" icon="plus" wire:click="nuevo" x-on:click="closeMenu(false)">Nueva columna</flux:button>
                @endif
            </x-slot:toolbarActions>
            <x-slot:menuActions>
                @if($esImportacion)
                    <a href="{{ route('inventario.taxonomia.importar.plantilla') }}" role="menuitem" tabindex="-1" x-on:click="closeMenu(false)">
                        <flux:icon name="arrow-down-tray" />Descargar plantilla
                    </a>
                @elseif($esDepositos)
                    <button type="button" role="menuitem" tabindex="-1" wire:click="descargarPlantillaDepositos" x-on:click="closeMenu(false)">
                        <flux:icon name="arrow-down-tray" />Descargar plantilla
                    </button>
                @endif
                @if($esImportacion || $esDepositos)
                    <button type="button" role="menuitem" tabindex="-1" wire:click="mostrarCarga" x-on:click="closeMenu(false)">
                        <flux:icon name="arrow-up-tray" />Subir plantilla modificada
                    </button>
                @elseif($esIpt)
                    <a href="{{ route('inventario.taxonomia.dataset.config') }}" wire:navigate role="menuitem" tabindex="-1" x-on:click="closeMenu(false)">
                        <flux:icon name="cog-6-tooth" />Configurar el conjunto de datos
                    </a>
                    <button type="button" role="menuitem" tabindex="-1" wire:click="solicitarRestaurar" x-on:click="closeMenu(false)">
                        <flux:icon name="arrow-uturn-left" />Restaurar ejemplo EPN
                    </button>
                @endif
            </x-slot:menuActions>
            <thead>
                <tr>
                    <th scope="col" data-grid-action data-grid-fixed="56" data-grid-label="Acciones"><span class="sr-only">Acciones</span></th>
                    @foreach($columnas as $campo => $etiqueta)
                        @if($campo === 'orden')
                            <x-grid-heading :field="$campo" data-grid-fixed="88">{{ $etiqueta }}</x-grid-heading>
                        @else
                            <x-grid-heading :field="$campo" resizable>{{ $etiqueta }}</x-grid-heading>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    @php($identificador = 'columna-'.$tabla.'-'.hash('sha256', $fila->campo))
                    <tr wire:key="{{ $identificador }}" data-catalogo-fila="{{ $tabla }}">
                        <td data-grid-action>
                            <x-catalogo-menu-fila :id="$identificador" :etiqueta="$fila->cabecera ?? $fila->campo">
                                <button type="button" role="menuitem" wire:click="editarColumna(@js($fila->campo))"><flux:icon name="pencil" />Editar</button>
                                @if((!$esImportacion || auth()->user()->esAdministrador()) && !in_array($fila->campo, $columnasProtegidas, true))
                                    <button type="button" role="menuitem" wire:click="solicitarEliminar(@js($fila->campo))"><flux:icon name="trash" />Eliminar</button>
                                @endif
                            </x-catalogo-menu-fila>
                        </td>
                        @foreach($columnas as $campo => $etiqueta)
                            <td>
                                @if($campo === 'descripcion' && filled($fila->{$campo}))
                                    <span class="line-clamp-2" title="{{ $fila->{$campo} }}">{{ $fila->{$campo} }}</span>
                                @else
                                    {{ filled($fila->{$campo}) ? $fila->{$campo} : '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columnas) + 1 }}">No hay columnas para esta búsqueda.</td></tr>
                @endforelse
            </tbody>
        </x-interactive-grid>
    </section>

    <flux:modal name="editar-columna-matriz" class="w-full max-w-4xl">
        @if($modo === 'columna')
            <form wire:submit="guardarColumna" class="space-y-4" x-data
                x-init="$nextTick(() => $el.querySelector('[autofocus]')?.focus({ preventScroll: true }))">
                <flux:heading size="lg">{{ $campoEdicion === null ? 'Nueva columna' : 'Editar columna' }}</flux:heading>
                <flux:error name="matriz" />
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($columnas as $campo => $etiqueta)
                        @if($campo === 'personalizada')
                            @continue
                        @endif
                        <div @class(['sm:col-span-2' => in_array($campo, ['descripcion', 'alias'], true)])>
                            @if($campo === 'campo' && $campoEdicion !== null)
                                <flux:input wire:model="datos.campo" :label="$etiqueta" readonly />
                            @elseif(isset($opciones[$campo]))
                                <flux:select wire:model="datos.{{ $campo }}" :label="$etiqueta">
                                    @if($campo === 'requisito_guia')
                                        <option value="">{{ $esImportacion ? 'Usar requisito de la plantilla de referencia' : 'Usar requisito de la columna' }}</option>
                                    @elseif($campo === 'campo' && !array_key_exists('', $opciones[$campo]))
                                        <option value="">Selecciona un campo</option>
                                    @endif
                                    @foreach($opciones[$campo] as $valor => $nombre)
                                        <option value="{{ $valor }}">{{ $nombre }}</option>
                                    @endforeach
                                </flux:select>
                            @elseif($campo === 'orden')
                                <flux:input wire:model="datos.orden" type="number" min="1" :label="$etiqueta" required autofocus />
                            @elseif($campo === 'ancho')
                                <flux:input wire:model="datos.ancho" type="number" min="5" max="80" :label="$etiqueta" required />
                            @elseif(in_array($campo, ['descripcion', 'alias'], true))
                                <flux:textarea wire:model="datos.{{ $campo }}" rows="2"
                                    :label="$campo === 'alias' && $esImportacion ? 'Otros nombres (uno por línea)' : $etiqueta"
                                    :placeholder="$campo === 'alias' && !$esImportacion ? 'Separados por comas' : null" />
                            @else
                                <flux:input wire:model="datos.{{ $campo }}" :label="$etiqueta"
                                    :required="in_array($campo, ['campo', 'cabecera'], true)" />
                            @endif
                            <flux:error :name="'datos.'.$campo" />
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Guardar</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="confirmar-cambio-matriz" class="w-full max-w-md">
        @if(in_array($modo, ['eliminar', 'restaurar'], true))
            <form wire:submit="confirmarCambio" class="space-y-4">
                <flux:heading size="lg">{{ $modo === 'eliminar' ? 'Eliminar columna' : 'Restaurar ejemplo EPN' }}</flux:heading>
                <p>{{ $modo === 'eliminar' ? 'Se quitará «'.$campoEdicion.'» de la matriz seleccionada.' : 'El perfil de 69 columnas reemplazará el formato IPT actual.' }}</p>
                <flux:error name="matriz" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" :variant="$modo === 'eliminar' ? 'danger' : 'primary'">{{ $modo === 'eliminar' ? 'Eliminar' : 'Restaurar' }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="cargar-plantilla-matriz" class="w-full max-w-lg">
        @if($modo === 'carga')
            <form wire:submit="subirPlantilla" class="space-y-4">
                <flux:heading size="lg">Subir plantilla modificada</flux:heading>
                @if($esDepositos)
                    <p>Se aplicarán las cabeceras, el orden, las descripciones y los requisitos del Excel. Las filas de ejemplo se omiten.</p>
                @else
                    <p>La plantilla y sus reglas se aplicarán a las próximas importaciones.</p>
                @endif
                <x-editor-foto-input id="matriz-plantilla-excel" modelo="archivo" etiqueta="Plantilla modificada (.xlsx)" accept=".xlsx"
                    accion="Elegir Excel" sin-archivo="Ningún archivo seleccionado" />
                <flux:error name="archivo" />
                @if($vistaPlantilla !== [])
                    <ul role="status" class="list-disc space-y-1 pl-5 text-sm">
                        <li>Columnas visibles en la nueva plantilla: {{ $vistaPlantilla['visibles'] }}</li>
                        <li>Campos nuevos: {{ $vistaPlantilla['nuevas'] }}</li>
                        <li>Columnas que se ocultarán y seguirán disponibles para archivos anteriores: {{ $vistaPlantilla['ocultas'] }}</li>
                    </ul>
                @endif
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button>Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="archivo,subirPlantilla">
                        {{ $esDepositos ? ($vistaPlantilla === [] ? 'Revisar Excel' : 'Aplicar plantilla') : 'Subir plantilla' }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
