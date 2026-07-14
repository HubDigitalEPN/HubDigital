<div class="space-y-6 p-4 sm:p-6 max-w-4xl">
    <div>
        <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Etiquetado QR</flux:heading>
        <p class="mt-1 text-sm text-text-secondary">
            Busca un espécimen por su código y genera su etiqueta QR para imprimir o descargar.
            Al escanearla desde el móvil se abre la ficha del espécimen. El QR se genera en el
            servidor: funciona sin internet.
        </p>
    </div>

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    {{-- Buscador --}}
    <div class="rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row">
            <flux:input wire:model="busqueda"
                        wire:keydown.enter="buscar"
                        placeholder="Código de catálogo, occurrenceID o número de catálogo…"
                        class="flex-1" />
            <flux:button variant="primary" icon="magnifying-glass"
                         wire:click="buscar"
                         wire:loading.attr="disabled" wire:target="buscar"
                         class="w-full sm:w-auto">
                <span wire:loading.remove wire:target="buscar">Buscar</span>
                <span wire:loading wire:target="buscar">Buscando…</span>
            </flux:button>
        </div>
    </div>

    {{-- Panel del QR generado --}}
    @if($qrUrl)
        <div
            wire:key="panel-qr-etiqueta-{{ md5($qrUrl) }}"
            class="relative rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6"
            x-data="{
                codigo: @js($qrEspecimenCodigo),
                imprimir() {
                    const w = window.open('', '_blank', 'width=400,height=520');
                    if (! w) { window.alert('Habilita las ventanas emergentes para imprimir la etiqueta.'); return; }
                    const d = w.document;
                    d.title = 'QR ' + this.codigo;
                    d.body.style.cssText = 'margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;font-family:sans-serif';
                    const caja = d.createElement('div');
                    caja.style.width = '220px';
                    caja.innerHTML = this.$refs.caja.innerHTML;
                    const label = d.createElement('p');
                    label.style.cssText = 'font-weight:600;margin-top:8px';
                    label.textContent = this.codigo;
                    d.body.appendChild(caja);
                    d.body.appendChild(label);
                    w.focus();
                    w.print();
                },
            }"
        >
            <flux:button variant="subtle" icon="x-mark" wire:click="cerrarQr"
                         class="absolute right-3 top-3" aria-label="Cerrar" />

            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div x-ref="caja" class="w-[220px] shrink-0 rounded-lg bg-white p-3 shadow-sm">
                    {!! $qrSvg !!}
                </div>

                <div class="min-w-0 flex-1 space-y-3 pr-10">
                    <flux:heading size="lg" class="text-text-primary">Código QR de {{ $qrEspecimenCodigo }}</flux:heading>
                    <p class="text-sm text-text-secondary">Imprime este código y pégalo en el espécimen.</p>

                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" icon="printer" @click="imprimir()">Imprimir</flux:button>
                        <flux:button variant="ghost" icon="arrow-down-tray"
                                     href="data:image/svg+xml;charset=utf-8,{{ rawurlencode($qrSvg) }}"
                                     download="qr-{{ $qrEspecimenCodigo }}.svg">
                            Descargar SVG
                        </flux:button>
                    </div>

                    <flux:field>
                        <flux:label>Enlace de la ficha</flux:label>
                        <flux:input readonly value="{{ $qrUrl }}" />
                    </flux:field>
                </div>
            </div>
        </div>
    @endif

    {{-- Resultados de la búsqueda --}}
    @if($buscado)
        <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
            <div class="border-b border-border bg-bg-main px-4 py-3 text-sm font-medium text-text-primary">
                {{ count($resultados) }} coincidencia(s)
            </div>
            <div class="divide-y divide-border">
                @forelse($resultados as $e)
                    <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="et-{{ $e['id'] }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm text-text-primary">{{ $e['codigoCatalogo'] }}</span>
                                @if(! empty($e['taxonNombre']))
                                    <span class="font-serif text-sm italic text-text-primary">{{ $e['taxonNombre'] }}</span>
                                @else
                                    <span class="text-xs italic text-text-secondary">sin determinar</span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-text-secondary">{{ $e['localidad'] ?: '—' }}</div>
                        </div>
                        <flux:button variant="ghost" icon="qr-code"
                                     wire:click="mostrarQr('{{ $e['id'] }}')"
                                     wire:loading.attr="disabled"
                                     wire:target="mostrarQr('{{ $e['id'] }}')"
                                     class="w-full sm:w-auto">
                            Generar / ver QR
                        </flux:button>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-text-secondary">
                        No se encontró ningún espécimen con «{{ $busqueda }}».
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
