<div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6" @if($carga && in_array($carga->estado, ['en_cola', 'procesando'])) wire:poll.3s="consultar" @endif>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-semibold text-blue-navy">Importar y actualizar catálogo</h1><p class="mt-1 text-sm text-text-secondary">Carga una matriz y revisa los cambios antes de incorporarlos.</p></div>
        <a href="{{ route('inventario.taxonomia.especimenes') }}" class="hub-ig-button">Ver catálogo</a>
    </div>
    @if($errorMessage)<div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-red-900">{{ $errorMessage }}</div>@endif
    @if($successMessage)<div role="status" class="rounded border border-green-200 bg-green-50 p-4 text-green-900">{{ $successMessage }}</div>@endif
    <section class="space-y-4 rounded border border-border bg-surface p-5 shadow-sm" aria-label="Cargar matriz del catálogo">
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:field><flux:label>Matriz Excel o CSV</flux:label><input type="file" wire:model="archivo" accept=".xlsx,.xls,.csv,.txt" aria-label="Matriz del catálogo" class="block w-full rounded border border-border bg-bg-main p-3 text-sm"/><flux:error name="archivo"/><p class="mt-2 text-xs text-text-secondary">Hasta 20 MB. Las columnas pueden tener otro orden; las adicionales se conservan con el original. La clasificación aportada completa niveles intermedios ausentes y conserva los taxones existentes.</p></flux:field>
            <flux:field><flux:label>Cómo incorporar los datos</flux:label><flux:select wire:model.live="modo"><option value="completar">Completar datos vacíos</option><option value="actualizar">Actualizar datos con la matriz</option></flux:select><p class="mt-2 text-xs text-text-secondary">Los identificadores existentes conservan su ficha. Las celdas vacías no borran datos.</p></flux:field>
        </div>
        @if($archivo && in_array(strtolower($archivo->getClientOriginalExtension()), ['csv', 'txt']))<flux:select wire:model.live="delimitador" label="Separador CSV"><option value=",">Coma</option><option value=";">Punto y coma</option><option value="tab">Tabulación</option></flux:select>@endif
        <div class="flex flex-wrap gap-3">
            <flux:button variant="filled" wire:click="validar" wire:loading.attr="disabled" wire:target="archivo,validar,importar">Validar matriz</flux:button>
            <flux:button variant="primary" wire:click="importar" wire:loading.attr="disabled" wire:target="archivo,validar,importar" :disabled="!$resultado || ($carga && in_array($carga->estado, ['en_cola', 'procesando']))" wire:confirm="Se aplicarán las altas y actualizaciones del resumen. ¿Continuar?">Importar al catálogo</flux:button>
            <flux:button variant="ghost" wire:click="limpiar" wire:loading.attr="disabled">Cambiar archivo</flux:button>
            <a href="{{ route('inventario.taxonomia.importar.plantilla') }}" class="hub-ig-button ml-auto">Descargar plantilla</a>
        </div>
        <p wire:loading wire:target="archivo,validar,importar" role="status" class="text-sm text-science-blue">Procesando la matriz…</p>
    </section>
    @if($carga)
        @php($guardado = json_decode($carga->resumen ?? '{}', true))
        <section aria-label="Estado de la importación" class="rounded border border-border bg-surface p-5">
            <h2 class="text-lg font-semibold">{{ $carga->archivo_nombre }}</h2>
            @if($carga->estado === 'completada')<p role="status" class="mt-2 text-green-800">Carga completada: {{ $guardado['nuevos'] ?? 0 }} nuevos, {{ $guardado['actualizados'] ?? 0 }} actualizados y {{ $guardado['sin_cambios'] ?? 0 }} sin cambios.</p><button type="button" wire:click="verDetalle('{{ $carga->id }}')" class="hub-ig-button mt-3">Revisar registros importados</button>
            @elseif($carga->estado === 'fallida')<p role="alert" class="mt-2 text-red-800">{{ $carga->error }}</p>
            @else<p role="status" class="mt-2 text-science-blue">{{ $carga->estado === 'en_cola' ? 'Carga en cola.' : 'Incorporando registros.' }} Puedes salir de esta pantalla y consultar el historial después.</p>@endif
        </section>
    @endif
    @if($resultado)
        <section class="space-y-4 rounded border border-border bg-surface p-5" aria-label="Vista previa de la matriz">
            <h2 class="text-lg font-semibold">Vista previa · {{ number_format($resultado['filas'], 0, ',', '.') }} registros</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach(['nuevos' => 'Nuevos', 'actualizados' => 'Por actualizar', 'sin_cambios' => 'Sin cambios', 'provisionales' => 'Identificación provisional'] as $key => $label)<div class="rounded border border-border bg-bg-main p-3"><strong class="block text-2xl">{{ number_format($resultado[$key], 0, ',', '.') }}</strong><span class="text-sm text-text-secondary">{{ $label }}</span></div>@endforeach</div>
            @if($resultado['provisionales'])<p class="text-sm text-text-secondary">Las identificaciones con códigos provisionales se conservan para revisión, asociadas al género; no crean especies válidas a partir del código.</p>@endif
            @if($resultado['columnas_adicionales'])<details class="text-sm"><summary>Columnas adicionales conservadas ({{ count($resultado['columnas_adicionales']) }})</summary><p class="mt-2 break-words">{{ implode(', ', $resultado['columnas_adicionales']) }}</p></details>@endif
            @if($resultado['avisos'])<details class="text-sm"><summary>Datos que requieren revisión</summary>@foreach($resultado['avisos'] as $aviso => $cantidad)<p class="mt-1">{{ $cantidad }} · {{ $aviso }}</p>@endforeach</details>@endif
            <x-grid-frame label="Vista previa del catálogo"><table><thead><tr><th scope="col">Fila</th><th scope="col">Identificador</th><th scope="col">Acción</th><th scope="col">Campos con cambios</th></tr></thead><tbody>@foreach($resultado['muestra'] as $r)<tr><td>{{ $r['fila'] }}</td><td>{{ $r['codigo'] }}</td><td>{{ ['nuevos' => 'Crear', 'actualizados' => 'Actualizar', 'sin_cambios' => 'Sin cambios'][$r['accion']] }}</td><td>{{ implode(', ', $r['campos']) }}</td></tr>@endforeach</tbody></table></x-grid-frame>
            <p class="text-xs text-text-secondary">Se muestran los primeros {{ count($resultado['muestra']) }} registros del archivo.</p>
        </section>
    @endif
    <section class="space-y-3 rounded border border-border bg-surface p-5" aria-label="Historial de cargas">
        <h2 class="text-lg font-semibold">Historial de cargas</h2>
        <x-grid-frame label="Historial de matrices"><table><thead><tr><th scope="col">Archivo</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col">Detalle</th></tr></thead><tbody>@forelse($historial as $h)<tr><td>{{ $h->archivo_nombre }}</td><td>{{ ['en_cola' => 'En cola', 'procesando' => 'Procesando', 'completada' => 'Completada', 'fallida' => 'Requiere revisión'][$h->estado] ?? $h->estado }}</td><td>{{ \Carbon\Carbon::parse($h->created_at)->timezone('America/Guayaquil')->format('d/m/Y H:i') }}</td><td><button type="button" class="hub-ig-button" wire:click="verDetalle('{{ $h->id }}')">Ver detalle</button></td></tr>@empty<tr><td colspan="4">Las matrices incorporadas aparecerán aquí.</td></tr>@endforelse</tbody></table></x-grid-frame>
    </section>
    <flux:modal wire:model="detalleAbierto" name="detalle-importacion-catalogo" :closable="false" aria-labelledby="detalle-importacion-titulo" class="w-full md:max-w-4xl">
        <div class="flex items-start justify-between gap-3"><h2 id="detalle-importacion-titulo" class="break-words text-xl font-semibold">{{ $detalle['archivo'] ?? 'Detalle de carga' }}</h2><flux:button autofocus variant="ghost" icon="x-mark" size="sm" wire:click="$set('detalleAbierto', false)" aria-label="Cerrar detalle de importación"/></div>
        <p class="mt-2 text-sm text-text-secondary">Cada fila conserva sus columnas originales y los valores anteriores y posteriores de la ficha.</p>
        <div class="my-4 max-h-[60vh] space-y-3 overflow-y-auto">@foreach($filas->take(20) as $f)<details class="rounded border border-border p-3"><summary class="cursor-pointer font-semibold">Fila {{ $f->fila }} · {{ $f->codigo }} · {{ $f->accion }}</summary><div class="mt-3 grid gap-4 md:grid-cols-2"><div><h3 class="font-semibold">Columnas del archivo</h3><dl class="mt-2 space-y-1 text-sm">@foreach(json_decode($f->original, true) as $celda)@if(trim($celda['valor']) !== '')<div><dt class="font-medium">{{ $celda['cabecera'] }} ({{ $celda['columna'] }})</dt><dd class="break-words">{{ $celda['valor'] }}</dd></div>@endif@endforeach</dl></div><div><h3 class="font-semibold">Cambios guardados</h3><dl class="mt-2 space-y-2 text-sm">@php($anterior = json_decode($f->anterior ?? '{}', true))@foreach(json_decode($f->posterior ?? '{}', true) as $campo => $valor)@if(!is_array($valor) && ($anterior[$campo] ?? null) !== $valor && !in_array($campo, ['busqueda_global', 'updated_at']))<div><dt class="font-medium">{{ $campo }}</dt><dd class="break-words">{{ is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor }} @if(isset($anterior[$campo]))<span class="block text-text-secondary">Anterior: {{ $anterior[$campo] }}</span>@endif</dd></div>@endif@endforeach</dl></div></div></details>@endforeach</div>
        <div class="flex justify-between gap-3"><flux:button wire:click="moverPaginaDetalle(-1)" :disabled="$paginaDetalle <= 1">Anterior</flux:button><span class="self-center text-sm">Página {{ $paginaDetalle }}</span><flux:button wire:click="moverPaginaDetalle(1)" :disabled="$filas->count() <= 20">Siguiente</flux:button></div>
    </flux:modal>
</div>
