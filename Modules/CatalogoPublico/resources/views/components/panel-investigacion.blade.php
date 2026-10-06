@php
    $configPanel = config('indices_portal.'.$tipoPanel);
    $filasPanel = $datosMapa[$tipoPanel];
    $maxPanel = max([1, ...array_column($filasPanel, 'registros')]);
    $mesesPanel = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $datosGrafico = $tipoPanel === 'metodos' ? array_map(static fn (array $fila): array => $fila + ['etiqueta' => \Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($fila['metodo'])], $filasPanel) : $filasPanel;
@endphp
<section class="atlas-panel" wire:key="grafico-{{ $tipoPanel }}-{{ sha1(json_encode($filasPanel)) }}" aria-labelledby="titulo-{{ $tipoPanel }}" x-data="portalGrafico(@js($tipoPanel), @js($datosGrafico), @js($configPanel['titulo']))" x-on:alternar-tipo-grafico="alternar($event.detail)">
    <div class="atlas-panel-header"><div><h2 id="titulo-{{ $tipoPanel }}">{{ $configPanel['titulo'] }}</h2><p class="atlas-panel-subtitle">{{ $configPanel['subtitulo'] }}</p></div><x-catalogopublico::menu-analisis :tipo="$tipoPanel" :datos="$filasPanel" /></div>
    <x-catalogopublico::grafico-panel :titulo="$configPanel['titulo']" />
    <div class="atlas-ranked-chart" x-show="modo === 'barras' || tablaAbierta">
        @forelse($filasPanel as $filaPanel)
            @php
                [$etiquetaPanel, $accionPanel] = match ($tipoPanel) {
                    'estacionalidad' => [$mesesPanel[(int) $filaPanel['mes']], 'seleccionarMes('.(int) $filaPanel['mes'].')'],
                    'altitud' => [$filaPanel['desde'].'–'.$filaPanel['hasta'].' m', 'seleccionarAltitud('.(int) $filaPanel['desde'].','.(int) $filaPanel['hasta'].')'],
                    'metodos' => [\Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico::etiqueta($filaPanel['metodo']), 'seleccionarMetodo('.json_encode($filaPanel['metodo']).')'],
                };
            @endphp
            <button type="button" class="atlas-ranked-row" wire:click="{{ $accionPanel }}" wire:loading.attr="disabled" title="Filtrar: {{ $etiquetaPanel }}{{ $tipoPanel === 'metodos' ? ' · Valores originales: '.implode(', ', $filaPanel['fuentes'] ?? []) : '' }}" aria-label="Filtrar {{ $etiquetaPanel }}: {{ number_format((int) $filaPanel['registros'], 0, ',', '.') }} {{ (int) $filaPanel['registros'] === 1 ? 'registro' : 'registros' }}{{ $tipoPanel === 'metodos' ? '. Valores originales: '.implode(', ', $filaPanel['fuentes'] ?? []) : '' }}">
                <span>{{ $etiquetaPanel }}</span><strong>{{ number_format((int) $filaPanel['registros'], 0, ',', '.') }}</strong>
                <i aria-hidden="true"><b style="width:{{ $filaPanel['registros'] / $maxPanel * 100 }}%"></b></i>
            </button>
        @empty<p class="atlas-chart-empty">No hay datos públicos disponibles para este indicador en la selección.</p>@endforelse
    </div>
    <x-catalogopublico::figura-panel :tipo="$tipoPanel" />
</section>
