<div class="hub-request-letter space-y-3"
    x-data="{erroresVisibles: true, temporizadorErrores: null, mostrarErrores() { clearTimeout(this.temporizadorErrores); this.erroresVisibles = true; this.temporizadorErrores = setTimeout(() => { this.erroresVisibles = false }, 3000) }, destroy() { clearTimeout(this.temporizadorErrores) }}"
    x-init="if (@js($errors->any())) mostrarErrores()"
    x-on:solicitud-validation-errors.window="mostrarErrores()"
    x-bind:class="{'hub-request-letter--errors-hidden': !erroresVisibles}">
    @php($camposBloqueados = $this->camposSolicitudBloqueados())
    @php($guardadoR2Confirmado = $this->solicitudR2Confirmada())
    @php($oficioCompleto = $solicitudId && ! $solicitudCambiosPendientes)
    @php($pdfGenerado = $this->pdfSolicitudGenerado())
    <div class="hub-request-fields {{ $camposBloqueados ? 'hub-request-fields--locked' : '' }}">
        <div class="hub-request-cell hub-request-cell--name"><flux:input placeholder=" " wire:model.live.blur="solicitudNombrePermiso" label="Nombre de la persona titular del permiso de colección" maxlength="200" :disabled="$camposBloqueados" required /></div>
        <div class="hub-request-cell hub-request-cell--identification hub-identification-fields">
            <x-gestionprestamosrecepciones::lov-popup campo="solicitudTipoIdentificacion" label="Tipo de identificación" :valor="$solicitudTipoIdentificacion" :opciones="['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte']" :disabled="$camposBloqueados" />
            <flux:input placeholder=" " wire:model.live.blur="solicitudCedula" class:input="hub-field-number" label="Identificación" :inputmode="$solicitudTipoIdentificacion === 'pasaporte' ? 'text' : 'numeric'" :maxlength="$solicitudTipoIdentificacion === 'cedula' ? 10 : ($solicitudTipoIdentificacion === 'ruc' ? 13 : 20)" :disabled="$camposBloqueados" required />
        </div>
        <div class="hub-request-cell hub-request-cell--cargo"><x-gestionprestamosrecepciones::lov-popup campo="solicitudCargo" label="Cargo o posición en el proyecto" :valor="$solicitudCargo" :opciones="$cargosCatalogo" placeholder="Selecciona un cargo" :disabled="$camposBloqueados" /></div>
        <div class="hub-request-cell hub-request-cell--institution"><x-gestionprestamosrecepciones::lov-popup campo="solicitudInstitucion" label="Empresa o institución" :valor="$solicitudInstitucion" :opciones="$institucionesCatalogo" placeholder="Selecciona una institución" :disabled="$camposBloqueados" /></div>
        <div class="hub-request-cell hub-request-cell--group"><x-gestionprestamosrecepciones::lov-popup campo="solicitudGrupo" label="Grupo de especímenes a depositar" :valor="$solicitudGrupo" :opciones="config('depositos.grupos_especimenes')" placeholder="Selecciona un grupo" :disabled="$camposBloqueados" /></div>
        <div class="hub-request-cell hub-request-cell--email"><flux:input placeholder=" " wire:model.live.blur="solicitudCorreo" type="email" label="Correo electrónico" maxlength="250" :disabled="$camposBloqueados" required /></div>
        <div class="hub-request-cell hub-request-cell--project">
            <flux:input placeholder=" " wire:model.live.blur="solicitudProyecto" label="Título del proyecto" maxlength="300" :disabled="$camposBloqueados" required />
        </div>
        <div class="hub-request-cell hub-request-cell--letter">
            <flux:input placeholder=" " wire:model.live.blur="solicitudOficio" class:input="hub-field-number" label="Número de oficio" maxlength="100" :disabled="$camposBloqueados" required />
        </div>
    </div>
    <flux:modal name="visualizar-pdf-solicitud" wire:model.self="mostrarVistaPdfSolicitud" class="hub-request-pdf-preview">
        <div class="hub-request-pdf-preview-content">
            <flux:heading size="lg">Solicitud de depósito · PDF completo</flux:heading>
            @if($mostrarVistaPdfSolicitud && $oficioCompleto && $pdfGenerado)
                <iframe class="hub-request-pdf-frame" title="PDF completo de la solicitud de depósito"
                    src="{{ route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => $solicitudFirmada ? null : 1, 'v' => $solicitudDocumentoVersion]) }}"></iframe>
            @elseif($mostrarVistaPdfSolicitud)
                <iframe class="hub-request-pdf-frame" name="vista-previa-solicitud" title="Vista previa completa de la solicitud de depósito"></iframe>
                <form hidden method="POST" action="{{ route('depositos.solicitud.vista-previa') }}" target="vista-previa-solicitud"
                    x-init="$nextTick(() => $el.submit())">
                    @csrf
                    @foreach([
                        'solicitud_nombre_permiso' => $solicitudNombrePermiso, 'solicitud_tipo_identificacion' => $solicitudTipoIdentificacion,
                        'solicitud_cedula' => $solicitudCedula, 'solicitud_cargo' => $solicitudCargo, 'solicitud_grupo' => $solicitudGrupo,
                        'solicitud_proyecto' => $solicitudProyecto, 'solicitud_institucion' => $solicitudInstitucion,
                        'solicitud_correo' => $solicitudCorreo, 'solicitud_oficio' => $solicitudOficio,
                    ] as $campo => $valor)
                        <input type="hidden" name="{{ $campo }}" value="{{ $valor }}">
                    @endforeach
                </form>
            @endif
        </div>
    </flux:modal>
    <div class="hub-request-document-actions flex flex-wrap items-center justify-between gap-3"
        @if($solicitudFirmada && !$guardadoR2Confirmado) wire:poll.2s="confirmarSolicitudFirmada" @endif
        wire:key="estado-pdf-{{ $solicitudId }}-{{ $solicitudDocumentoVersion }}">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2" aria-live="polite">
        <span class="inline-flex items-center gap-1.5">
            @if($solicitudFirmada)
                <span data-pdf-generated-label class="text-sm font-semibold text-science-blue">PDF Generado</span>
                <span data-pdf-generated-check class="hub-pdf-check" role="img" aria-label="PDF generado correctamente">✓</span>
            @else
                <a href="{{ $oficioCompleto ? route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1]) : '#visualizar-pdf-solicitud' }}" wire:click.prevent="visualizarPdfSolicitud" wire:loading.class="pointer-events-none" class="text-sm font-semibold text-science-blue hover:underline">{{ $pdfGenerado ? 'PDF Generado' : ($oficioCompleto ? 'Generar PDF' : 'Visualizar PDF') }}</a>
                @if($pdfGenerado)<span data-pdf-generated-check class="hub-pdf-check" role="img" aria-label="PDF generado correctamente">✓</span>@endif
            @endif
        </span>
        @if($solicitudOficioAnteriorFirmado)
            <a href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline" aria-label="PDF Firmado, versión anterior">PDF Firmado</a>
        @endif
        <span wire:loading class="sr-only">Guardando…</span>
        @if($oficioCompleto && $pdfGenerado)
            @if($solicitudFirmada)
                <span class="inline-flex items-center gap-1.5">
                    <a data-oficio-signed-link href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline">PDF Firmado</a>
                    <span data-pdf-signed-check class="hub-pdf-check" role="img" aria-label="PDF firmado correctamente">✓</span>
                </span>
            @else
                <div wire:key="firma-solicitud-{{ $solicitudId }}-{{ $solicitudDocumentoVersion }}" x-data="hubDigitalFirmador({uploadUrl: @js(route('depositos.solicitud.firmar', ['id' => $solicitudId])), originalUrl: @js(route('depositos.solicitud.documento', ['id' => $solicitudId, 'original' => 1])), statusUrl: @js(route('depositos.solicitud.estado-firma', ['id' => $solicitudId])), documentId: @js($solicitudId), documentVersion: {{ $solicitudDocumentoVersion }}, onSigned: () => $wire.confirmarSolicitudFirmada()})" x-on:keydown.escape.window="if (dialogo) cerrarDialogo()">
                    <button type="button" class="cursor-pointer text-sm font-semibold text-science-blue hover:underline disabled:cursor-wait disabled:opacity-50" x-ref="abrirFirma" x-on:click="abrirDialogo()" x-show="estado !== 'completado'" wire:loading.attr="disabled">Firmar PDF</button>
                    <template x-if="estado === 'completado'">
                        <span class="inline-flex flex-wrap items-center gap-x-4 gap-y-2">
                            <a data-oficio-signed-link href="{{ route('depositos.solicitud.documento', ['id' => $solicitudId]) }}" target="_blank" rel="noopener" class="text-sm font-semibold text-science-blue hover:underline">PDF Firmado</a>
                            <span data-pdf-signed-check class="hub-pdf-check" role="img" aria-label="PDF firmado correctamente">✓</span>
                        </span>
                    </template>
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
                                <div data-flux-input class="relative">
                                <input id="certificado-solicitud" x-ref="certificado" type="file" accept=".p12,.pfx,application/x-pkcs12" class="hub-certificate-input block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" x-bind:disabled="estado === 'procesando'" required />
                                </div>
                            </flux:field>
                            <flux:field x-show="metodo === 'token' && tokenCertificados.length > 1">
                                <flux:label for="token-certificado">Certificado del token</flux:label>
                                <select id="token-certificado" x-model="tokenAlias" class="w-full rounded border border-border p-2" x-bind:disabled="estado === 'procesando'"><option value="">Selecciona un certificado</option><template x-for="cert in tokenCertificados" :key="cert.alias"><option :value="cert.alias" x-text="cert.nombre"></option></template></select>
                            </flux:field>
                            <flux:input x-ref="clave" type="password" autocomplete="off" label="Clave del certificado o PIN del token" x-bind:disabled="estado === 'procesando'" required />
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:button variant="ghost" x-on:click="cerrarDialogo()" x-bind:disabled="estado === 'procesando'">Cancelar</flux:button>
                                <flux:button variant="primary" icon="lock-closed" x-on:click="firmar" x-bind:disabled="estado === 'procesando' || pdfPreparando" x-bind:aria-busy="(estado === 'procesando' || pdfPreparando).toString()">
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
        <flux:button class="ml-auto" variant="primary" icon-trailing="arrow-right" wire:click="continuarDesdeSolicitud" wire:loading.attr="disabled" :disabled="!$solicitudFirmada || $solicitudCambiosPendientes">{{ \Modules\GestionPrestamosRecepciones\Presentation\Support\PasosWizardDeposito::accionSiguiente(0) }}</flux:button>
    </div>
    <flux:error name="solicitudFirmada" />
</div>
