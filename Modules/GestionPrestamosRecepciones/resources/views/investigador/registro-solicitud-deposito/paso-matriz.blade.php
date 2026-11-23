<section class="space-y-4" aria-labelledby="registro-biologico-nativo">
    <div>
        <flux:heading id="registro-biologico-nativo" size="lg" level="2">Subir datos · Especímenes y lotes</flux:heading>

    </div>
    <flux:button wire:click="descargarPlantillaMatriz" wire:loading.attr="disabled" icon="arrow-down-tray">Descargar plantilla Excel</flux:button>
    <flux:field class="space-y-2">
        @php($rotuloMatriz = 'Tabla Darwin Core (.xlsx)')
        <flux:label id="matriz-dwc-etiqueta" for="matriz-dwc" data-wizard-copy="literal:{{ $rotuloMatriz }}">{{ $rotuloMatriz }}</flux:label>
        <x-gestionprestamosrecepciones::dropzone-matriz
            propiedad="archivoMatriz"
            :cargado="$matrizId !== null"
            :archivo-nombre="$archivoMatrizNombre ?? null"
            :error="$errorMatriz ?? null"
            :solo-edicion-de-textos="$soloEdicionDeTextos ?? false" />
    </flux:field>
    @if($matrizId)
        @php($requiereRevision = collect($estadosRegistros)->contains(static fn (array $registro): bool =>
            ($registro['advertencias'] ?? []) !== [] || in_array($registro['estado'], ['Pendiente', 'No Verificado'], true)))
        <div class="rounded border border-border bg-surface p-4">
            <p class="font-semibold text-blue-navy">{{ $archivoMatrizNombre ?: 'Matriz guardada' }} · {{ number_format($totalRegistros, 0, ',', '.') }} registros</p>
            @if($camposDwCRecomendadosFaltantes !== [])<p class="mt-2 text-sm text-text-secondary">Campos recomendados ausentes: {{ implode(', ', $camposDwCRecomendadosFaltantes) }}.</p>@endif
            <details class="mt-3" @if($requiereRevision) open @endif>
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
