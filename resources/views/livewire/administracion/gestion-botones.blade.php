<div class="hub-workspace space-y-3 px-4 pb-4 pt-2" data-testid="admin-buttons-page">
    <p class="text-sm text-text-secondary">Define qué botones puede ver y usar cada perfil. Las personas con varios perfiles pueden cambiar entre los que tienen asignados.</p>
    <p class="rounded border border-border bg-surface px-3 py-2 text-sm text-text-secondary">El catálogo completo contiene información interna y queda reservado a Administrador-Curador y Técnico. Las descargas externas mantienen las restricciones de divulgación y el permiso general de descargas de autoservicio.</p>
    @if(session('botones-guardados'))<p role="status" class="text-bio-green">{{ session('botones-guardados') }}</p>@endif
    @error('permisos')<p role="alert" class="text-error">{{ $message }}</p>@enderror
    <form wire:submit="guardar">
        <x-grid-frame label="Permisos de botones por perfil">
            <x-slot:toolbarActions>
                @if(auth()->user()->esAdministrador())
                <flux:button type="submit" class="hub-ig-new-record" variant="primary" wire:loading.attr="disabled" x-on:click="closeMenu(false)">Guardar permisos</flux:button>
                @endif
            </x-slot:toolbarActions>
            <table class="w-full min-w-[65rem] text-sm">
                <caption class="sr-only">Permisos para ver y usar botones según el perfil activo</caption>
                <thead><tr class="border-b border-border bg-bg-main"><th scope="col" class="p-4 text-left">Botón</th>@foreach($roles as $rol)<th scope="col" class="p-3 text-left">{{ $rol->etiqueta() }}</th>@endforeach</tr></thead>
                <tbody>@foreach($botones as $clave => $boton)<tr class="border-b border-border last:border-0"><th scope="row" class="p-4 text-left"><span class="block font-normal text-xs text-text-secondary">{{ $boton['grupo'] }}</span>{{ $boton['nombre'] }}</th>
                    @foreach($roles as $rol)<td class="p-3 align-top">
                        @if($rol === \App\Enums\RolUsuario::CURADOR)
                            <span class="text-text-secondary">Acceso completo</span>
                        @elseif(in_array($rol->value, $boton['elegibles'], true))
                            @foreach(['ver' => 'Ver', 'usar' => 'Usar'] as $accion => $etiqueta)
                                <label class="flex min-h-11 items-center gap-2"><input type="checkbox" wire:model="permisos.{{ str_replace('.', '_', $clave) }}.{{ $rol->value }}.{{ $accion }}" aria-label="{{ $etiqueta }} {{ $boton['nombre'] }}: {{ $rol->etiqueta() }}" @disabled(!auth()->user()->esAdministrador()) class="size-4 rounded border-border text-science-blue" />{{ $etiqueta }}</label>
                            @endforeach
                        @else<span class="text-text-secondary">Reservado al equipo</span>@endif
                    </td>@endforeach
                </tr>@endforeach</tbody>
            </table>
        </x-grid-frame>
    </form>
</div>
