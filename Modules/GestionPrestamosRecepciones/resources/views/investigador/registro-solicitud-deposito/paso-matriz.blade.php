<section class="space-y-4" aria-labelledby="registro-biologico-nativo">
    <div>
        <flux:heading id="registro-biologico-nativo" size="lg" level="2">Subir datos · Especímenes y lotes</flux:heading>

    </div>
    <flux:button wire:click="descargarPlantillaMatriz" wire:loading.attr="disabled" icon="arrow-down-tray">Descargar plantilla Excel</flux:button>
    <flux:field class="space-y-2">
        @php($rotuloMatriz = 'Tabla Darwin Core (.xlsx)')
        <flux:label id="matriz-dwc-etiqueta" for="matriz-dwc" data-wizard-copy="literal:{{ $rotuloMatriz }}">{{ $rotuloMatriz }}</flux:label>
        @if($soloEdicionDeTextos ?? false)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span aria-disabled="true" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold text-text-secondary">
                    <flux:icon name="paper-clip" class="size-4 shrink-0" aria-hidden="true" />
                    Elegir archivo Excel
                </span>
                <span class="text-sm text-text-secondary">Ningún archivo seleccionado</span>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2" x-data="{ nombre: '' }"
                x-init="$watch(() => $wire.$get('archivoMatriz'), archivo => { if (archivo == null || archivo === '') nombre = '' })">
                <div class="inline-flex rounded-lg focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-science-blue">
                    <input id="matriz-dwc" type="file" class="sr-only" wire:model="archivoMatriz"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        aria-labelledby="matriz-dwc-etiqueta matriz-dwc-accion" aria-describedby="matriz-dwc-seleccion"
                        x-on:change="nombre = $event.target.files[0]?.name ?? ''"
                        x-on:livewire-upload-error="nombre = ''" x-on:livewire-upload-cancel="nombre = ''" />
                    <label id="matriz-dwc-accion" for="matriz-dwc" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold text-text-primary shadow-sm hover:border-science-blue hover:bg-bg-main">
                        <flux:icon name="paper-clip" class="size-4 shrink-0" aria-hidden="true" />
                        Elegir archivo Excel
                    </label>
                </div>
                <span id="matriz-dwc-seleccion" role="status" aria-atomic="true" class="min-w-0 break-all text-sm text-text-secondary"
                    x-text="nombre || 'Ningún archivo seleccionado'">Ningún archivo seleccionado</span>
            </div>
            <p wire:loading wire:target="archivoMatriz" role="status" class="text-sm text-science-blue">Cargando y validando el Excel…</p>
        @endif
    </flux:field>
    @if($matrizId)
        <div class="rounded border border-border bg-surface p-4">
            <p class="font-semibold text-blue-navy">{{ $archivoMatrizNombre ?: 'Matriz guardada' }} · {{ number_format($totalRegistros, 0, ',', '.') }} registros</p>
            @if($camposDwCRecomendadosFaltantes !== [])<p class="mt-2 text-sm text-text-secondary">Campos recomendados ausentes: {{ implode(', ', $camposDwCRecomendadosFaltantes) }}.</p>@endif
            <details class="mt-3">
                <summary class="cursor-pointer font-semibold text-science-blue">Ver resultado de la validación</summary>
                <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                    @foreach($estadosRegistros as $idRegistro => $registro)
                        <div class="rounded border border-border p-3 text-sm" wire:key="matriz-estado-{{ $idRegistro }}">
                            <p><em>{{ $registro['especieCorregida'] ?: $registro['especieIngresada'] }}</em> · {{ ($registro['advertencias'] ?? []) !== [] ? 'Datos por corregir' : $registro['estado'] }}</p>
                            @foreach($registro['advertencias'] ?? [] as $advertencia)<p class="text-error">{{ $advertencia['campo'] ?? '' }}: {{ $advertencia['mensaje'] ?? $advertencia['original'] ?? 'Revisa este valor en el Excel.' }}</p>@endforeach
                            @if(in_array($registro['estado'], ['Pendiente', 'No Verificado'], true))
                                @foreach($registro['especiesSugeridas'] ?? [] as $sugerencia)<flux:button size="sm" :wire:click="'aceptarSugerencia('.\Illuminate\Support\Js::from($idRegistro).', '.\Illuminate\Support\Js::from($sugerencia).')'">Usar {{ $sugerencia }}</flux:button>@endforeach
                                <flux:button size="sm" :wire:click="'mantenerNombreOriginal('.\Illuminate\Support\Js::from($idRegistro).')'">Conservar nombre original para revisión curatorial</flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    @endif
</section>
