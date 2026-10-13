<div class="hub-request-letter space-y-3">
    <div class="border-b border-border pb-2">
        <flux:heading size="lg" level="2">Solicitud de recepción</flux:heading>
        <flux:text class="mt-1 text-xs text-text-secondary">Completa, revisa y firma el oficio antes de continuar. Los campos con un pequeño triángulo rojo son obligatorios.</flux:text>
    </div>
    <div class="hub-request-fields grid gap-3 md:grid-cols-2">
        <flux:input wire:model.live.blur="solicitudNombrePermiso" label="Nombre de la persona titular del permiso de colección" maxlength="200" required />
        <div class="hub-identification-fields">
            <flux:select wire:model.live="solicitudTipoIdentificacion" label="Tipo de identificación" required>
                <option value="cedula">Cédula</option><option value="ruc">RUC</option><option value="pasaporte">Pasaporte</option>
            </flux:select>
            <flux:input wire:model.live.blur="solicitudCedula" label="Identificación" :inputmode="$solicitudTipoIdentificacion === 'pasaporte' ? 'text' : 'numeric'" :maxlength="$solicitudTipoIdentificacion === 'cedula' ? 10 : ($solicitudTipoIdentificacion === 'ruc' ? 13 : 20)" required />
        </div>
        <flux:select wire:model.live="solicitudCargo" label="Cargo o posición en el proyecto" required>
            <option value="">Selecciona un cargo</option>
            @foreach($cargosCatalogo as $cargo)<option value="{{ $cargo }}">{{ $cargo }}</option>@endforeach
        </flux:select>
        <flux:select wire:model.live="solicitudInstitucion" label="Empresa o institución" required>
            <option value="">Selecciona una institución</option>
            @foreach($institucionesCatalogo as $institucion)<option value="{{ $institucion }}">{{ $institucion }}</option>@endforeach
        </flux:select>
        <flux:select wire:model.live="solicitudGrupo" label="Grupo de especímenes a depositar" required>
            <option value="">Selecciona un grupo</option>
            @foreach(config('depositos.grupos_especimenes') as $grupo)<option value="{{ $grupo }}">{{ $grupo }}</option>@endforeach
        </flux:select>
        <flux:input wire:model.live.blur="solicitudProyecto" label="Título del proyecto" maxlength="300" required />
        <flux:input wire:model.live.blur="solicitudCorreo" type="email" label="Correo electrónico" maxlength="250" required />
        <flux:input wire:model.live.blur="solicitudOficio" label="Número de oficio" maxlength="100" required />
    </div>
    @if($solicitudIdentificacionConsultada === ($solicitudTipoIdentificacion.':'.trim($solicitudCedula)))
        <p class="text-xs text-text-secondary" role="status">
            @switch($solicitudIdentificacionConsulta['estado'] ?? '')
                @case('verificado') RUC verificado en el SRI: {{ $solicitudIdentificacionConsulta['razon_social'] }} · {{ $solicitudIdentificacionConsulta['estado_ruc'] }}. @break
                @case('no_disponible') No se pudo consultar el SRI en este momento. @break
                @case('cedula_local_valida') Formato de cédula válido. @break
                @case('pasaporte_formato_valido') Formato de pasaporte correcto. @break
            @endswitch
        </p>
    @endif
    <details class="rounded-lg border border-border bg-bg-main px-3 py-2">
        <summary class="cursor-pointer text-sm font-semibold">Texto del oficio · revisar</summary>
        <p class="mt-2 text-sm leading-5">{{ $this->textoSolicitudSugerido() }}</p>
    </details>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2" aria-live="polite">
        @if($solicitudOficioAnteriorFirmado)
            <span class="text-xs text-text-secondary">El oficio anterior tiene valores que deben actualizarse. Selecciona las opciones vigentes y firma el nuevo PDF.</span>
            <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline">Ver PDF firmado anterior ↗</a>
        @endif
        <span wire:loading class="text-xs text-text-secondary">Guardando…</span>
        <span wire:loading.remove class="text-xs text-text-secondary">{{ $solicitudCambiosPendientes || ! $solicitudId ? 'Completa los campos para generar el PDF.' : 'Datos guardados automáticamente.' }}</span>
        @if(! $solicitudId || $solicitudCambiosPendientes)
            <flux:button size="sm" wire:click="guardarSolicitudInicial" wire:loading.attr="disabled" icon="document-text">Preparar PDF</flux:button>
        @else
            <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline">Revisar PDF generado ↗</a>
            @if($solicitudFirmada)
                <span class="text-sm font-semibold text-bio-green">Solicitud firmada y validada criptográficamente.</span>
                <a data-oficio-signed-link href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline">Ver PDF firmado ↗</a>
            @else
                <div wire:key="firma-solicitud-{{ $solicitudId }}-{{ $solicitudDocumentoVersion }}" x-data="hubDigitalFirmador({uploadUrl: @js(route('depositos.solicitud.firmar', ['id' => $solicitudId])), originalUrl: @js(route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1])), statusUrl: @js(route('depositos.solicitud.estado-firma', ['id' => $solicitudId])), documentVersion: {{ $solicitudDocumentoVersion }}, onSigned: () => $wire.confirmarSolicitudFirmada()})" x-on:keydown.escape.window="if (dialogo) cerrarDialogo()">
                    <flux:button size="sm" variant="primary" icon="lock-closed" x-ref="abrirFirma" x-on:click="abrirDialogo()" wire:loading.attr="disabled">Firmar PDF</flux:button>
                    <div x-show="dialogo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-blue-navy/75 p-4" role="presentation">
                        <section role="dialog" aria-modal="true" aria-labelledby="titulo-firma-solicitud" x-trap.inert.noscroll="dialogo" x-on:click.outside="cerrarDialogo()" class="hub-signature-dialog space-y-3 rounded-xl border border-border bg-surface p-4 text-text-primary shadow-2xl">
                            <div class="flex items-start justify-between gap-3">
                                <div><h3 id="titulo-firma-solicitud" class="text-lg font-semibold">Firma electrónica del oficio</h3><p class="mt-1 text-xs text-text-secondary">Selecciona tu certificado y escribe su clave.</p></div>
                                <button type="button" x-on:click="cerrarDialogo()" x-bind:disabled="estado === 'procesando'" class="cursor-pointer rounded border border-border px-2 text-xl" aria-label="Cerrar diálogo de firma">×</button>
                            </div>
                            <flux:select x-model="metodo" label="Método de firma" x-bind:disabled="estado === 'procesando'">
                                <option value="archivo">Certificado .p12 o .pfx</option><option value="token">Token USB</option>
                            </flux:select>
                            <p x-show="metodo === 'token'" class="text-xs text-text-secondary">Conecta tu token y abre el conector HubDigital en este equipo. <a href="{{ route('depositos.firmador.token.kit') }}" class="underline text-science-blue">Descargar conector USB</a></p>
                            <flux:field x-show="metodo === 'archivo'">
                                <flux:label for="certificado-solicitud">Archivo del certificado (.p12 o .pfx)</flux:label>
                                <input id="certificado-solicitud" x-ref="certificado" type="file" accept=".p12,.pfx,application/x-pkcs12" class="hub-certificate-input block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" x-bind:disabled="estado === 'procesando'" required />
                            </flux:field>
                            <flux:field x-show="metodo === 'token' && tokenCertificados.length > 1">
                                <flux:label for="token-certificado">Certificado del token</flux:label>
                                <select id="token-certificado" x-model="tokenAlias" class="w-full rounded border border-border p-2" x-bind:disabled="estado === 'procesando'"><option value="">Selecciona un certificado</option><template x-for="cert in tokenCertificados" :key="cert.alias"><option :value="cert.alias" x-text="cert.nombre"></option></template></select>
                            </flux:field>
                            <flux:input x-ref="clave" type="password" autocomplete="off" label="Clave del certificado o PIN del token" x-bind:disabled="estado === 'procesando'" required />
                            <p x-show="error" x-text="error" class="text-sm text-error" role="alert" aria-live="assertive"></p>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:button variant="ghost" x-on:click="cerrarDialogo()" x-bind:disabled="estado === 'procesando'">Cancelar</flux:button>
                                <flux:button variant="primary" icon="lock-closed" x-on:click="firmar" x-bind:disabled="estado === 'procesando'">
                                    <span x-show="estado !== 'procesando'">Firmar solicitud</span>
                                    <span x-show="estado === 'procesando'" role="status">Firmando…</span>
                                </flux:button>
                            </div>
                        </section>
                    </div>
                </div>
            @endif
        @endif
    </div>
    <flux:error name="solicitudFirmada" />
</div>
