<div class="hub-workspace mx-auto max-w-3xl space-y-5 p-4 sm:p-6">
    <header class="hub-page-header">
        <div>
            <h1 class="hub-page-title">Siglas del expediente</h1>
        </div>
    </header>

    @if(session('prefijo-guardado'))
        <p data-hub-feedback="success" role="status" class="rounded-lg border border-success/30 bg-success/5 p-3 text-sm text-success">{{ session('prefijo-guardado') }}</p>
    @endif

    <form wire:submit="guardar" class="hub-panel space-y-4 p-5">
        <flux:input wire:model="prefijo" label="Siglas" maxlength="40" />
        <flux:error name="prefijo" />
        <p class="text-sm text-text-secondary">Vista previa: <strong class="font-mono text-blue-navy">{{ strtoupper(trim($prefijo)) }}-00001</strong></p>
        <div class="flex justify-end"><flux:button type="submit" variant="primary" icon="check">Guardar siglas</flux:button></div>
    </form>
</div>
