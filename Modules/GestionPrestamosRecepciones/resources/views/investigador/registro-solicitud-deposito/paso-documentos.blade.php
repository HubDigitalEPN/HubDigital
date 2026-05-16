<div class="space-y-6" x-data="{ total: {{ count($documentosRequeridos) }} }">

    <div>
        <flux:heading size="lg" level="2" class="font-display">Carga de documentación oficial</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Adjunta los documentos requeridos según el origen y situación regulatoria declarados.
            Los documentos en formato PDF serán procesados automáticamente para extraer datos.
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
                    <p class="text-sm font-semibold text-text-primary">Solicitud retenida para asesoría curatorial</p>
                    <p class="text-xs text-text-secondary">N.º {{ $numeroSolicitud }}</p>
                </div>
            </div>

            {{-- Qué pasó --}}
            <p class="text-sm text-text-secondary">
                La carga de documentos ha sido pausada. Un curador revisará tu caso y se pondrá en contacto contigo directamente para orientarte en el proceso.
            </p>

            {{-- Próximos pasos --}}
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">¿Qué sigue?</p>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="check-circle" class="size-4 text-success shrink-0 mt-0.5" />
                        Tu solicitud quedó registrada con el estado <strong>Retenida para asesoría curatorial</strong>.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="envelope" class="size-4 text-science-blue shrink-0 mt-0.5" />
                        Recibirás una notificación cuando el curador inicie el contacto contigo.
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-primary">
                        <flux:icon name="clock" class="size-4 text-text-secondary shrink-0 mt-0.5" />
                        No es necesario que hagas nada más por ahora.
                    </li>
                </ul>
            </div>

        </div>

    @else

        {{-- Error de documentos faltantes --}}
        <flux:error name="documentos" />

        {{-- Dropzones dinámicas --}}
        @if(!empty($documentosRequeridos))
            <div class="space-y-3">
                @foreach($documentosRequeridos as $docNombre)
                    @php $prop = $this->propiedadParaDocumento($docNombre); @endphp
                    <x-gestionprestamosrecepciones::dropzone
                        :nombre="$docNombre"
                        :propiedad="$prop"
                        :requerido="true"
                        :cargado="isset($documentosCargados[$docNombre])"
                        :archivo-nombre="$nombresArchivosOriginales[$docNombre] ?? null"
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
                        Si carecés totalmente de documentación, puedes solicitar la intervención directa de un curador.
                        La carga documental se pausará y el curador iniciará el contacto contigo.
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
                Solicitar intervención de curaduría
            </flux:button>
        </div>

    @endif

    @endif {{-- fin @if(!$extraccionProcesando) --}}

</div>
