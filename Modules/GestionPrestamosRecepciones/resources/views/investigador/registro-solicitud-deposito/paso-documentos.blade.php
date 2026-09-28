<div class="space-y-5" x-data="{ total: {{ count($documentosRequeridos) }} }">
    @if(in_array('validando', $firmasElectronicas, true) || in_array('analizando', $validacionArchivos, true))
        <span wire:poll.1s="actualizarFirmas" class="sr-only">Comprobando documentos y firmas electrónicas</span>
    @endif

    <div class="hub-wizard-copy-header border-b border-blue-navy/10 pb-4">
        <flux:heading size="lg" level="2" class="font-display tracking-tight text-blue-navy">{{ \App\Support\WizardCopy::text('documentos.titulo') }}</flux:heading>
        <flux:text class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">{{ !empty($documentosRequeridos) ? \App\Support\WizardCopy::text('documentos.intro_con_archivos') : \App\Support\WizardCopy::text('documentos.intro_sin_archivos') }}</flux:text>
    </div>

    {{-- Procesando documentos (polling activo) --}}
    @if($extraccionProcesando)
        <div wire:poll.500ms="verificarExtraccion" class="space-y-4 rounded-xl border border-science-blue/30 bg-science-blue/5 p-6" role="status" aria-live="polite">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-science-blue/15">
                    <flux:icon name="arrow-path" class="size-5 text-science-blue animate-spin" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-text-primary">Analizando documentos…</p>
                    <p class="text-xs text-text-secondary">
                        {{ count($documentosProcesados) }} de {{ count($documentosCargados) }} documentos procesados.
                    </p>
                </div>
            </div>
            <div class="space-y-2">
                @foreach($documentosCargados as $nombre => $ruta)
                    @php $procesado = in_array($nombre, $documentosProcesados, true); @endphp
                    <div class="flex items-center gap-2 text-sm {{ $procesado ? 'text-text-primary' : 'text-text-secondary' }}">
                        @if($procesado)
                            <flux:icon name="check-circle" class="size-4 shrink-0 text-success" />
                        @else
                            <flux:icon name="arrow-path" class="size-4 shrink-0 text-science-blue animate-spin" />
                        @endif
                        <span>{{ $nombre }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @else

    {{-- Intervención curatorial activa --}}
    @if($intervencionCuratoriaActiva)

        <div wire:key="card-intervencion" class="rounded-xl border border-warning/40 bg-warning/5 p-6 space-y-5">

            {{-- Estado --}}
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning/15">
                    <flux:icon name="pause-circle" class="size-5 text-warning" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-text-primary">Solicitud pausada — en espera de asesoría</p>
                    <p class="text-xs text-text-secondary">N.º {{ $numeroSolicitud }}</p>
                </div>
            </div>

            {{-- Qué pasó --}}
            <p class="text-sm text-text-secondary">
                {{ \App\Support\WizardCopy::text('documentos.pausa_explicacion') }}
            </p>

            {{-- Próximos pasos --}}
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">¿Qué sigue?</p>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="check-circle" class="size-4 text-success shrink-0 mt-0.5" />
                        Tu solicitud quedó registrada con el estado <strong>Pausada para asesoría</strong>.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="envelope" class="size-4 text-science-blue shrink-0 mt-0.5" />
                        Recibirás una notificación cuando el funcionario responsable inicie el contacto contigo.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="clock" class="size-4 text-text-secondary shrink-0 mt-0.5" />
                        No es necesario que hagas nada más por ahora.
                    </li>
                </ul>
            </div>

            <flux:button icon="arrow-left" href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate>
                Ver mis depósitos
            </flux:button>

        </div>

    @else

    <div wire:key="formulario-documentos" class="space-y-4">

        {{-- Error de documentos faltantes --}}
        <flux:error name="documentos" />

        @if($estadoValidacionContenido === 'rechazado' || !empty($erroresDocumentales))
            @php $falloTecnico = in_array($estadoValidacionContenido, ['error_procesamiento', 'error_modelo', 'error_cola'], true); @endphp
            <flux:callout
                variant="danger"
                icon="x-circle"
                :heading="$falloTecnico ? 'No fue posible completar la lectura' : 'Los documentos no superaron la validación de contenido'"
            >
                <p class="mb-2 text-sm">
                    {{ $falloTecnico
                        ? 'El expediente y los archivos cargados permanecen guardados para que puedas volver a ejecutar el análisis.'
                        : 'HubDigital revisó la estructura, códigos, titulares, proyecto y fechas; no se basa en el nombre del archivo.' }}
                </p>
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    @foreach($erroresDocumentales as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs">
                    {{ $falloTecnico
                        ? 'Vuelve a pulsar «Validar documentos». Reemplaza un archivo únicamente si el mensaje indica que el PDF no puede leerse.'
                        : 'Reemplaza el archivo incorrecto y vuelve a ejecutar el análisis.' }}
                </p>
            </flux:callout>
        @endif

        @if(!empty($advertenciasDocumentales))
            <flux:callout variant="warning" icon="exclamation-triangle" heading="Aspectos que requieren confirmación humana">
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    @foreach($advertenciasDocumentales as $advertencia)
                        <li>{{ $advertencia }}</li>
                @endforeach
                </ul>
                <div class="mt-4">
                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="solicitarRevisionDocumental"
                        wire:loading.attr="disabled"
                        wire:target="solicitarRevisionDocumental"
                        icon="user-group"
                    >
                        Solicitar revisión documental
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        {{-- Dropzones dinámicas --}}
        @if(!empty($documentosRequeridos))
            @php
                $plantillas = [
                    'Formato solicitud depósito' => asset('plantillas/depositos/formato-solicitud-deposito.pdf'),
                    'Formato solicitud donación' => asset('plantillas/depositos/formato-solicitud-donacion.pdf'),
                    'Copia de la autorización de recolección (MAE)' => asset('plantillas/depositos/autorizacion-mae-ejemplo.pdf'),
                    'Copia del permiso de movilización' => asset('plantillas/depositos/permiso-movilizacion-ejemplo.pdf'),
                    'Documento de explicación de motivos y/o carta de justificación (institucional o personal)' => asset('plantillas/depositos/carta-justificacion-ejemplo.pdf'),
                    'Documento de procedencia de los especimenes' => asset('plantillas/depositos/carta-procedencia-ejemplo.pdf'),
                    'Carta de cesión de derechos / origen lícito' => asset('plantillas/depositos/carta-cesion-ejemplo.pdf'),
                    'Carta de delegación / justificación de tercero' => asset('plantillas/depositos/carta-delegacion-ejemplo.pdf'),
                ];
                $plantillasDisponibles = array_intersect_key($plantillas, array_flip($documentosRequeridos));

                $ayudas = [
                    'Formato solicitud depósito'
                        => \App\Support\WizardCopy::text('documentos.ayuda_formato_deposito'),
                    'Formato solicitud donación'
                        => \App\Support\WizardCopy::text('documentos.ayuda_formato_donacion'),
                    'Copia de la autorización de recolección (MAE)'
                        => \App\Support\WizardCopy::text('documentos.ayuda_autorizacion'),
                    'Copia del permiso de movilización'
                        => \App\Support\WizardCopy::text('documentos.ayuda_movilizacion'),
                    'Documento de explicación de motivos y/o carta de justificación (institucional o personal)'
                        => \App\Support\WizardCopy::text('documentos.ayuda_justificacion'),
                    'Documento de procedencia de los especimenes'
                        => \App\Support\WizardCopy::text('documentos.ayuda_procedencia'),
                    'Carta de cesión de derechos / origen lícito'
                        => \App\Support\WizardCopy::text('documentos.ayuda_cesion'),
                    'Carta de delegación / justificación de tercero'
                        => \App\Support\WizardCopy::text('documentos.ayuda_delegacion'),
                ];
            @endphp

            <section class="hub-wizard-document-grid space-y-3" aria-labelledby="documentos-requeridos-titulo">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="documentos-requeridos-titulo" class="text-sm font-semibold text-blue-navy">Archivos requeridos</h3>
                        <p class="mt-1 text-xs leading-5 text-text-secondary">{{ \App\Support\WizardCopy::text('documentos.ayuda_archivos') }}</p>
                    </div>
                    <p class="font-mono text-xs text-text-secondary">{{ count($documentosCargados) }}/{{ count($documentosRequeridos) }} cargados</p>
                </div>
                @foreach($documentosRequeridos as $docNombre)
                    @php $prop = $this->propiedadParaDocumento($docNombre); @endphp
                    <x-gestionprestamosrecepciones::dropzone
                        :nombre="$docNombre"
                        :propiedad="$prop"
                        :requerido="true"
                        :cargado="isset($documentosCargados[$docNombre])"
                        :rechazo="$rechazosDocumentos[$docNombre] ?? null"
                        :estado-firma="$firmasElectronicas[$docNombre] ?? null"
                        :estado-archivo="$validacionArchivos[$docNombre] ?? null"
                        :validando-firma="($firmasElectronicas[$docNombre] ?? null) === 'validando'"
                        :archivo-nombre="$nombresArchivosOriginales[$docNombre] ?? null"
                        :plantilla="$plantillasDisponibles[$docNombre] ?? null"
                        :ayuda="$ayudas[$docNombre] ?? null"
                        role="button"
                        tabindex="0"
                        aria-label="Cargar documento: {{ $docNombre }}"
                        x-on:keydown.enter.prevent="if (!cargado) $refs.fileInput.click()"
                        x-on:keydown.space.prevent="if (!cargado) $refs.fileInput.click()"
                    />
                @endforeach
            </section>
            @if(!$analisisDocumentalCompletado && array_intersect($firmasElectronicas, ['revocacion_no_comprobable', 'verificacion_no_disponible', 'almacen_incompleto', 'no_verificado', 'validando']) !== [])
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-error/30 bg-error/5 px-3 py-2 text-xs text-text-primary" role="alert">
                    <p>Cada PDF conserva su resultado de firma. Puedes reintentar las comprobaciones pendientes o interrumpidas; espera a que termine un intento que acaba de iniciar.</p>
                    <flux:button size="xs" variant="outline" icon="arrow-path" wire:click="repetirVerificacionFirmas" wire:loading.attr="disabled" wire:target="repetirVerificacionFirmas">Volver a comprobar</flux:button>
                </div>
            @endif
        @else
            <div class="flex items-start gap-3 rounded-lg border border-success/30 bg-success/5 p-4">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <flux:icon name="check-circle" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-text-primary">Sin archivos requeridos</p>
                    <p class="mt-1 text-sm leading-5 text-text-secondary">{{ \App\Support\WizardCopy::text('documentos.sin_archivos_explicacion') }}</p>
                </div>
            </div>
        @endif

        {{-- Sección de intervención curatorial --}}
        @if(!empty($documentosRequeridos))
        <div class="flex flex-col gap-3 border-t border-blue-navy/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <flux:icon name="question-mark-circle" class="size-5 text-text-secondary shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-semibold text-text-primary">¿No cuentas con ningún documento disponible?</p>
                    <p class="text-xs text-text-secondary mt-0.5">
                        {{ \App\Support\WizardCopy::text('documentos.asistencia_explicacion') }}
                    </p>
                </div>
            </div>
            <flux:modal.trigger name="asistencia-depositos">
                <flux:button variant="outline" size="sm" icon="hand-raised" class="shrink-0 border-warning/40 text-warning hover:bg-warning/10">
                    {{ \App\Support\WizardCopy::text('documentos.asistencia_accion') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
        @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.asistencia-modal')
        @endif

    </div>{{-- fin wire:key="formulario-documentos" --}}

    @endif

    @endif {{-- fin @if(!$extraccionProcesando) --}}

    @if(!$extraccionProcesando && $analisisDocumentalCompletado)
        @include('gestionprestamosrecepciones::investigador.registro-solicitud-deposito.paso-firmas')
    @endif

</div>
