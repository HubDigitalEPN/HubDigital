@php
    $esDonacion = $tipoTramite === \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite::Donacion->value;
    $cargadaSinError = $matrizCargada && empty($errorMatriz);

    $pendientes = collect($estadosRegistros)->where('estado', 'Pendiente');
    $pendientesConSugerencia = $pendientes->filter(fn ($r) => $r['especieSugerida'] !== null);
    $pendientesSinCatalogar = $pendientes->filter(fn ($r) => $r['noCatalogado'] === true);
    $alertasJustificadas = collect($estadosRegistros)->where('estado', 'Validación Manual por Curaduría')->count();
    $noVerificados = collect($estadosRegistros)->where('estado', 'No Verificado')->count();
    $todosResueltos = $pendientes->isEmpty();

    $totalRegistros = count($estadosRegistros);
    $resueltoCount = $totalRegistros - $pendientes->count();
    $porcentajeResuelto = $totalRegistros > 0 ? round(($resueltoCount / $totalRegistros) * 100) : 0;
@endphp

<div class="space-y-6" wire:loading.class="opacity-40 pointer-events-none" wire:target="archivoMatriz">

    {{-- Header --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="lg" level="2" class="font-display">Matriz de especies</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Carga la matriz <strong>Darwin Core</strong> de tus especímenes. El sistema valida la integridad
                de campos y la consistencia taxonómica contra el catálogo de curaduría.
            </flux:text>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border border-science-blue/30 bg-science-blue/5 text-science-blue whitespace-nowrap self-start">
            <flux:icon name="sparkles" class="size-3" />
            Estándar Darwin Core
        </span>
    </div>

    {{-- Banner: rechazo por campo DwC faltante --}}
    @if($matrizCargada && !empty($errorMatriz))
        <flux:callout variant="danger" icon="x-circle">
            <flux:heading>Carga rechazada</flux:heading>
            <flux:text class="text-sm">
                {{ $errorMatriz }}. Corrige el archivo y vuelve a subirlo.
            </flux:text>
        </flux:callout>
    @endif

    {{-- Dropzone de la matriz --}}
    <x-gestionprestamosrecepciones::dropzone-matriz
        propiedad="archivoMatriz"
        :requerido="true"
        :cargado="$matrizCargada"
        :archivoNombre="$archivoMatrizNombre"
        :error="$errorMatriz"
    />

    {{-- Pantalla de carga mientras se procesa la matriz --}}
    <div wire:loading wire:target="archivoMatriz" class="w-full rounded-lg border border-border bg-surface p-10">
        <div class="flex flex-col items-center justify-center gap-4">
            <flux:icon name="arrow-path" class="size-8 text-science-blue animate-spin" />
            <div class="text-center space-y-1">
                <p class="text-sm font-semibold text-text-primary">Procesando matriz de especies</p>
                <p class="text-xs text-text-secondary">Validando campos Darwin Core y consistencia taxonómica contra el catálogo...</p>
            </div>
        </div>
    </div>

    {{-- Integridad de campos Darwin Core --}}
    @if($matrizCargada && !empty($camposDwCPresentes))
        @php
            $camposClasificados = array_merge($camposDwCCriticos, $camposDwCRecomendados);
            $camposExtra = array_values(array_filter(
                $camposDwCPresentes,
                fn($c) => !in_array($c, $camposClasificados)
            ));
        @endphp
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <flux:icon name="document-text" class="size-4 text-text-secondary" />
                <flux:heading size="sm" level="3">Integridad de campos Darwin Core</flux:heading>
            </div>

            {{-- Críticos --}}
            @if(!empty($camposDwCCriticos))
                <div>
                    <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-1.5">Críticos</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($camposDwCCriticos as $campo)
                            <x-gestionprestamosrecepciones::dwc-chip
                                :campo="$campo"
                                :presente="in_array($campo, $camposDwCPresentes)"
                                prioridad="critica"
                            />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recomendados --}}
            @if(!empty($camposDwCRecomendados))
                <div>
                    <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-1.5">Recomendados</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($camposDwCRecomendados as $campo)
                            <x-gestionprestamosrecepciones::dwc-chip
                                :campo="$campo"
                                :presente="!in_array($campo, $camposDwCRecomendadosFaltantes)"
                                prioridad="recomendada"
                            />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Extras presentes en el Excel --}}
            @if(!empty($camposExtra))
                <div>
                    <p class="text-xs font-semibold text-text-secondary uppercase tracking-wide mb-1.5">Otros campos incluidos</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($camposExtra as $campo)
                            <x-gestionprestamosrecepciones::dwc-chip
                                :campo="$campo"
                                :presente="true"
                                prioridad="opcional"
                            />
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Validacion taxonomica --}}
    @if($cargadaSinError)
        <div class="space-y-4">

            {{-- Header + badge estado --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="bug-ant" class="size-4 text-bio-green" />
                        <flux:heading size="sm" level="3">Validación taxonómica</flux:heading>
                    </div>
                    <flux:text class="text-xs text-text-secondary mt-1">
                        @if($esDonacion)
                            Transferencia por donación — se omite la validación de inconsistencias tipográficas.
                        @else
                            Revisa cada espécimen contra el catálogo de referencia de la colección.
                        @endif
                    </flux:text>
                </div>
                <x-gestionprestamosrecepciones::matrix-status-badge :estado="$estadoMatriz" />
            </div>

            {{-- Donacion: banner automatico --}}
            @if($esDonacion)
                <flux:callout variant="info" icon="information-circle">
                    <flux:heading>Taxonomía aceptada automáticamente</flux:heading>
                    <flux:text class="text-sm">
                        Al tratarse de una transferencia de colección establecida, se conserva la identificación
                        taxonómica original de forma íntegra. La matriz asume el estado <strong>Validada Técnicamente</strong>.
                    </flux:text>
                </flux:callout>
            @endif

            {{-- Deposito: toolbar batch para sugerencias --}}
            @if(!$esDonacion && $pendientesConSugerencia->isNotEmpty())
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between p-3 rounded-lg border border-science-blue/30 bg-science-blue/5">
                    <span class="text-xs text-text-secondary">
                        <strong>{{ $pendientesConSugerencia->count() }}</strong> sugerencia(s) de corrección pendiente(s)
                    </span>
                    <flux:modal.trigger name="confirmar-aceptar-todas">
                        <flux:button variant="primary" size="sm" icon="sparkles">
                            Aceptar todas las sugerencias
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            {{-- Modal de confirmacion para aceptar todas --}}
            <flux:modal name="confirmar-aceptar-todas" class="max-w-sm">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">Aceptar todas las sugerencias</flux:heading>
                        <flux:text class="text-text-secondary mt-1">
                            Se aplicarán <strong>{{ $pendientesConSugerencia->count() }}</strong> correcciones
                            tipográficas sugeridas por el catálogo de referencia. Esta acción se puede deshacer
                            individualmente después.
                        </flux:text>
                    </div>
                    <div class="flex justify-end gap-3">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancelar</flux:button>
                        </flux:modal.close>
                        <flux:button
                            variant="primary"
                            wire:click="aceptarTodasLasSugerencias"
                            wire:loading.attr="disabled"
                            wire:target="aceptarTodasLasSugerencias"
                        >
                            <span wire:loading.remove wire:target="aceptarTodasLasSugerencias">Confirmar</span>
                            <span wire:loading wire:target="aceptarTodasLasSugerencias" class="inline-flex items-center gap-2">
                                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                                Aplicando...
                            </span>
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            {{-- Contador de progreso --}}
            @if(!$esDonacion && $totalRegistros > 0)
                <div class="space-y-3 p-4 rounded-lg border border-border bg-bg-main">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-surface border border-border text-text-primary">
                            <flux:icon name="check-circle" class="size-4 {{ $todosResueltos ? 'text-success' : 'text-science-blue' }}" />
                            {{ $resueltoCount }}/{{ $totalRegistros }} resueltos
                        </span>
                        @if($pendientesConSugerencia->count() > 0)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full text-xs font-medium bg-warning/10 border border-warning/20 text-warning">
                                <flux:icon name="sparkles" class="size-3.5" />
                                {{ $pendientesConSugerencia->count() }} sugerencia(s)
                            </span>
                        @endif
                        @if($pendientesSinCatalogar->count() > 0)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full text-xs font-medium bg-error/10 border border-error/20 text-error">
                                <flux:icon name="exclamation-triangle" class="size-3.5" />
                                {{ $pendientesSinCatalogar->count() }} sin catalogar
                            </span>
                        @endif
                        @if($noVerificados > 0)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full text-xs font-medium bg-info/10 border border-info/20 text-info">
                                <flux:icon name="wifi" class="size-3.5" />
                                {{ $noVerificados }} no verificado(s)
                            </span>
                        @endif
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-border overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $todosResueltos ? 'bg-success' : 'bg-science-blue' }}"
                            style="width: {{ $porcentajeResuelto }}%"
                        ></div>
                    </div>
                </div>
            @endif

            {{-- Banner: especies no verificadas por fallo de GBIF --}}
            @if(!$esDonacion && $noVerificados > 0)
                <flux:callout variant="warning" icon="wifi">
                    <flux:heading>{{ $noVerificados }} especie(s) no pudieron verificarse</flux:heading>
                    <flux:text class="text-sm">
                        El servicio de validación taxonómica (GBIF) no respondió para algunos registros.
                        Puedes continuar con el envío — estas especies serán revisadas manualmente por curaduría.
                    </flux:text>
                </flux:callout>
            @endif

            {{-- Tabla de registros con filtros --}}
            @php
                $conteoValidados = collect($estadosRegistros)->whereIn('estado', ['Validado Técnicamente', 'Corregido por Sugerencia', 'Validación Manual por Curaduría'])->count();
                $conteoPendientes = $pendientes->count();
            @endphp

            {{-- Barra de filtros --}}
            @if(!$esDonacion && $totalRegistros > 0)
                <div class="flex flex-wrap gap-1.5 mb-3 p-1 rounded-lg border border-border bg-bg-main">
                    <button
                        wire:click="$set('filtroTabla', 'todos')"
                        @class([
                            'px-3 py-1.5 text-xs rounded-md border transition-colors',
                            'bg-science-blue/10 text-science-blue border-science-blue/30 font-semibold' => $filtroTabla === 'todos',
                            'text-text-secondary hover:text-text-primary hover:bg-surface border-transparent' => $filtroTabla !== 'todos',
                        ])
                    >
                        Todos ({{ $totalRegistros }})
                    </button>
                    <button
                        wire:click="$set('filtroTabla', 'pendientes')"
                        @class([
                            'px-3 py-1.5 text-xs rounded-md border transition-colors',
                            'bg-warning/10 text-warning border-warning/30 font-semibold' => $filtroTabla === 'pendientes',
                            'text-text-secondary hover:text-text-primary hover:bg-surface border-transparent' => $filtroTabla !== 'pendientes',
                        ])
                    >
                        Pendientes ({{ $conteoPendientes }})
                    </button>
                    <button
                        wire:click="$set('filtroTabla', 'resueltos')"
                        @class([
                            'px-3 py-1.5 text-xs rounded-md border transition-colors',
                            'bg-success/10 text-success border-success/30 font-semibold' => $filtroTabla === 'resueltos',
                            'text-text-secondary hover:text-text-primary hover:bg-surface border-transparent' => $filtroTabla !== 'resueltos',
                        ])
                    >
                        Resueltos ({{ $conteoValidados }})
                    </button>
                    @if($noVerificados > 0)
                        <button
                            wire:click="$set('filtroTabla', 'no_verificados')"
                            @class([
                                'px-3 py-1.5 text-xs rounded-md border transition-colors',
                                'bg-info/10 text-info border-info/30 font-semibold' => $filtroTabla === 'no_verificados',
                                'text-text-secondary hover:text-text-primary hover:bg-surface border-transparent' => $filtroTabla !== 'no_verificados',
                            ])
                        >
                            No verificados ({{ $noVerificados }})
                        </button>
                    @endif
                </div>
            @endif

            <div class="rounded-lg border border-border overflow-hidden">
                {{-- Header de tabla (oculto en movil) --}}
                <div class="hidden md:grid grid-cols-[140px_1.1fr_1.6fr] gap-3.5 px-4 py-3 bg-bg-main border-b border-border text-[11px] uppercase tracking-wide font-semibold text-text-secondary">
                    <span>Catálogo</span>
                    <span>Especie ingresada</span>
                    <span>Estado / Acción</span>
                </div>

                {{-- Filas --}}
                @foreach($estadosRegistros as $id => $registro)
                    @php
                        $categoriaFila = match($registro['estado']) {
                            'Pendiente' => 'pendientes',
                            'Validado Técnicamente', 'Corregido por Sugerencia', 'Validación Manual por Curaduría' => 'resueltos',
                            'No Verificado' => 'no_verificados',
                            default => 'otros',
                        };
                        $filaVisible = $filtroTabla === 'todos' || $filtroTabla === $categoriaFila;
                    @endphp
                    @if($filaVisible)
                        <div wire:key="tax-row-{{ $id }}">
                            <x-gestionprestamosrecepciones::tax-row
                                :registroId="$id"
                                :catalogoId="$registro['catalogoId']"
                                :especieIngresada="$registro['especieIngresada']"
                                :estado="$registro['estado']"
                                :especieSugerida="$registro['especieSugerida']"
                                :especieCorregida="$registro['especieCorregida']"
                                :noCatalogado="$registro['noCatalogado']"
                                :motivoJustificacion="$registro['motivoJustificacion']"
                                :esDonacion="$esDonacion"
                                :advertencias="$registro['advertencias'] ?? []"
                            />
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Banners resumen --}}
            @if(!$esDonacion)
                @if($todosResueltos && $alertasJustificadas > 0)
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:heading>Matriz cargada con alertas justificadas</flux:heading>
                        <flux:text class="text-sm">
                            Todos los hallazgos no catalogados fueron justificados. Al enviar, las
                            <strong>{{ $alertasJustificadas }} alerta(s)</strong> se derivarán a la bandeja de revisión manual de curaduría.
                        </flux:text>
                    </flux:callout>
                @elseif($todosResueltos && $alertasJustificadas === 0)
                    <flux:callout variant="success" icon="check-circle">
                        <flux:heading>Matriz validada técnicamente</flux:heading>
                        <flux:text class="text-sm">
                            Todos los especímenes coinciden con el catálogo de referencia. La matriz está lista para el envío.
                        </flux:text>
                    </flux:callout>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        <flux:heading>{{ $pendientes->count() }} registro(s) requieren tu acción</flux:heading>
                        <flux:text class="text-sm">
                            Acepta las sugerencias de corrección y justifica las especies no catalogadas para habilitar el envío.
                        </flux:text>
                    </flux:callout>
                @endif
            @endif

        </div>
    @endif

</div>
