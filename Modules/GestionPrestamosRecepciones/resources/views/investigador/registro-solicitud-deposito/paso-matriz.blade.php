<section class="space-y-4" aria-labelledby="registro-biologico-nativo">
    <div>
        <flux:heading id="registro-biologico-nativo" size="lg" level="2">Subir datos · Especímenes y lotes</flux:heading>
        <p class="mt-2 text-sm text-text-secondary">Carga la tabla Excel con los campos Darwin Core del material que vas a depositar. Una fila corresponde a un registro; conserva los nombres de las columnas.</p>
    </div>
    <flux:button wire:click="descargarPlantillaMatriz" wire:loading.attr="disabled" icon="arrow-down-tray">Descargar plantilla Excel</flux:button>
    <flux:field>
        <flux:label for="matriz-dwc">Tabla Darwin Core (.xlsx)</flux:label>
        <input id="matriz-dwc" type="file" wire:model="archivoMatriz" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full rounded border border-border bg-surface p-3 text-sm" aria-describedby="matriz-dwc-ayuda" />
        <p id="matriz-dwc-ayuda" class="text-xs text-text-secondary">Máximo 10 MB. Puedes reemplazar el archivo; una carga rechazada conserva los datos guardados anteriormente.</p>
    </flux:field>
    <p wire:loading wire:target="archivoMatriz" role="status" class="text-sm text-science-blue">Cargando y validando el Excel…</p>
    @if($matrizId)
        <div class="rounded border border-border bg-surface p-4">
            <p class="font-semibold text-blue-navy">{{ $archivoMatrizNombre ?: 'Matriz guardada' }} · {{ number_format($totalRegistros, 0, ',', '.') }} registros</p>
            @if($camposDwCRecomendadosFaltantes !== [])<p class="mt-2 text-sm text-text-secondary">Campos recomendados ausentes: {{ implode(', ', $camposDwCRecomendadosFaltantes) }}.</p>@endif
            <details class="mt-3">
                <summary class="cursor-pointer font-semibold text-science-blue">Ver resultado de la validación</summary>
                <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                    @foreach($estadosRegistros as $idRegistro => $registro)
                        <div class="rounded border border-border p-3 text-sm" wire:key="matriz-estado-{{ $idRegistro }}">
                            <p><em>{{ $registro['especieCorregida'] ?: $registro['especieIngresada'] }}</em> · {{ $registro['estado'] }}</p>
                            @foreach($registro['advertencias'] ?? [] as $advertencia)<p class="text-error">{{ $advertencia['campo'] ?? '' }}: {{ $advertencia['mensaje'] ?? $advertencia['original'] ?? 'Revisa este valor en el Excel.' }}</p>@endforeach
                            @if(in_array($registro['estado'], ['Pendiente', 'No Verificado'], true))
                                @foreach($registro['especiesSugeridas'] ?? [] as $sugerencia)<flux:button size="sm" wire:click="aceptarSugerencia(@js($idRegistro), @js($sugerencia))">Usar {{ $sugerencia }}</flux:button>@endforeach
                                <flux:button size="sm" wire:click="mantenerNombreOriginal(@js($idRegistro))">Conservar nombre original para revisión curatorial</flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    @endif
</section>
