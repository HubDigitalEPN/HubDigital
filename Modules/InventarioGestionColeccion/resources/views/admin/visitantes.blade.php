<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Acceso de visitantes</flux:heading>
            <p class="text-xs text-text-secondary">Registra visitantes y genérales un QR temporal para que entren al mapa de la colección.</p>
        </div>
        <flux:button icon="plus" variant="primary" wire:click="abrirModal" class="w-full sm:w-auto">
            Nuevo visitante
        </flux:button>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage && !$showModal)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    {{-- Panel del QR generado: el código se dibuja en el navegador desde el enlace
         firmado (no se envía a ningún servicio externo). El enlace queda como respaldo. --}}
    @if($qrUrl)
        <div
            wire:key="panel-qr"
            class="relative rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6"
            x-data="{
                copiado: false,
                render(url) {
                    if (! url || ! window.QRCode) { return; }
                    this.$refs.caja.innerHTML = '';
                    new window.QRCode(this.$refs.caja, {
                        text: url, width: 200, height: 200,
                        correctLevel: window.QRCode.CorrectLevel.M,
                    });
                },
                copiar(texto) {
                    const marcar = () => {
                        this.copiado = true;
                        setTimeout(() => { this.copiado = false; }, 2000);
                    };
                    if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(texto).then(marcar).catch(() => this.copiarFallback(texto, marcar));
                    } else {
                        this.copiarFallback(texto, marcar);
                    }
                },
                copiarFallback(texto, marcar) {
                    const ta = document.createElement('textarea');
                    ta.value = texto;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    try { document.execCommand('copy'); marcar(); } catch (e) {}
                    document.body.removeChild(ta);
                },
            }"
            x-effect="render($wire.qrUrl)"
        >
            <flux:button variant="subtle" icon="x-mark" wire:click="cerrarQr"
                         class="absolute right-3 top-3" aria-label="Cerrar" />

            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div x-ref="caja" class="shrink-0 rounded-lg bg-white p-3 shadow-sm"></div>

                <div class="min-w-0 flex-1 space-y-2 pr-10">
                    <flux:heading size="lg" class="text-text-primary">QR de acceso para {{ $qrVisitanteNombre }}</flux:heading>
                    <p class="text-sm text-text-secondary">
                        Pídele al visitante que escanee este código. Es válido por 5 minutos y lo lleva directo al mapa.
                    </p>

                    <flux:field>
                        <flux:label>Enlace de acceso (respaldo)</flux:label>
                        <flux:input readonly value="{{ $qrUrl }}" />
                    </flux:field>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <flux:button variant="ghost" icon="clipboard" x-on:click="copiar($wire.qrUrl)">
                            <span x-show="!copiado">Copiar enlace</span>
                            <span x-show="copiado">¡Copiado!</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-border bg-surface shadow-sm">
        {{-- Escritorio --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-sm">
                <thead class="border-b border-border bg-blue-navy">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Contacto</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Registrado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Reubicación</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($visitantes as $visitante)
                        <tr class="transition-colors hover:bg-bg-main">
                            <td class="px-4 py-3 font-medium text-text-primary">{{ $visitante['nombre'] }}</td>
                            <td class="px-4 py-3 text-text-primary">{{ $visitante['contacto'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-text-secondary">{{ $visitante['registradoEn'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <flux:switch
                                        :checked="$visitante['puedeReubicar']"
                                        wire:click="alternarReubicacion('{{ $visitante['id'] }}', {{ $visitante['puedeReubicar'] ? 'false' : 'true' }})"
                                        aria-label="Puede reubicar"
                                    />
                                    <span class="text-sm text-text-secondary">Puede reubicar</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="primary" icon="qr-code"
                                                 wire:click="generarQr('{{ $visitante['id'] }}')">Generar QR</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-text-secondary">Aún no hay visitantes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Móvil --}}
        <div class="divide-y divide-border md:hidden">
            @forelse($visitantes as $visitante)
                <div class="space-y-2 p-4">
                    <div class="font-medium text-text-primary break-words">{{ $visitante['nombre'] }}</div>
                    @if(! empty($visitante['contacto']))
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Contacto">
                            {{ $visitante['contacto'] }}
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    @endif
                    <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Registrado">
                        {{ $visitante['registradoEn'] }}
                    </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    <div class="flex items-center gap-2 pt-1">
                        <flux:switch
                            :checked="$visitante['puedeReubicar']"
                            wire:click="alternarReubicacion('{{ $visitante['id'] }}', {{ $visitante['puedeReubicar'] ? 'false' : 'true' }})"
                            aria-label="Puede reubicar"
                        />
                        <span class="text-sm text-text-secondary">Puede reubicar</span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <flux:button variant="primary" icon="qr-code"
                                     wire:click="generarQr('{{ $visitante['id'] }}')">Generar QR</flux:button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-text-secondary">Aún no hay visitantes registrados.</div>
            @endforelse
        </div>
    </div>

    {{-- Modal: registrar visitante --}}
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo visitante</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Ana Pérez" />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>Contacto (opcional)</flux:label>
                <flux:input wire:model="contacto" placeholder="Ej. ana@correo.com" />
                <flux:error name="contacto" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="registrarVisitante" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrarVisitante">Registrar y generar QR</span>
                    <span wire:loading wire:target="registrarVisitante">Registrando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@script
<script>
    // Librería QR liviana y sin dependencias: codifica el enlace en el navegador,
    // así la URL firmada nunca sale del equipo del curador.
    if (! window.QRCode && ! document.getElementById('qrcodejs-lib')) {
        const s = document.createElement('script');
        s.id = 'qrcodejs-lib';
        s.src = 'https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js';
        document.head.appendChild(s);
    }
</script>
@endscript
