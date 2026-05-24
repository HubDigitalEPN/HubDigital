<div class="space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.investigador.mis-depositos') }}">
            Mis depósitos
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $deposito?->numero ?? 'Detalle' }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if(!$deposito)
        <flux:callout variant="danger" icon="exclamation-triangle">Solicitud no encontrada.</flux:callout>
    @else
        @php
            $badgeVariant = match($deposito->estado) {
                'Pendiente de Revisión por Curaduría' => 'info',
                'Retenida para Asesoría Curatorial'   => 'warning',
                'Rechazada'                           => 'danger',
                default                               => 'ghost',
            };
        @endphp

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Columna principal --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Encabezado --}}
                <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <flux:heading size="xl" level="1" class="font-display">
                                Solicitud de {{ mb_strtolower($deposito->tipo_tramite) }}
                            </flux:heading>
                            <p class="font-mono text-xs text-text-secondary mt-1">{{ $deposito->numero }}</p>
                        </div>
                        <flux:badge :variant="$badgeVariant">{{ $deposito->estado }}</flux:badge>
                    </div>
                    <flux:separator />

                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-4 text-sm">
                        <div>
                            <dt class="text-text-secondary">Tipo de trámite</dt>
                            <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->tipo_tramite }}</dd>
                        </div>
                        <div>
                            <dt class="text-text-secondary">Fecha de registro</dt>
                            <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        @if($deposito->origen_recoleccion)
                            <div>
                                <dt class="text-text-secondary">Origen de recolección</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->origen_recoleccion }}</dd>
                            </div>
                        @endif
                        @if($deposito->situacion_regulatoria)
                            <div>
                                <dt class="text-text-secondary">Situación regulatoria</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->situacion_regulatoria }}</dd>
                            </div>
                        @endif
                        @if($deposito->provincia_origen)
                            <div>
                                <dt class="text-text-secondary">Provincia de origen</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->provincia_origen }}</dd>
                            </div>
                        @endif
                        @if($deposito->localidad)
                            <div>
                                <dt class="text-text-secondary">Localidad</dt>
                                <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->localidad }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Datos integrados de documentación --}}
                @php
                    $tieneDatos = $deposito->nro_permiso_recoleccion
                        || $deposito->nro_permiso_movilizacion
                        || $deposito->grupo_animal
                        || $deposito->origen_donacion;
                @endphp

                @if($tieneDatos)
                    <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                        <flux:heading size="lg" level="2" class="font-display">Datos de documentación oficial</flux:heading>
                        <flux:separator />
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-4 text-sm">
                            @if($deposito->nro_permiso_recoleccion)
                                <div>
                                    <dt class="text-text-secondary">N.º permiso recolección</dt>
                                    <dd class="font-mono font-medium text-text-primary mt-0.5">{{ $deposito->nro_permiso_recoleccion }}</dd>
                                </div>
                            @endif
                            @if($deposito->nro_permiso_movilizacion)
                                <div>
                                    <dt class="text-text-secondary">N.º permiso movilización</dt>
                                    <dd class="font-mono font-medium text-text-primary mt-0.5">{{ $deposito->nro_permiso_movilizacion }}</dd>
                                </div>
                            @endif
                            @if($deposito->grupo_animal)
                                <div>
                                    <dt class="text-text-secondary">Grupo animal</dt>
                                    <dd class="font-medium text-text-primary mt-0.5 italic font-serif">{{ $deposito->grupo_animal }}</dd>
                                </div>
                            @endif
                            @if($deposito->origen_donacion)
                                <div class="col-span-2">
                                    <dt class="text-text-secondary">Origen de donación</dt>
                                    <dd class="font-medium text-text-primary mt-0.5">{{ $deposito->origen_donacion }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Alerta si retenida --}}
                @if($deposito->estado === 'Retenida para Asesoría Curatorial')
                    <div class="rounded-xl border border-warning/40 bg-warning/5 p-5 space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-warning/15">
                                <flux:icon name="pause-circle" class="size-5 text-warning" />
                            </div>
                            <p class="text-sm font-semibold text-text-primary">En espera de asesoría curatorial</p>
                        </div>
                        <p class="text-sm text-text-secondary">
                            Un curador revisará tu caso y se pondrá en contacto contigo directamente. No necesitas realizar ninguna acción por ahora.
                        </p>
                    </div>
                @endif

            </div>

            {{-- Timeline lateral --}}
            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-3 h-fit">
                <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
                <flux:separator />

                <div class="mt-3 space-y-0">
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$deposito->created_at->format('d/m/Y H:i')"
                        titulo="Solicitud registrada"
                        :ultimo="$deposito->estado === 'En Borrador'" />

                    @if($deposito->estado === 'Retenida para Asesoría Curatorial')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Retenida para asesoría curatorial"
                            descripcion="Sin documentación disponible. Curador notificado."
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Pendiente de Revisión por Curaduría')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Documentación cargada"
                            :ultimo="true" />
                    @endif

                    @if($deposito->estado === 'Rechazada')
                        <x-gestionprestamosrecepciones::timeline-event
                            :fecha="$deposito->updated_at->format('d/m/Y H:i')"
                            titulo="Solicitud rechazada"
                            :ultimo="true" />
                    @endif
                </div>
            </div>

        </div>
    @endif

</div>
