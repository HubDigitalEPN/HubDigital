<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-xl space-y-6">
        <div class="space-y-1">
            <flux:heading size="xl" class="font-serif">Certificado de firma electrónica</flux:heading>
            <flux:text class="text-text-secondary">
                Registra tu certificado <span class="font-medium">.p12</span> una sola vez. Con él, las actas se
                firmarán digitalmente de forma automática cuando las apruebes.
            </flux:text>
        </div>

        @if($successMessage)
            <flux:callout variant="success" icon="check-circle">{{ $successMessage }}</flux:callout>
        @endif

        <flux:callout variant="warning" icon="exclamation-triangle">
            Tu certificado y su contraseña se guardan cifrados y se usarán en tu nombre para firmar las actas que
            apruebes. No compartas tu contraseña.
        </flux:callout>

        <form wire:submit="registrar" class="space-y-4 overflow-hidden rounded-lg bg-surface p-4 shadow-sm sm:p-6">
            <flux:input
                type="file"
                wire:model="certificado"
                class="w-full [&_*]:min-w-0 [&_[data-flux-input-file-name]]:truncate"
                label="Archivo de certificado (.p12 / .pfx)"
                accept=".p12,.pfx" />
            <flux:error name="certificado" />

            <flux:input
                type="password"
                wire:model="contrasenia"
                label="Contraseña del certificado"
                viewable />

            <div class="flex justify-end pt-2">
                <flux:button
                    type="submit"
                    variant="primary"
                    icon="shield-check"
                    class="w-full sm:w-auto"
                    wire:loading.attr="disabled"
                    wire:target="registrar,certificado">
                    <flux:icon wire:loading wire:target="registrar" name="arrow-path" class="animate-spin" />
                    Registrar certificado
                </flux:button>
            </div>
        </form>
    </div>
</div>
