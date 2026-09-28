@php
    $recibida = in_array($recepcion->estadoRecepcion, ['Verificado Físicamente', 'Verificado con Observaciones'], true);
    $firma = $recepcion->firmaMetadata;
@endphp

<div class="hub-transactional-ui hub-depositos-ui hub-workspace p-4 sm:p-6 space-y-5" @toast.window="$flux.toast($event.detail.message)">
    <flux:breadcrumbs class="hub-form-breadcrumbs">
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.depositos') }}">Depósitos</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $recepcion->numeroSolicitud }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Acta final</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Acta final de recepción</flux:heading>
        <flux:text class="mt-1 text-text-secondary">
            {{ $recepcion->numeroSolicitud }} · {{ $depositante ?? 'Depositante' }}
        </flux:text>
    </div>

    @unless($recibida)
        <flux:callout variant="warning" icon="clock">
            <flux:callout.heading>Pendiente de recepción física</flux:callout.heading>
            <flux:callout.text>
                Curaduría podrá generar el acta únicamente cuando Recepción EPN marque el lote como recibido y constatado.
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-text-secondary">Constatado por</p>
                <p class="mt-1 font-medium text-text-primary">{{ $receptor ?? 'Recepción EPN' }}</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-text-secondary">Fecha de recepción</p>
                <p class="mt-1 font-medium text-text-primary">@fechaEc($recepcion->verificadoEn)</p>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-text-secondary">Estado físico</p>
                <p class="mt-1 font-medium text-text-primary">{{ $recepcion->estadoRecepcion }}</p>
            </div>
        </div>

        @unless($recepcion->actaEmitida)
            <flux:callout variant="info" icon="document-text">
                <flux:callout.heading>Generar documento oficial</flux:callout.heading>
                <flux:callout.text>
                    HubDigital completará el acta con los datos aprobados, la constatación física y la identidad del curador.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button wire:click="generar" wire:loading.attr="disabled" variant="primary" icon="document-plus">
                        Generar acta final
                    </flux:button>
                </x-slot>
            </flux:callout>
        @elseif($recepcion->actaFirmada)
            <flux:callout variant="success" icon="shield-check">
                <flux:callout.heading>Firma electrónica validada</flux:callout.heading>
                <flux:callout.text>
                    El acta quedó cerrada, habilitó el ingreso del lote a la colección en estado
                    <strong>{{ $recepcion->estadoColeccion }}</strong> y el depositante ya puede descargarla.
                    Firmante: <strong>{{ data_get($firma, 'certificado.nombre', 'certificado verificado') }}</strong>.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button href="{{ route('prestamos.deposito.acta-recepcion', $this->id) }}"
                        target="_blank" rel="noopener" variant="primary" icon="document-arrow-down">
                        Ver acta firmada
                    </flux:button>
                </x-slot>
            </flux:callout>
        @elseif(! $originalDisponible)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>El original oficial no está disponible</flux:callout.heading>
                <flux:callout.text>
                    No se puede firmar una versión regenerada silenciosamente. Emita una nueva versión y revísela completa antes de firmar.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button wire:click="reemitirOriginal({{ $originalVersion }})" wire:loading.attr="disabled" variant="primary" icon="document-arrow-path">
                        Reemitir versión para revisión
                    </flux:button>
                </x-slot>
            </flux:callout>
        @else
            <div class="rounded-xl border border-border bg-surface shadow-sm overflow-hidden">
                <div class="border-b border-border p-5">
                    <flux:heading size="lg" level="2">Firmador HubDigital</flux:heading>
                    <flux:text class="mt-1 text-text-secondary">
                        Revise el acta y fírmela aquí con su certificado institucional o personal .p12/.pfx.
                    </flux:text>
                </div>

                <div class="grid gap-6 p-5 lg:grid-cols-[1.1fr_.9fr]"
                    x-data="hubDigitalFirmador({
                        documentUrl: @js(route('prestamos.deposito.acta-recepcion', $this->id)),
                        uploadUrl: @js(route('prestamos.curador.deposito.acta.firmar', $this->id)),
                        originalReference: @js($originalReferencia),
                        originalSha256: @js($originalSha256),
                        signatureProfile: 'acta-recepcion:curador:v1',
                        reason: 'Aprobación del acta final de recepción de especímenes',
                        location: 'Laboratorio de Invertebrados EPN, Quito, Ecuador'
                    })">
                    <div class="min-h-0 overflow-hidden rounded-lg border border-border bg-bg-main">
                        <iframe title="Vista previa del acta final" class="hub-document-viewer w-full"
                            src="{{ route('prestamos.deposito.acta-recepcion', $this->id) }}"></iframe>
                    </div>

                    <div class="space-y-4">
                        <flux:callout variant="info" icon="lock-closed">
                            <flux:callout.heading>Firma y verificación con Java</flux:callout.heading>
                            <flux:callout.text>
                                El certificado y la contraseña se entregan temporalmente a Java para firmar el original oficial. Se eliminan al terminar el intento; el expediente conserva el PDF firmado y su validación.
                            </flux:callout.text>
                        </flux:callout>

                        <flux:field>
                            <flux:label>Certificado electrónico (.p12 o .pfx)</flux:label>
                            <input x-ref="certificado" type="file" accept=".p12,.pfx,application/x-pkcs12"
                                class="block w-full rounded-lg border border-border bg-white p-2.5 text-sm" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Contraseña del certificado</flux:label>
                            <input x-ref="clave" type="password" autocomplete="off"
                                class="block w-full rounded-lg border border-border bg-white p-2.5 text-sm" />
                        </flux:field>

                        <label class="flex items-start gap-2 text-sm text-text-primary">
                            <input type="checkbox" required class="mt-1 rounded border-border" x-ref="consentimiento" />
                            <span>Confirmo que revisé el documento y que el certificado seleccionado me pertenece o estoy autorizado para usarlo.</span>
                        </label>

                        <p x-show="error" x-text="error" class="rounded-lg bg-error/10 p-3 text-sm text-error" role="alert"></p>
                        <p x-show="progreso" x-text="progreso" class="text-sm text-text-secondary" aria-live="polite"></p>

                        <flux:button x-on:click="if (!$refs.consentimiento.checked) { error = 'Debe confirmar la revisión y autorización.' } else { firmar() }"
                            x-bind:disabled="estado === 'procesando'" variant="primary" icon="key" class="w-full">
                            <span x-show="estado !== 'procesando'">Firmar electrónicamente</span>
                            <span x-show="estado === 'procesando'">Firmando y validando…</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        @endunless
    @endunless
</div>
