@inject('mensajes', 'App\Support\MensajesSistema')
<div class="space-y-5 p-4 sm:p-6" data-hub-inline-feedback @if($carga && in_array($carga->estado, ['en_cola', 'procesando'])) wire:poll.3s="consultar" @endif>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1" class="font-display text-blue-navy font-bold">{{ \App\Support\PortalCopy::text('importacion.titulo') }}</flux:heading>
        <div class="flex flex-wrap items-center gap-3">
            <x-catalogo-excel-menu accion="exportarExcel" />
            @if(\App\Support\AccesoCatalogo::permitido(\App\Support\AccesoCatalogo::CONSULTA))
                <flux:button :href="route('inventario.taxonomia.especimenes')">Ver catálogo</flux:button>
            @endif
        </div>
    </div>
    @if($errorMessage)<div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-red-900">{{ $errorMessage }}</div>@endif
    @if($successMessage)<div role="status" class="rounded border p-4 {{ $resultado && $resultado['avisos'] ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-green-200 bg-green-50 text-green-900' }}">{{ $successMessage }}</div>@endif
    <section class="space-y-4 rounded border border-border bg-surface p-5 shadow-sm" aria-label="Cargar matriz del catálogo">
        <p class="text-sm text-text-secondary">1. Selecciona la matriz. 2. Valida y revisa la vista previa.
            @if($puedeImportar) 3. Importa y espera la confirmación de guardado. @endif
        </p>
        <div class="grid items-start gap-5 md:grid-cols-2">
            <flux:field>
                <div class="flex items-start gap-2" data-catalogo-control-fila>
                    <div class="min-w-0 flex-1" data-catalogo-control data-hub-apex-field>
                        <label for="matriz-catalogo-archivo" data-hub-apex-label>Matriz Excel o CSV</label>
                        <div class="flex h-12 min-w-0 items-center gap-2 px-2 focus-within:outline-2 focus-within:outline-science-blue"
                            data-hub-apex-control @error('archivo') aria-invalid="true" @enderror>
                            <input id="matriz-catalogo-archivo" type="file" wire:model="archivo" class="sr-only"
                                accept=".xlsx,.xls,.csv,.txt" aria-label="Matriz del catálogo" aria-required="true"
                                aria-describedby="matriz-catalogo-seleccion @error('archivo') matriz-catalogo-error @enderror"
                                @error('archivo') aria-invalid="true" @enderror />
                            <label for="matriz-catalogo-archivo" class="inline-flex shrink-0 cursor-pointer items-center gap-2 font-semibold text-science-blue">
                                <flux:icon name="paper-clip" class="size-4" aria-hidden="true" />
                                Elegir archivo
                            </label>
                            <span id="matriz-catalogo-seleccion" role="status" class="min-w-0 truncate text-sm text-text-secondary"
                                title="{{ $archivo?->getClientOriginalName() }}">{{ $archivo?->getClientOriginalName() ?? 'Ningún archivo seleccionado' }}</span>
                        </div>
                    </div>
                    <x-catalogo-ayuda campo="importacion-archivo" :detalle="\Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::importacion('archivo')" />
                </div>
                <flux:error id="matriz-catalogo-error" name="archivo" />
            </flux:field>
            <div class="flex items-start gap-2" data-catalogo-control-fila>
                <div class="min-w-0 flex-1" data-catalogo-control>
                    <x-gestionprestamosrecepciones::lov-popup campo="modo" label="Cómo incorporar los datos"
                        :valor="$modo" :opciones="['completar' => 'Completar datos vacíos', 'actualizar' => 'Actualizar datos con la matriz']" />
                </div>
                <x-catalogo-ayuda campo="importacion-modo" :detalle="\Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::importacion('modo')" />
            </div>
            @if($archivo && in_array(strtolower($archivo->getClientOriginalExtension()), ['csv', 'txt']))
                <div>
                    <div class="flex items-start gap-2" data-catalogo-control-fila>
                        <div class="min-w-0 flex-1" data-catalogo-control>
                            <x-gestionprestamosrecepciones::lov-popup campo="delimitador" label="Separador CSV"
                                :valor="$delimitador" :opciones="[',' => 'Coma', ';' => 'Punto y coma', 'tab' => 'Tabulación']" />
                        </div>
                        <x-catalogo-ayuda campo="importacion-delimitador" :detalle="\Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::importacion('delimitador')" />
                    </div>
                    @if($separadorDetectado)<p class="mt-2 text-sm text-text-secondary">Separador detectado: {{ $separadorDetectado }}. Puedes cambiarlo si tu archivo usa otro.</p>@endif
                </div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <flux:button wire:click="validar" wire:loading.attr="disabled" wire:target="archivo,validar,importar">Validar matriz</flux:button>
            @if($puedeImportar)
                <div>
                    <flux:button variant="primary" wire:click="importar" wire:loading.attr="disabled" wire:target="archivo,validar,importar" :disabled="!$resultado || ($carga && in_array($carga->estado, ['en_cola', 'procesando']))" wire:confirm="{{ $mensajes->texto('catalogo.importacion.confirmacion') }}">Importar al catálogo</flux:button>
                </div>
            @endif
            <flux:button wire:click="limpiar" wire:loading.attr="disabled">Cambiar archivo</flux:button>
            <p wire:loading wire:target="archivo,validar,importar" role="status" class="text-sm text-science-blue">Procesando la matriz…</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 border-t border-border pt-3">
            <x-catalogo-guia-columnas :columnas="$guiaCampos" />
            <flux:button :href="route('inventario.taxonomia.importar.plantilla')">Ver o descargar plantilla</flux:button>
            @if($accesoCompleto)
                <flux:button :href="route('inventario.taxonomia.importar.plantilla.configurar')">Configurar columnas de la plantilla</flux:button>
                <flux:button :href="route('admin.contenidos', ['seccion' => 'portal', 'grupo' => 'importacion'])">Editar textos informativos</flux:button>
            @endif
        </div>
    </section>
    @if($carga)
        @php($guardado = json_decode($carga->resumen ?? '{}', true))
        <section aria-label="{{ $cargaAnterior ? 'Resultado de la carga anterior' : 'Estado de la importación' }}" class="rounded border border-border bg-surface p-5">
            <h2 class="text-lg font-semibold">{{ $carga->archivo_nombre }}</h2>
            @if($carga->estado === 'completada')
                <p role="status" class="mt-2 {{ $cargaAnterior ? 'text-text-secondary' : 'text-green-800' }}">
                    {{ $mensajes->texto($cargaAnterior ? 'catalogo.importacion.resultado_anterior' : 'catalogo.importacion.completada', [
                        'nuevos' => $guardado['nuevos'] ?? 0,
                        'actualizados' => $guardado['actualizados'] ?? 0,
                        'sin_cambios' => $guardado['sin_cambios'] ?? 0,
                    ]) }}
                </p>
                <button type="button" wire:click="verDetalle('{{ $carga->id }}')" class="hub-ig-button mt-3">{{ $cargaAnterior ? 'Ver cambios de la carga anterior' : 'Revisar registros importados' }}</button>
            @elseif($carga->estado === 'fallida')<p role="alert" class="mt-2 text-red-800">{{ $carga->error }}</p>
            @else<p role="status" class="mt-2 text-science-blue">{{ $mensajes->texto($carga->estado === 'en_cola' ? 'catalogo.importacion.en_cola' : 'catalogo.importacion.procesando') }}</p>@endif
        </section>
    @endif
    @if($resultado)
        <section class="space-y-4 rounded border border-border bg-surface p-5" aria-label="Vista previa de la matriz">
            <h2 class="text-lg font-semibold">Vista previa · {{ number_format($resultado['filas'], 0, ',', '.') }} registros</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach(['nuevos' => 'Nuevos', 'actualizados' => 'Por actualizar', 'sin_cambios' => 'Sin cambios', 'provisionales' => 'Identificación provisional'] as $key => $label)<div class="rounded border border-border bg-bg-main p-3"><strong class="block text-2xl">{{ number_format($resultado[$key], 0, ',', '.') }}</strong><span class="text-sm text-text-secondary">{{ $label }}</span></div>@endforeach</div>
            @if($resultado['provisionales'])@endif
            @if($resultado['columnas_adicionales'])
                <details open class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    <summary class="font-semibold">Columnas que requieren revisión ({{ count($resultado['columnas_adicionales']) }})</summary>
                    <p class="mt-2 break-words">{{ implode(', ', $resultado['columnas_adicionales']) }}</p>
                    <p class="mt-1">Revisa si las cabeceras corresponden a los campos de la plantilla. Los valores quedan disponibles en el archivo original.</p>
                </details>
            @endif
            @if($resultado['avisos'])
                <div class="flex items-start gap-2" data-catalogo-control-fila>
                    <div class="min-w-0 flex-1 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" data-catalogo-control><details open><summary class="font-semibold">Observaciones de importación</summary><p class="mt-2">Revisa estos valores antes de guardar. Se conserva el texto original y no se inventan valores normalizados.</p>@foreach($resultado['avisos'] as $aviso => $cantidad)<p class="mt-1">{{ $cantidad }} · {{ $aviso }}</p>@endforeach</details></div>
                    <x-catalogo-ayuda campo="importacion-observaciones" :detalle="\Modules\InventarioGestionColeccion\Presentation\Support\AyudasCapturaCatalogo::importacion('observaciones')" />
                </div>
            @endif
            <x-grid-frame label="Vista previa del catálogo"><table><thead><tr><th scope="col">Fila</th><th scope="col">Identificador</th><th scope="col">Acción</th><th scope="col">Campos con cambios</th></tr></thead><tbody>@foreach($resultado['muestra'] as $r)<tr><td>{{ $r['fila'] }}</td><td>{{ $r['codigo'] }}</td><td>{{ ['nuevos' => 'Crear', 'actualizados' => 'Actualizar', 'sin_cambios' => 'Sin cambios'][$r['accion']] }}</td><td>{{ implode(', ', $r['campos']) }}</td></tr>@endforeach</tbody></table></x-grid-frame>
            <p class="text-xs text-text-secondary">Se muestran los primeros {{ count($resultado['muestra']) }} registros del archivo.</p>
        </section>
    @endif
    <section class="space-y-3 rounded border border-border bg-surface p-5" aria-label="Historial de cargas">
        <h2 class="text-lg font-semibold">Historial de cargas</h2>
        <x-grid-frame label="Historial de matrices"><table><thead><tr><th scope="col">Archivo</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col">Detalle</th></tr></thead><tbody>@forelse($historial as $h)<tr><td>{{ $h->archivo_nombre }}</td><td>{{ ['en_cola' => 'En cola', 'procesando' => 'Procesando', 'completada' => 'Completada', 'fallida' => 'No completada'][$h->estado] ?? $h->estado }}</td><td>{{ \Carbon\Carbon::parse($h->created_at)->timezone('America/Guayaquil')->format('d/m/Y H:i') }}</td><td>@if($accesoCompleto)<a class="hub-ig-button" href="{{ route('inventario.taxonomia.importar.original', ['importacion' => $h->id]) }}" download title="Descargar archivo original completo">Ver detalle</a> @endif<button type="button" class="hub-ig-button" wire:click="verDetalle('{{ $h->id }}')">Ver cambios</button></td></tr>@empty<tr><td colspan="4">Las matrices incorporadas aparecerán aquí.</td></tr>@endforelse</tbody></table></x-grid-frame>
    </section>
    <flux:modal wire:model="detalleAbierto" name="detalle-importacion-catalogo" :closable="false" aria-labelledby="detalle-importacion-titulo" class="w-full md:max-w-4xl">
        <div class="flex items-start justify-between gap-3"><h2 id="detalle-importacion-titulo" class="break-words text-xl font-semibold">{{ $detalle['archivo'] ?? 'Detalle de carga' }}</h2><flux:button autofocus variant="ghost" icon="x-mark" size="sm" wire:click="$set('detalleAbierto', false)" aria-label="Cerrar detalle de importación"/></div>

        <div wire:key="detalle-catalogo-{{ $detalle['id'] ?? 'sin-carga' }}-{{ $paginaDetalle }}" class="my-4 max-h-[60vh] space-y-3 overflow-y-auto">@foreach($filas->take(20) as $f)<details wire:key="fila-catalogo-{{ $f->id }}" class="rounded border border-border p-3"><summary class="cursor-pointer font-semibold">Fila {{ $f->fila }} · {{ $f->codigo }} · {{ $f->accion }}</summary><div class="mt-3 grid gap-4 md:grid-cols-2"><div><h3 class="font-semibold">Columnas del archivo</h3><dl class="mt-2 space-y-1 text-sm">@foreach(json_decode($f->original, true) as $celda)@if(trim($celda['valor']) !== '')<div><dt class="font-medium">{{ $celda['cabecera'] }} ({{ $celda['columna'] }})</dt><dd class="break-words">{{ $celda['valor'] }}</dd></div>@endif@endforeach</dl></div><div><h3 class="font-semibold">Cambios guardados</h3><dl class="mt-2 space-y-2 text-sm">@php($anterior = json_decode($f->anterior ?? '{}', true))@foreach(json_decode($f->posterior ?? '{}', true) as $campo => $valor)@if(!is_array($valor) && ($anterior[$campo] ?? null) !== $valor && !in_array($campo, ['busqueda_global', 'updated_at']))<div><dt class="font-medium">{{ $campo }}</dt><dd class="break-words">{{ is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor }} @if(isset($anterior[$campo]))<span class="block text-text-secondary">Anterior: {{ $anterior[$campo] }}</span>@endif</dd></div>@endif@endforeach</dl></div></div></details>@endforeach</div>
        <div class="flex justify-between gap-3"><flux:button wire:click="moverPaginaDetalle(-1)" :disabled="$paginaDetalle <= 1">Anterior</flux:button><span class="self-center text-sm">Página {{ $paginaDetalle }}</span><flux:button wire:click="moverPaginaDetalle(1)" :disabled="$filas->count() <= 20">Siguiente</flux:button></div>
    </flux:modal>
</div>
