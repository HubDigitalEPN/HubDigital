<div class="space-y-5">
    <div class="border-b border-border pb-3">
        <flux:heading size="lg" level="2">Solicitud de recepción</flux:heading>
        <flux:text class="mt-1 text-sm text-text-secondary">Completa el oficio institucional. Revísalo en PDF y fírmalo con tu certificado electrónico antes de continuar.</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:input wire:model="solicitudNombrePermiso" label="Nombre de la persona titular del permiso de colección" required />
        <flux:input wire:model="solicitudCedula" label="Número de cédula" inputmode="numeric" maxlength="10" required />
        <flux:input wire:model="solicitudCargo" label="Cargo o posición en el proyecto" required />
        <flux:input wire:model="solicitudInstitucion" label="Empresa o institución" required />
        <flux:input wire:model="solicitudGrupo" label="Grupo de especímenes a depositar" placeholder="Ej. macroinvertebrados acuáticos" required />
        <flux:input wire:model="solicitudProyecto" label="Título del proyecto" required />
        <flux:input wire:model="solicitudCorreo" type="email" label="Correo electrónico" required />
        <flux:input wire:model="solicitudOficio" label="Número de oficio (opcional)" />
    </div>

    <div class="rounded-lg border border-border bg-bg-main p-4 text-sm leading-6 text-text-primary">
        <p class="font-semibold">Así se redactará la solicitud</p>
        <p class="mt-2">Yo, <strong>{{ $solicitudNombrePermiso ?: 'nombre del titular' }}</strong>, con número de cédula de identidad <strong>{{ $solicitudCedula ?: '##########' }}</strong>, en mi calidad de <strong>{{ $solicitudCargo ?: 'cargo' }}</strong>, solicito autorice la recepción de los especímenes de <strong>{{ $solicitudGrupo ?: 'grupo de especímenes' }}</strong>, que fueron recolectados en el proyecto <strong>{{ $solicitudProyecto ?: 'título del proyecto' }}</strong>, cuyos detalles indico en tabla compartida, vía Google Drive, conforme requisitos establecidos en la página web del Laboratorio.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:button wire:click="guardarSolicitudInicial" wire:loading.attr="disabled" wire:target="guardarSolicitudInicial" icon="document-text">
            {{ $solicitudId ? 'Guardar cambios del oficio' : 'Generar solicitud PDF' }}
        </flux:button>
        @if($solicitudId)
            <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1]) }}" target="_blank" class="text-sm font-semibold text-science-blue hover:underline">Revisar PDF generado ↗</a>
        @endif
    </div>

    @if($solicitudId)
        <div class="rounded-lg border border-bio-green/30 bg-surface p-4">
            @if($solicitudFirmada)
                <p class="text-sm font-semibold text-bio-green">Solicitud firmada y validada criptográficamente.</p>
                <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" class="mt-2 inline-block text-sm text-science-blue hover:underline">Ver PDF firmado ↗</a>
            @else
                <div x-data="hubDigitalFirmador({uploadUrl: @js(route('depositos.solicitud.firmar', ['id' => $solicitudId]))})" x-on:keydown.escape.window="if (dialogo) cerrarDialogo()">
                    <flux:button variant="primary" icon="lock-closed" x-ref="abrirFirma" x-on:click="abrirDialogo()">Firmar PDF</flux:button>
                    <div x-show="dialogo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-blue-navy/75 p-4" role="presentation">
                        <section role="dialog" aria-modal="true" aria-labelledby="titulo-firma-solicitud" x-on:click.outside="cerrarDialogo()" class="w-full max-w-xl space-y-4 rounded-xl border border-border bg-surface p-5 text-text-primary shadow-2xl">
                            <div class="flex items-start justify-between gap-3">
                                <div><h3 id="titulo-firma-solicitud" class="text-lg font-semibold">Firma electrónica del oficio</h3><p class="mt-1 text-xs leading-5 text-text-secondary">Selecciona tu certificado y escribe su clave. Java firmará el PDF que acabas de revisar.</p></div>
                                <button type="button" x-on:click="cerrarDialogo()" class="rounded border border-border px-2 text-xl" aria-label="Cerrar diálogo de firma">×</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Archivo del certificado (.p12 o .pfx)</flux:label>
                                    <input x-ref="certificado" type="file" accept=".p12,.pfx,application/x-pkcs12" class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Clave del certificado</flux:label>
                                    <flux:input x-ref="clave" type="password" autocomplete="off" />
                                </flux:field>
                            </div>
                            <p x-show="error" x-text="error" class="text-sm text-error" role="alert" aria-live="assertive"></p>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:button variant="ghost" x-on:click="cerrarDialogo()" x-bind:disabled="estado === 'procesando'">Cancelar</flux:button>
                                <flux:button variant="primary" icon="lock-closed" x-on:click="firmar" x-bind:disabled="estado === 'procesando'">
                                    <span x-show="estado !== 'procesando'">Firmar solicitud</span>
                                    <span x-show="estado === 'procesando'" x-text="progreso"></span>
                                </flux:button>
                            </div>
                            <p class="text-xs leading-5 text-text-secondary">Java firma y verifica el PDF. La clave y el archivo .p12 se usan temporalmente; R2 conserva únicamente el PDF firmado.</p>
                        </section>
                    </div>
                </div>
            @endif
        </div>
    @endif
    <flux:error name="solicitudFirmada" />
</div>
