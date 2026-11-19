<div data-copy-preserve class="hub-workspace mx-auto max-w-7xl space-y-4 p-4">

    @if(session('textos-sistema'))<p data-hub-feedback="success" role="status">{{ session('textos-sistema') }}</p>@endif
    <div class="hub-panel grid items-start gap-4 p-4 sm:grid-cols-2" data-filtros-textos-sistema>
        <flux:input type="search" label="Buscar texto" wire:model.live.debounce.400ms="busqueda"/>
        <flux:select label="Pantalla donde aparece" wire:model.live="pantalla"><option value="">Todas las pantallas</option>@foreach($pantallas as $nombre)<option value="{{ $nombre }}">{{ $nombre }}</option>@endforeach</flux:select>
    </div>
    @if($original !== '')
    <form wire:submit="guardar" class="hub-panel space-y-3 border-2 border-science-blue p-4">
        <h3 class="font-semibold">Estás cambiando este texto en:</h3><ul class="space-y-2 text-sm">@forelse($ubicaciones as $ubicacion)<li><strong>{{ $ubicacion['pantalla'] }}</strong> · {{ $ubicacion['campo'] }}</li>@empty<li>Texto adicional: se sustituirá donde aparezca exactamente. Su ubicación no está identificada en el inventario.</li>@endforelse</ul>
        <p class="whitespace-pre-line"><strong>Original:</strong> {{ $original }}</p>
        <flux:textarea wire:model="contenido" label="Texto mostrado" rows="4" maxlength="5000"/>
        <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Guardar texto</flux:button><flux:button type="button" wire:click="restaurar">Restaurar original</flux:button><flux:button type="button" wire:click="cancelar">Cancelar</flux:button></div>
    </form>
    @endif
    <div class="hub-panel"><x-grid-frame label="Textos de todas las pantallas"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Ubicación y campo</th><th class="p-3">Texto original</th><th class="p-3">Texto mostrado</th><th class="p-3">Acción</th></tr></thead><tbody>@foreach($filas as $clave => $fila)<tr wire:key="texto-{{ $clave }}" class="border-t"><td class="p-3"><ul class="space-y-2">@foreach($fila['ubicaciones'] ?? [] as $ubicacion)<li><strong class="block">{{ $ubicacion['pantalla'] }}</strong><span>{{ $ubicacion['campo'] }}</span></li>@endforeach</ul></td><td class="p-3">{{ $fila['original'] }}</td><td class="p-3" tabindex="0" wire:dblclick="editar('{{ $clave }}')" wire:keydown.enter="editar('{{ $clave }}')">{{ $guardados[$fila['original']] ?? $fila['original'] }}</td><td class="p-3"><flux:button wire:click="editar('{{ $clave }}')" aria-label="Editar texto: {{ \Illuminate\Support\Str::limit($fila['original'],80) }}">Editar</flux:button></td></tr>@endforeach</tbody></table></x-grid-frame></div>
    <div class="hub-ig-footer flex items-center justify-between" data-grid-window='@json($gridVentana)'><flux:button wire:click="$set('pagina', {{ max(1, $pagina-1) }})" :disabled="$pagina <= 1">Anterior</flux:button><span>Páginas visibles {{ $gridVentana['desde'] }}–{{ $gridVentana['hasta'] }} de {{ $ultima }}</span><flux:button wire:click="$set('pagina', {{ min($ultima, $pagina+1) }})" :disabled="$pagina >= $ultima">Siguiente</flux:button></div>
</div>
