<div class="hub-workspace mx-auto max-w-7xl space-y-4 p-3 sm:p-5">
    <header class="hub-page-header">
        <div>
            <h1 class="hub-page-title">Textos del asistente de depósitos</h1>
        </div>
    </header>

    @if(session('wizard-textos'))
        <p role="status" class="rounded-lg border border-success/30 bg-success/5 p-3 text-sm text-success">{{ session('wizard-textos') }}</p>
    @endif

    <form wire:submit="guardar" class="space-y-5">
        @foreach($grupos as $grupo => $campos)
            <details class="hub-panel group p-4" @if($loop->first) open @endif>
                <summary class="flex cursor-pointer items-center justify-between gap-3 text-blue-navy marker:hidden">
                    <span class="hub-section-title">{{ ucfirst($grupo) }}</span>
                    <span class="text-xs text-text-secondary">{{ count($campos) }} textos · Abrir o cerrar</span>
                </summary>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($campos as $nombre => $defecto)
                        @php $clave = $grupo.'.'.$nombre; @endphp
                        <flux:textarea wire:model="textos.{{ $grupo }}.{{ $nombre }}" label="{{ str_replace('_', ' ', ucfirst($nombre)) }}" rows="2" maxlength="1000" />
                        @error('textos.'.$clave) <p class="text-xs text-error">{{ $message }}</p> @enderror
                    @endforeach
                </div>
            </details>
        @endforeach
        <div class="sticky bottom-3 flex justify-end">
            <flux:button type="submit" variant="primary" icon="check" class="shadow-lg">Guardar textos</flux:button>
        </div>
    </form>
</div>
