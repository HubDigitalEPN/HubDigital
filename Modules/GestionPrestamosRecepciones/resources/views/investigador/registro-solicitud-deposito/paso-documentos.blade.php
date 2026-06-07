<div class="space-y-6" x-data="{ total: {{ count($documentosRequeridos) }} }">

    <div>
        <flux:heading size="lg" level="2" class="font-display">Carga de documentación oficial</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Adjunta los documentos requeridos en formato PDF.
        </flux:text>
    </div>

    {{-- Procesando documentos (polling activo) --}}
    @if($extraccionProcesando)
        <div wire:poll.500ms="verificarExtraccion" class="rounded-xl border border-science-blue/30 bg-science-blue/5 p-6 space-y-4">
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

        <div class="rounded-xl border border-warning/40 bg-warning/5 p-6 space-y-5">

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
                La carga de documentos ha sido pausada. El funcionario responsable revisará tu caso y se pondrá en contacto contigo directamente para orientarte en el proceso.
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

        {{-- Error de documentos faltantes --}}
        <flux:error name="documentos" />

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
                        => 'Formulario oficial de la colección que debes completar para iniciar tu solicitud. El ejemplo de referencia te muestra cómo luce correctamente llenado.',
                    'Formato solicitud donación'
                        => 'Formulario oficial para formalizar la transferencia permanente de los especímenes. Consulta el ejemplo de referencia para ver cómo debe quedar.',
                    'Copia de la autorización de recolección (MAE)'
                        => 'Documento emitido por el Ministerio del Ambiente y Agua (MAE) que autoriza la recolección en campo. Sube la copia del que ya tienes; el ejemplo te muestra cómo identificarlo.',
                    'Copia del permiso de movilización'
                        => 'Guía de movilización emitida por el MAE que ampara el traslado de los especímenes. Sube la copia del documento que tienes; el ejemplo te orienta sobre su formato.',
                    'Documento de explicación de motivos y/o carta de justificación (institucional o personal)'
                        => 'Carta redactada por ti o tu institución que explica por qué no cuentas con permisos del MAE. El ejemplo te muestra el tipo de contenido y tono esperados.',
                    'Documento de procedencia de los especimenes'
                        => 'Este documento explica y justifica cómo obtuviste los especímenes que deseas depositar. Debe incluir el origen, la procedencia y cualquier información que respalde la legalidad de su obtención.',
                    'Carta de cesión de derechos / origen lícito'
                        => 'Documento que certifica que los especímenes se donan voluntariamente y que su obtención fue legal. El ejemplo te orienta sobre su contenido.',
                    'Carta de delegación / justificación de tercero'
                        => 'Si el nombre en los documentos no coincide con tu perfil, adjunta esta carta explicando el motivo o autorizando a otra persona a realizar el trámite.',
                ];
            @endphp

            <div class="space-y-3">
                @foreach($documentosRequeridos as $docNombre)
                    @php $prop = $this->propiedadParaDocumento($docNombre); @endphp
                    <x-gestionprestamosrecepciones::dropzone
                        :nombre="$docNombre"
                        :propiedad="$prop"
                        :requerido="true"
                        :cargado="isset($documentosCargados[$docNombre])"
                        :archivo-nombre="$nombresArchivosOriginales[$docNombre] ?? null"
                        :plantilla="$plantillasDisponibles[$docNombre] ?? null"
                        :ayuda="$ayudas[$docNombre] ?? null"
                    />
                @endforeach
            </div>
        @else
            <div class="rounded-lg border border-dashed border-border p-8 text-center">
                <flux:icon name="document-text" class="size-8 text-text-secondary mx-auto mb-2" />
                <p class="text-sm text-text-secondary">Cargando documentos requeridos…</p>
            </div>
        @endif

        {{-- Sección de intervención curatorial --}}
        <div class="rounded-lg border border-border bg-bg-main p-4 space-y-3">
            <div class="flex items-start gap-3">
                <flux:icon name="question-mark-circle" class="size-5 text-text-secondary shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-semibold text-text-primary">¿No cuentas con ningún documento disponible?</p>
                    <p class="text-xs text-text-secondary mt-0.5">
                        Si no cuentas con ningún documento, puedes solicitar la intervención directa del funcionario responsable.
                        La carga documental se pausará y él se pondrá en contacto contigo para orientarte.
                    </p>
                </div>
            </div>
            <flux:button
                variant="outline"
                size="sm"
                wire:click="solicitarIntervencion"
                wire:loading.attr="disabled"
                wire:target="solicitarIntervencion"
                icon="hand-raised"
                icon:loading="arrow-path"
                class="text-warning border-warning/40 hover:bg-warning/10"
            >
                Solicitar asistencia
            </flux:button>
        </div>

    @endif

    @endif {{-- fin @if(!$extraccionProcesando) --}}

</div>
