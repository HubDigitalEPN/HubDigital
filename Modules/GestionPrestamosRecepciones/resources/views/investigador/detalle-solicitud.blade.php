<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-solicitudes') }}">
            Mis Solicitudes
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $solicitud?->numero_solicitud ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if($successMessage)
        <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
    @endif

    @if(!$solicitud)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Info principal --}}
            <div class="lg:col-span-2 space-y-6">

                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <flux:heading size="xl" level="1" class="font-display">{{ $solicitud->titulo_estudio }}</flux:heading>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-gestionprestamosrecepciones::solicitud-status-badge :estado="$solicitud->estado" />
                            @if(in_array($solicitud->estado, ['borrador', 'observada']))
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:navigate
                                    href="{{ route('prestamos.investigador.solicitud.editar', $solicitud->id) }}"
                                >
                                    Editar
                                </flux:button>
                            @endif
                        </div>
                    </div>
                    <flux:separator />

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-text-secondary">N.º Solicitud</dt>
                            <dd class="font-mono font-medium text-text-primary">{{ $solicitud->numero_solicitud }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Institución</dt>
                            <dd class="text-text-primary">{{ $solicitud->institucion_adscripcion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Línea de Investigación</dt>
                            <dd class="text-text-primary">{{ $solicitud->linea_investigacion }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Duración</dt>
                            <dd class="text-text-primary">{{ $solicitud->duracion_propuesta_meses }} meses</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-text-secondary">Propósito del Préstamo</dt>
                            <dd class="text-text-primary mt-1">{{ $solicitud->proposito_prestamo }}</dd>
                        </div>
                        @if($solicitud->justificacion_extendida)
                            <div class="col-span-2">
                                <dt class="text-text-secondary">Justificación para Duración Extendida</dt>
                                <dd class="text-text-primary mt-1">{{ $solicitud->justificacion_extendida }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($solicitud->estado === 'observada' && $solicitud->comentario_curador)
                        <flux:separator />
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:heading size="sm">Observación del Curador</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ $solicitud->comentario_curador }}</flux:text>
                        </flux:callout>
                    @endif
                </div>

                {{-- Especimenes --}}
                @if($solicitud->items && $solicitud->items->count())
                    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
                        <div class="p-4">
                            <flux:heading size="lg" level="2" class="font-display">Especimenes Solicitados</flux:heading>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="!ps-4">Código de Espécimen</flux:table.column>
                                <flux:table.column class="px-4">Cantidad</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($solicitud->items as $item)
                                    <flux:table.row>
                                        <flux:table.cell class="!ps-4 px-4 py-3 font-mono text-sm whitespace-nowrap">{{ $item->especimen_codigo_externo }}</flux:table.cell>
                                        <flux:table.cell class="px-4 py-3">{{ $item->cantidad_solicitada }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif

                {{-- Acta de préstamo --}}
                @if($acta)
                    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg" level="2" class="font-display">Acta de Préstamo</flux:heading>
                            <x-gestionprestamosrecepciones::acta-status-badge :estado="$acta->estado" />
                        </div>
                        <flux:separator />

                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-text-secondary">N.º Préstamo</dt>
                                <dd class="font-mono font-medium text-text-primary">{{ $acta->numero_prestamo }}</dd>
                            </div>
                        </dl>

                        @if(in_array($acta->estado, ['pendiente_firma', 'pendiente_validacion']))
                            <flux:callout variant="info" icon="information-circle">
                                @if($acta->estado === 'pendiente_firma')
                                    El curador ha generado el acta. Descárgala, fírmala y sube el PDF firmado.
                                @else
                                    El acta firmada está en proceso de validación por el curador.
                                @endif
                            </flux:callout>
                        @endif

                        @if($acta->pdf_ruta)
                            <flux:button variant="ghost" icon="arrow-down-tray" size="sm"
                                href="{{ Storage::url($acta->pdf_ruta) }}" target="_blank">
                                Descargar Acta Original
                            </flux:button>
                        @endif

                        @if($acta->estado === 'pendiente_firma')
                            <flux:button variant="primary" icon="arrow-up-tray"
                                wire:click="$set('showUploadModal', true)">
                                Subir Acta Firmada
                            </flux:button>
                        @endif
                    </div>
                @endif

            </div>

            {{-- Timeline lateral --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2 h-fit">
                <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
                <flux:separator />
                <div class="mt-3">
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$solicitud->created_at->format('d/m/Y H:i')"
                        titulo="Solicitud creada"
                        :ultimo="$solicitud->estado === 'borrador'" />

                    @if(!in_array($solicitud->estado, ['borrador']))
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$solicitud->updated_at->format('d/m/Y H:i')"
                            titulo="Solicitud enviada"
                            :ultimo="$solicitud->estado === 'enviada'" />
                    @endif

                    @if($solicitud->estado === 'aprobada')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$solicitud->updated_at->format('d/m/Y H:i')"
                            titulo="Solicitud aprobada"
                            :ultimo="!$acta" />
                    @endif

                    @if($solicitud->estado === 'observada')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$solicitud->updated_at->format('d/m/Y H:i')"
                            titulo="Solicitud observada"
                            :descripcion="$solicitud->comentario_curador"
                            :ultimo="true" />
                    @endif
                </div>
            </div>

        </div>
    @endif

    {{-- Modal: subir acta firmada --}}
    <flux:modal wire:model="showUploadModal" class="max-w-md">
        <div class="space-y-4 p-2">
            <flux:heading size="lg">Subir Acta Firmada</flux:heading>
            <flux:text class="text-text-secondary text-sm">
                Sube el PDF del acta con tu firma. Máximo 10 MB, solo formato PDF.
            </flux:text>

            <flux:field>
                <flux:label>Archivo PDF</flux:label>
                <input type="file" wire:model="pdfFirmado" accept=".pdf"
                    class="block w-full text-sm text-text-secondary
                           file:mr-3 file:py-2 file:px-4 file:rounded-lg
                           file:border-0 file:text-sm file:font-medium
                           file:bg-science-blue file:text-white
                           hover:file:bg-blue-700" />
                <flux:error name="pdfFirmado" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showUploadModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="subirActa"
                    wire:loading.attr="disabled" wire:target="subirActa">
                    <flux:icon wire:loading wire:target="subirActa" name="arrow-path" class="animate-spin" />
                    Subir Acta
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
