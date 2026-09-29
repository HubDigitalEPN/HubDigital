<div class="hub-transactional-ui hub-curator-home hub-workspace w-full p-4 sm:p-5">
    <header class="hub-curator-heading">
        <div>
            <h1>Panel del curador</h1>
            <p>Colecciones, investigación y patrimonio natural en un mismo lugar.</p>
        </div>
        <div class="hub-curator-heading__actions">
            <a href="{{ route('prestamos.curador.depositos', ['vista' => 'actas']) }}" wire:navigate class="hub-curator-button hub-curator-button--outline"><flux:icon name="document-text" class="size-4" /> Actas pendientes</a>
            <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate class="hub-curator-button hub-curator-button--primary"><flux:icon name="plus" class="size-4" /> Revisar ingresos</a>
        </div>
    </header>

    <nav class="hub-curator-shortcuts" aria-label="Accesos principales">
        <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate><flux:icon name="rectangle-stack" class="size-6" /><span><strong>Colecciones</strong><small>Gestionar y consultar</small></span></a>
        <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate><flux:icon name="magnifying-glass" class="size-6" /><span><strong>Buscar</strong><small>Objetos, lotes y registros</small></span></a>
        <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><flux:icon name="tag" class="size-6" /><span><strong>Ingresos</strong><small>Revisar nuevos materiales</small></span></a>
        <a href="{{ route('divulgacion.imagenes') }}" wire:navigate><flux:icon name="photo" class="size-6" /><span><strong>Imágenes</strong><small>Archivo de la colección</small></span></a>
        <a href="{{ route('divulgacion.analisis-diversidad') }}"><flux:icon name="chart-bar" class="size-6" /><span><strong>Análisis de diversidad</strong><small>Índices de la colección</small></span></a>
        <button type="button" wire:click="descargarReporteDepositos"><flux:icon name="arrow-down-tray" class="size-6" /><span><strong>Reportes</strong><small>Descargar movimientos</small></span></button>
    </nav>

    <div class="hub-curator-grid">
        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="recepcion-title">
            <div class="hub-curator-card__heading"><flux:icon name="cube" class="size-6" /><div><h2 id="recepcion-title">Recepción y depósitos</h2><p>Seguimiento de materiales en proceso de ingreso.</p></div></div>
            <div class="hub-curator-metrics hub-curator-metrics--four">
                <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><strong>{{ number_format($depPorRevisar, 0, ',', '.') }}</strong><span>Depósitos<br>por revisar</span></a>
                <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><strong>{{ number_format($depEnviadas, 0, ',', '.') }}</strong><span>Solicitudes<br>recibidas</span></a>
                <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><strong>{{ number_format($depLotesRecibidos, 0, ',', '.') }}</strong><span>Lotes<br>recibidos</span></a>
                <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><strong>{{ number_format($depRegistrosMatriz, 0, ',', '.') }}</strong><span>Registros<br>en matrices</span></a>
            </div>
            <div class="hub-curator-activity">
                <h3>Últimos movimientos</h3>
                @forelse (array_slice($colaCuratorial, 0, 3) as $fila)
                    <a href="{{ $fila['ruta'] }}" wire:navigate><span class="hub-curator-activity__dot"></span><span><strong>{{ $fila['numero'] }}</strong> · {{ $fila['detalle'] }}<small>{{ $fila['estado'] }} · {{ $fila['fecha'] }}</small></span><flux:icon name="arrow-right" class="size-4" /></a>
                @empty
                    <p>No hay movimientos recientes.<small>Los ingresos y depósitos aparecerán aquí cuando se registren.</small></p>
                @endforelse
            </div>
        </section>

        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="archivo-title">
            <div class="hub-curator-card__heading hub-curator-card__heading--split"><flux:icon name="photo" class="size-6" /><div><h2 id="archivo-title">Imagen del archivo</h2><p>Fotografías de la colección y documentos de depósitos.</p></div></div>
            @if($archivoActual)
                <label class="hub-curator-file-picker">Explorar archivo
                    <select wire:model.live="archivoSeleccion" aria-label="Seleccionar fotografía o documento del archivo">
                        @foreach ($archivoElementos as $elemento)
                            <option value="{{ $elemento['id'] }}">{{ $elemento['tipo'] === 'imagen' ? 'Colección' : $elemento['detalle'] }} · {{ $elemento['titulo'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="hub-curator-preview">
                    @if($archivoActual['tipo'] === 'imagen')
                        <a href="{{ $archivoActual['url'] }}" target="_blank" rel="noopener" aria-label="Abrir imagen {{ $archivoActual['titulo'] }}">
                            <img src="{{ $archivoActual['url'] }}" alt="Fotografía de {{ $archivoActual['titulo'] }}" loading="lazy">
                        </a>
                    @else
                        <iframe title="Vista previa de {{ $archivoActual['titulo'] }}" src="{{ $archivoActual['url'] }}#toolbar=0" loading="lazy"></iframe>
                    @endif
                    <div>
                        <strong>{{ $archivoActual['titulo'] }}</strong>
                        <small>{{ $archivoActual['detalle'] }}</small>
                        <p>{{ $archivoActual['tipo'] === 'imagen' ? 'Imagen conservada en el archivo digital de la colección.' : 'Documento del expediente almacenado en R2.' }}</p>
                        <a href="{{ $archivoActual['url'] }}" target="_blank" rel="noopener">Abrir {{ $archivoActual['tipo'] === 'imagen' ? 'imagen' : 'documento' }} <flux:icon name="arrow-top-right-on-square" class="size-3" /></a>
                        @if(isset($archivoActual['expediente']))
                            <a href="{{ $archivoActual['expediente'] }}" wire:navigate>Revisar expediente <flux:icon name="arrow-right" class="size-3" /></a>
                        @endif
                    </div>
                </div>
            @else
                <div class="hub-curator-preview-empty"><flux:icon name="photo" class="size-9" /><strong>El archivo aún no tiene imágenes ni documentos.</strong><p>Al registrar fotografías de especímenes o recibir expedientes, podrás consultarlos aquí.</p><a href="{{ route('divulgacion.imagenes') }}" wire:navigate>Gestionar imágenes <flux:icon name="arrow-right" class="size-4" /></a></div>
            @endif
            <div class="hub-curator-pending">
                <div><flux:icon name="clock" class="size-5" /><h3>Acciones pendientes</h3></div>
                @if(count($colaCuratorial) > 0)
                    <a href="{{ $colaCuratorial[0]['ruta'] }}" wire:navigate>{{ $colaCuratorial[0]['numero'] }} · {{ $colaCuratorial[0]['accion'] }} <flux:icon name="arrow-right" class="size-4" /></a>
                @else
                    <p>No hay acciones pendientes.<small>Cuando tengas actas, ingresos u otras tareas asignadas, se mostrarán aquí.</small></p>
                @endif
            </div>
        </section>

        <section class="hub-curator-card hub-curator-card--summary" aria-labelledby="coleccion-title">
            <div class="hub-curator-card__heading"><flux:icon name="circle-stack" class="size-6" /><div><h2 id="coleccion-title">La colección</h2><p>Resumen general de los registros en el sistema.</p></div><a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate>Ir a la colección <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--four">
                <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate><strong>{{ number_format($colEspecimenes, 0, ',', '.') }}</strong><span>Registros</span></a>
                <a href="{{ route('prestamos.curador.depositos') }}" wire:navigate><strong>{{ number_format($depLotesRecibidos, 0, ',', '.') }}</strong><span>Lotes recibidos</span></a>
                <a href="{{ route('inventario.taxonomia.especimenes') }}" wire:navigate><strong>{{ number_format($colLocalidades, 0, ',', '.') }}</strong><span>Localidades</span></a>
                <a href="{{ route('inventario.taxonomia.taxones') }}" wire:navigate><strong>{{ number_format($colTaxones, 0, ',', '.') }}</strong><span>Taxones</span></a>
            </div>
        </section>
        <section class="hub-curator-card hub-curator-card--summary" aria-labelledby="prestamos-title">
            <div class="hub-curator-card__heading"><flux:icon name="book-open" class="size-6" /><div><h2 id="prestamos-title">Préstamos</h2><p>Materiales de la colección en préstamo.</p></div><a href="{{ route('prestamos.curador.prestamos') }}" wire:navigate>Ver préstamos <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--three">
                <a href="{{ route('prestamos.curador.prestamos') }}" wire:navigate><strong>{{ number_format($prestActivos, 0, ',', '.') }}</strong><span>Préstamos activos</span></a>
                <a href="{{ route('prestamos.curador.solicitudes') }}" wire:navigate><strong>{{ number_format($statSolicitudesPendientes, 0, ',', '.') }}</strong><span>En evaluación</span></a>
                <a href="{{ route('prestamos.curador.actas') }}" wire:navigate><strong>{{ number_format($statActasPorValidar, 0, ',', '.') }}</strong><span>Actas por validar</span></a>
            </div>
        </section>
    </div>

    <details class="hub-curator-details">
        <summary>Estadísticas y seguimiento detallado <flux:icon name="chevron-down" class="size-4" /></summary>
        <div class="hub-curator-details__body">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2>Depósitos y recepción</h2><div class="flex items-center gap-2"><flux:select wire:model.live="periodoAnalisis" aria-label="Período del análisis" class="w-40"><flux:select.option value="6">Últimos 6 meses</flux:select.option><flux:select.option value="12">Últimos 12 meses</flux:select.option><flux:select.option value="24">Últimos 24 meses</flux:select.option></flux:select><flux:button wire:click="descargarReporteDepositos" variant="ghost" icon="arrow-down-tray">Descargar reporte</flux:button></div></div>
            <div class="grid gap-3 lg:grid-cols-2"><x-bar-chart titulo="Tendencia de solicitudes" :subtitulo="'Depósitos y donaciones enviados durante '.$periodoAnaliticoEtiqueta" :filas="$graficoDepositosPorMes" /><x-bar-chart titulo="Estado de los depósitos" :subtitulo="'Distribución documental durante '.$periodoAnaliticoEtiqueta" :filas="$graficoEstadosDepositos" /><x-bar-chart titulo="Familias mejor representadas" :subtitulo="'Las 8 con más registros, de '.number_format($colFamilias, 0, ',', '.').' familias'" :filas="$graficoFamilias" /><x-bar-chart titulo="Nivel de determinación" subtitulo="Rango taxonómico de cada espécimen" :filas="$graficoDeterminacion" /></div>
        </div>
    </details>
</div>
