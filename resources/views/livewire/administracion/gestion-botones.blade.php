<div class="hub-workspace space-y-6 p-4 sm:p-6" data-testid="admin-buttons-page">
    <a href="{{ route('admin.centro') }}" wire:navigate class="text-sm text-science-blue hover:underline">Centro de administración</a>
    <header><h1 class="hub-page-title">Botones y permisos por perfil</h1><p class="mt-2 text-sm text-text-secondary">Define qué botones puede ver y usar cada perfil. Las personas con varios perfiles pueden cambiar entre los que tienen asignados.</p></header>
    <p class="rounded-lg border border-border bg-surface p-4 text-sm text-text-secondary">El catálogo completo contiene información interna y queda reservado a Administración, Curador y Ayudante de curador. Las descargas externas mantienen las restricciones de divulgación y el permiso general de descargas de autoservicio.</p>
    @if(session('botones-guardados'))<p role="status" class="text-bio-green">{{ session('botones-guardados') }}</p>@endif
    @error('permisos')<p role="alert" class="text-error">{{ $message }}</p>@enderror
    <form wire:submit="guardar" class="space-y-5">
        <div class="overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="w-full min-w-[65rem] text-sm">
                <caption class="sr-only">Permisos para ver y usar botones según el perfil activo</caption>
                <thead><tr class="border-b border-border bg-bg-main"><th scope="col" class="p-4 text-left">Botón</th>@foreach($roles as $rol)<th scope="col" class="p-3 text-left">{{ $rol->etiqueta() }}</th>@endforeach</tr></thead>
                <tbody>@foreach($botones as $clave => $boton)<tr class="border-b border-border last:border-0"><th scope="row" class="p-4 text-left"><span class="block font-normal text-xs text-text-secondary">{{ $boton['grupo'] }}</span>{{ $boton['nombre'] }}</th>
                    @foreach($roles as $rol)<td class="p-3 align-top">
                        @if(in_array($rol->value, $boton['elegibles'], true))
                            @foreach(['ver' => 'Ver', 'usar' => 'Usar'] as $accion => $etiqueta)
                                <label class="flex min-h-11 items-center gap-2"><input type="checkbox" wire:model="permisos.{{ str_replace('.', '_', $clave) }}.{{ $rol->value }}.{{ $accion }}" aria-label="{{ $etiqueta }} {{ $boton['nombre'] }}: {{ $rol->etiqueta() }}" class="size-4 rounded border-border text-science-blue" />{{ $etiqueta }}</label>
                            @endforeach
                        @else<span class="text-text-secondary">Reservado al equipo</span>@endif
                    </td>@endforeach
                </tr>@endforeach</tbody>
            </table>
        </div>
        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Guardar permisos</flux:button>
    </form>
</div>
