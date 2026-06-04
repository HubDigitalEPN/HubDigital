@if($paso === 7)

    {{-- ── Pantalla de confirmación final ──────────────────────────────────────── --}}
    @php
        $esExito  = in_array($estadoFinal, ['Pendiente de Revisión por Curaduría', 'Registrada']);
        $esAviso  = in_array($estadoFinal, ['Pausada para Asesoría', 'Requiere Corrección']);
        $esError  = $estadoFinal === 'Rechazada';

        $icono  = $esExito ? 'check-circle' : ($esAviso ? 'exclamation-triangle' : 'x-circle');
        $colorIcono = $esExito ? 'text-success' : ($esAviso ? 'text-warning' : 'text-error');
        $colorFondo = $esExito ? 'bg-success/10' : ($esAviso ? 'bg-warning/10' : 'bg-error/10');
        $colorBorde = $esExito ? 'border-success/30' : ($esAviso ? 'border-warning/30' : 'border-error/30');

        $titulo = match($estadoFinal) {
            'Pendiente de Revisión por Curaduría' => 'Solicitud enviada · pendiente de revisión por curaduría',
            'Registrada'                          => 'Solicitud registrada exitosamente',
            'Pausada para Asesoría'   => 'Solicitud pausada — en espera de asesoría',
            'Requiere Corrección'                 => 'La solicitud requiere corrección',
            'Rechazada'                           => 'Solicitud rechazada por límite anual',
            default                               => 'Solicitud procesada',
        };

        $subtitulo = match($estadoFinal) {
            'Pendiente de Revisión por Curaduría' => 'Tu solicitud ha sido remitida al equipo curatorial. Recibirás notificación cuando inicie la revisión.',
            'Registrada'                          => 'El registro fue aceptado y archivado en la colección.',
            'Pausada para Asesoría'   => 'El funcionario responsable se pondrá en contacto contigo para guiar el caso documental.',
            'Requiere Corrección'                 => 'Algunos documentos no cumplen los requisitos. Revisa las observaciones y reenvía.',
            'Rechazada'                           => 'No es posible continuar. El cupo anual de depósitos ha sido alcanzado.',
            default                               => '',
        };
    @endphp

    <div class="py-8 flex flex-col items-center text-center gap-6">

        {{-- Icono hero --}}
        <div class="size-20 rounded-full {{ $colorFondo }} flex items-center justify-center">
            <flux:icon name="{{ $icono }}" class="size-10 {{ $colorIcono }}" />
        </div>

        {{-- Título y subtítulo --}}
        <div class="space-y-2">
            <flux:heading size="lg" level="2">{{ $titulo }}</flux:heading>
            <flux:text class="text-text-secondary max-w-md mx-auto">{{ $subtitulo }}</flux:text>
        </div>

        {{-- Mini ficha de resumen --}}
        <div class="w-full max-w-md text-left rounded-lg border {{ $colorBorde }} bg-bg-main divide-y divide-border">
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">ID de solicitud</span>
                <span class="font-mono text-xs font-medium text-text-primary">{{ $numeroSolicitud }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Tipo</span>
                <strong class="text-sm text-text-primary">{{ $tipoTramite }}</strong>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Origen</span>
                <span class="text-sm text-text-primary text-right">
                    {{ $origenRecoleccion }}
                    @if($situacionRegulatoria && $situacionRegulatoria !== 'Proviene de colección foránea')
                        <span class="text-text-secondary"> · {{ $situacionRegulatoria }}</span>
                    @endif
                </span>
            </div>
            @if($provincia)
                <div class="flex items-center justify-between px-4 py-3">
                    <span class="text-xs text-text-secondary">Provincia</span>
                    <span class="text-sm text-text-primary">
                        {{ $provincia }}@if($localidad) / {{ $localidad }}@endif
                    </span>
                </div>
            @endif
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-text-secondary">Estado</span>
                <x-gestionprestamosrecepciones::deposito-status-badge estado="{{ $estadoFinal }}" />
            </div>
        </div>

        {{-- Línea de tiempo --}}
        @if($esExito)
            <div class="w-full max-w-md text-left space-y-3">
                <flux:heading size="sm" level="3">Historial de la solicitud</flux:heading>
                <ol class="relative border-l border-border ml-3 space-y-4">
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Solicitud creada</p>
                    </li>
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Documentos cargados y validados</p>
                    </li>
                    @if($matrizCargada)
                        <li class="pl-5">
                            <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                            <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                            <p class="text-sm font-medium text-text-primary">Matriz Darwin Core validada</p>
                        </li>
                    @endif
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-success"></div>
                        <p class="text-xs text-text-secondary">{{ now()->format('d M Y · H:i') }}</p>
                        <p class="text-sm font-medium text-text-primary">Solicitud enviada al equipo curatorial</p>
                    </li>
                    <li class="pl-5">
                        <div class="absolute -left-1.5 mt-1 size-3 rounded-full bg-border border-2 border-border"></div>
                        <p class="text-xs text-text-secondary">Pendiente</p>
                        <p class="text-sm text-text-secondary">Revisión por curaduría · ETA 3 días hábiles</p>
                    </li>
                </ol>
            </div>
        @endif

        {{-- Acciones --}}
        <div class="flex gap-3 flex-wrap justify-center">
            <flux:button
                variant="filled"
                icon="document-text"
                wire:navigate
                href="{{ route('prestamos.investigador.mis-depositos') }}"
            >
                Ver mis solicitudes
            </flux:button>
            <a
                wire:navigate
                href="{{ route('prestamos.investigador.deposito.crear') }}"
                class="flex items-center gap-1.5 text-sm text-text-secondary hover:text-text-primary transition-colors"
            >
                <flux:icon name="plus" class="size-4" />
                Nueva solicitud
            </a>
        </div>

    </div>

@else

    {{-- ── Paso 5: Revisar y enviar ────────────────────────────────────────────── --}}
    <div class="space-y-6">

        <div>
            <flux:heading size="lg" level="2" class="font-display">Revisar y enviar</flux:heading>
            <flux:text class="text-text-secondary text-sm mt-1">
                Confirma la información antes de remitir la solicitud al equipo curatorial.
            </flux:text>
        </div>

        <flux:callout variant="info" icon="information-circle">
            <flux:text class="text-sm">
                Tu solicitud será evaluada por curaduría en máximo <strong>3 días hábiles</strong>.
                Recibirás notificación en cada cambio de estado.
            </flux:text>
        </flux:callout>

        {{-- Resumen de la solicitud --}}
        <div class="space-y-3">
            <flux:heading size="sm" level="3">Resumen de la solicitud</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2">

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Tipo de trámite</p>
                    <p class="text-sm font-semibold text-text-primary">{{ $tipoTramite }}</p>
                </div>

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Origen</p>
                    <p class="text-sm font-semibold text-text-primary">{{ $origenRecoleccion }}</p>
                    @if($situacionRegulatoria && $situacionRegulatoria !== 'Proviene de colección foránea')
                        <p class="text-xs text-text-secondary">{{ $situacionRegulatoria }}</p>
                    @endif
                </div>

                @if($provincia)
                    <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                        <p class="text-xs text-text-secondary">Provincia</p>
                        <p class="text-sm font-semibold text-text-primary">{{ $provincia }}</p>
                        @if($localidad)
                            <p class="text-xs text-text-secondary">{{ $localidad }}</p>
                        @endif
                    </div>
                @endif

                <div class="rounded-lg border border-border bg-bg-main p-3 space-y-0.5">
                    <p class="text-xs text-text-secondary">Solicitante</p>
                    <p class="text-sm font-semibold text-text-primary">{{ auth()->user()->name }}</p>
                    @if($nombreEnDocumento && $nombreEnDocumento !== auth()->user()->name)
                        <p class="text-xs text-text-secondary">Doc: {{ $nombreEnDocumento }}</p>
                    @endif
                </div>

                @if(!empty($documentosCargados))
                    <div class="sm:col-span-2 rounded-lg border border-border bg-bg-main p-3 space-y-1.5">
                        <p class="text-xs text-text-secondary">Documentos adjuntados</p>
                        <ul class="space-y-1">
                            @foreach($documentosCargados as $nombre => $ruta)
                                <li class="flex items-center gap-2 text-sm text-text-primary">
                                    <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                    {{ $nombre }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($matrizCargada && $estadoMatriz)
                    <div class="sm:col-span-2 rounded-lg border border-border bg-bg-main p-3 space-y-1.5">
                        <p class="text-xs text-text-secondary">Matriz de especies</p>
                        <div class="flex items-center gap-3 flex-wrap">
                            <p class="text-sm font-semibold text-text-primary">{{ $archivoMatrizNombre ?: 'Cargada' }}</p>
                            <x-gestionprestamosrecepciones::matrix-status-badge :estado="$estadoMatriz" />
                        </div>
                        <p class="text-xs text-text-secondary">{{ $totalRegistros }} registro(s)</p>
                    </div>
                @endif

            </div>
        </div>

        {{-- Declaración jurada --}}
        <div class="rounded-lg border border-border bg-bg-main p-4 space-y-3">
            <flux:heading size="sm" level="3">Declaración del solicitante</flux:heading>
            <flux:checkbox
                wire:model="declaracionAceptada"
                label="Declaro bajo juramento que la información proporcionada y los documentos cargados son verídicos, y que cumplo con la normativa nacional e institucional vigente para el manejo de especímenes biológicos."
            />
            <flux:error name="declaracionAceptada" />
        </div>

    </div>

@endif
