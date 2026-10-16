<div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-5 sm:px-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Revocación de firmas</flux:heading>
            <p class="text-sm text-text-secondary">Java consulta primero la evidencia del PDF y las direcciones del certificado. Configura excepciones solo si esas direcciones faltan o fallan.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="nuevo">Nueva entidad</flux:button>
    </div>

    @if(session('fuente-guardada'))
        <p role="status" class="rounded-lg border border-science-blue/30 bg-science-blue/5 px-3 py-2 text-sm">{{ session('fuente-guardada') }}</p>
    @endif

    <div class="grid gap-2 md:grid-cols-2">
        @foreach($fuentes as $fuente)
            <article wire:key="fuente-{{ $fuente->codigo }}" class="rounded-xl border border-border bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-blue-navy">{{ $fuente->entidad }}</h2>
                        <p class="mt-0.5 text-xs text-text-secondary">Emisor: {{ $fuente->patron_emisor }}</p>
                    </div>
                    <span class="shrink-0 rounded px-2 py-0.5 text-xs {{ $fuente->activo ? 'bg-science-blue/10 text-science-blue' : 'bg-border/50 text-text-secondary' }}">{{ $fuente->activo ? 'Activa' : 'Inactiva' }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 text-xs text-text-secondary">
                    <span>{{ $fuente->crl_url ? 'CRL alternativa' : 'CRL del certificado' }}</span>
                    <span>·</span>
                    <span>{{ $fuente->ocsp_url ? 'OCSP alternativo' : 'OCSP del certificado' }}</span>
                </div>
                <div class="mt-3 flex justify-end gap-2 border-t border-border pt-2">
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editar('{{ $fuente->codigo }}')">Editar</flux:button>
                    <flux:button size="xs" variant="ghost" :icon="$fuente->activo ? 'pause-circle' : 'arrow-path'" wire:click="cambiarEstado('{{ $fuente->codigo }}')">{{ $fuente->activo ? 'Desactivar' : 'Activar' }}</flux:button>
                </div>
            </article>
        @endforeach
    </div>

    <flux:modal name="fuente-revocacion-editor" class="w-full max-w-2xl">
        <div class="space-y-3">
            <div>
                <flux:heading size="lg">{{ $editandoCodigo ? 'Editar entidad' : 'Nueva entidad' }}</flux:heading>
                <p class="mt-1 text-xs text-text-secondary">Las URL son alternativas para el emisor indicado. Si la revocación no responde, la firma puede seguir siendo válida.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><flux:input wire:model="codigo" label="Código" :disabled="$editandoCodigo !== null" maxlength="50" /><flux:error name="codigo" /></div>
                <div><flux:input wire:model="entidad" label="Entidad" maxlength="180" /><flux:error name="entidad" /></div>
                <div class="sm:col-span-2"><flux:input wire:model="patronEmisor" label="Patrón del emisor" maxlength="180" /><flux:error name="patronEmisor" /></div>
                <div class="sm:col-span-2"><flux:input wire:model="crlUrl" label="URL alternativa de CRL" type="url" /><flux:error name="crlUrl" /></div>
                <div class="sm:col-span-2"><flux:input wire:model="ocspUrl" label="URL alternativa de OCSP" type="url" /><flux:error name="ocspUrl" /></div>
                <div><flux:input wire:model="tiempoEsperaSegundos" label="Espera de red (segundos)" type="number" min="1" max="5" /><flux:error name="tiempoEsperaSegundos" /></div>
                <div class="flex flex-col justify-center gap-2">
                    <flux:checkbox wire:model="activo" label="Entidad activa" />
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
