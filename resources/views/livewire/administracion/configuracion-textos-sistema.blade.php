<div data-copy-preserve class="hub-workspace mx-auto max-w-7xl space-y-4 p-4">
    <h1 class="hub-page-title">Textos de todas las pantallas</h1>
    <p>Edita títulos, botones, instrucciones, etiquetas y mensajes, incluidos los del mapa. Los nombres de campos del catálogo se administran desde Especímenes y conservan el idioma elegido.</p>
    <div class="flex flex-wrap gap-4"><a href="{{ route('admin.textos-portal') }}" wire:navigate class="underline">Contenido del portal y mapa</a><a href="{{ route('admin.textos-depositos') }}" wire:navigate class="underline">Contenido de depósitos</a></div>
    @if(session('textos-sistema'))<p role="status">{{ session('textos-sistema') }}</p>@endif
    <div class="hub-panel grid gap-3 p-4 sm:grid-cols-2">
        <flux:input type="search" label="Buscar texto" wire:model.live.debounce.400ms="busqueda"/>
        <label>Pantalla<select wire:model.live="pantalla" class="block w-full rounded border p-2"><option value="">Todas las pantallas</option>@foreach($pantallas as $nombre)<option value="{{ $nombre }}">{{ $nombre }}</option>@endforeach</select></label>
    </div>
    <details class="hub-panel p-4"><summary>Agregar un texto visible que no aparece en la lista</summary><form wire:submit="agregar" class="mt-3 flex gap-3"><flux:input wire:model="textoAdicional" label="Texto original exacto"/><flux:button type="submit">Editar</flux:button></form></details>
    @if($original !== '')
    <form wire:submit="guardar" class="hub-panel space-y-3 border-2 border-science-blue p-4">
        <p class="whitespace-pre-line"><strong>Original:</strong> {{ $original }}</p>
        <flux:textarea wire:model="contenido" label="Texto mostrado" rows="4" maxlength="5000"/>
        <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Guardar texto</flux:button><flux:button type="button" wire:click="restaurar">Restaurar original</flux:button><flux:button type="button" wire:click="cancelar">Cancelar</flux:button></div>
    </form>
    @endif
    <div class="hub-panel overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Texto original</th><th class="p-3">Texto mostrado</th><th class="p-3">Acción</th></tr></thead><tbody>@foreach($filas as $clave => $fila)<tr wire:key="texto-{{ $clave }}" class="border-t"><td class="p-3">{{ $fila['original'] }}</td><td class="p-3">{{ $guardados[$fila['original']] ?? $fila['original'] }}</td><td class="p-3"><flux:button wire:click="editar('{{ $clave }}')">Editar</flux:button></td></tr>@endforeach</tbody></table></div>
    <div class="flex items-center justify-between"><flux:button wire:click="$set('pagina', {{ max(1, $pagina-1) }})" :disabled="$pagina <= 1">Anterior</flux:button><span>Página {{ $pagina }} de {{ $ultima }}</span><flux:button wire:click="$set('pagina', {{ min($ultima, $pagina+1) }})" :disabled="$pagina >= $ultima">Siguiente</flux:button></div>
</div>
