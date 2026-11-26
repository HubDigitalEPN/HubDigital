<li wire:key="menu-lateral-{{ $clave }}" data-menu-nodo="{{ $clave }}" class="border-b border-border last:border-0">
    <div class="flex flex-wrap items-center gap-2 px-2 py-2 sm:flex-nowrap">
        @if($puedeAdministrar)
        <x-catalogo-menu-fila :id="'menu-lateral-acciones-'.hash('sha256', $clave)" :etiqueta="$opcion['texto']">
            <button type="button" role="menuitem" wire:click="editar(@js($clave))"><flux:icon name="pencil" />Editar</button>
            <button type="button" role="menuitem" wire:click="eliminar(@js($clave))" wire:confirm="¿Eliminar esta opción del menú?">
                <flux:icon name="trash" />Eliminar
            </button>
        </x-catalogo-menu-fila>
        @endif
        <div class="min-w-0 grow sm:basis-1/3">
            @if($hijos !== [])
                <button type="button" class="flex w-full items-center gap-1.5 text-left font-semibold text-blue-navy"
                    aria-controls="menu-hijos-{{ $clave }}" x-bind:aria-expanded="abiertos.includes(@js($clave)).toString()"
                    x-on:click="abiertos = abiertos.includes(@js($clave)) ? abiertos.filter(grupo => grupo !== @js($clave)) : [...abiertos, @js($clave)]">
                    <span aria-hidden="true" class="inline-flex shrink-0"
                          x-bind:class="abiertos.includes(@js($clave)) ? 'rotate-90' : ''">
                        <flux:icon name="chevron-right" />
                    </span>
                    <span>{{ $opcion['texto'] }}</span>
                </button>
            @else
                <span class="pl-5 {{ $opcion['ruta'] === null ? 'font-semibold' : '' }}">{{ $opcion['texto'] }}</span>
            @endif
        </div>
        <span class="text-xs font-medium text-text-secondary">{{ $opcion['visible'] ? 'Visible' : 'Oculta' }}</span>
        <span class="w-full text-xs text-text-secondary sm:w-auto sm:basis-1/2">
        {{ implode(', ', array_map(fn ($rol) => $etiquetasRoles[$rol] ?? $rol, $opcion['roles'])) }}
        </span>
    </div>
    @if($hijos !== [])
        <ul id="menu-hijos-{{ $clave }}" class="ml-5 border-s border-border pl-2 sm:ml-9" role="list" data-menu-hijos
            x-cloak x-show="abiertos.includes(@js($clave))">
            @foreach($hijos as $claveHija => $hija)
                @include('livewire.administracion.menu-lateral-nodo', ['clave' => $claveHija, 'opcion' => $hija, 'hijos' => []])
            @endforeach
        </ul>
    @endif
</li>
