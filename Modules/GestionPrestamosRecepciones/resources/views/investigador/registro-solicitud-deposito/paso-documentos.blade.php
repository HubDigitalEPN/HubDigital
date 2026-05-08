<div class="space-y-6">

    <div>
        <flux:heading size="lg" level="2">Carga de Documentación Oficial</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Adjunta los documentos requeridos según el origen y situación regulatoria declarados.
            Los documentos en formato PDF serán procesados automáticamente para extraer datos.
        </flux:text>
    </div>

    {{-- Intervención curatorial activa --}}
    @if($intervencionCuratoriaActiva)

        <flux:callout variant="warning" icon="pause-circle">
            <flux:heading>Solicitud retenida para Asesoría Curatorial</flux:heading>
            <flux:text>
                La carga documental está pausada. Un curador se pondrá en contacto contigo para asistirte en el proceso.
                Tu solicitud ha sido registrada con el estado <strong>Retenida para Asesoría Curatorial</strong>.
            </flux:text>
        </flux:callout>

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
                variant="ghost"
                size="sm"
                wire:click="solicitarIntervencion"
                wire:loading.attr="disabled"
                wire:target="solicitarIntervencion"
                class="text-warning border-warning/40 hover:bg-warning/10"
            >
                <flux:icon wire:loading wire:target="solicitarIntervencion" name="arrow-path" class="animate-spin size-4" />
                <flux:icon wire:loading.remove wire:target="solicitarIntervencion" name="hand-raised" class="size-4" />
                Solicitar intervención de curaduría
            </flux:button>
        </div>

    @endif

</div>
