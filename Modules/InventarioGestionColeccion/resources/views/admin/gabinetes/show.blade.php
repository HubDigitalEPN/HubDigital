<div class="space-y-6 p-4 sm:p-6">
    <div class="flex items-center gap-3">
        <flux:button
            icon="arrow-left"
            variant="ghost"
            size="sm"
            :href="route('inventario.gabinetes')"
            wire:navigate
        />
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-semibold truncate">
                {{ $gabinete['codigo'] ?? '' }} — {{ $gabinete['nombre'] ?? '' }}
            </flux:heading>
            <p class="text-sm text-text-secondary">{{ count($ranuras) }} / {{ $gabinete['totalRanuras'] ?? 0 }} ranuras configuradas</p>
        </div>
    </div>

    {{-- Bloque de configuración ESP32 --}}
    <div
        class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4"
        x-data="{
            copiedId: false,
            copiedToken: false,
            copyValue(value, copiedKey) {
                try {
                    // Intenta usar la API moderna primero
                    if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(value).then(() => {
                            this[copiedKey] = true;
                            setTimeout(() => { this[copiedKey] = false; }, 2000);
                        }).catch(err => {
                            console.error('Clipboard write failed:', err);
                            this.fallbackCopy(value, copiedKey);
                        });
                    } else {
                        // Fallback para navegadores antiguos o contextos no seguros
                        this.fallbackCopy(value, copiedKey);
                    }
                } catch (error) {
                    console.error('Error al copiar:', error);
                    this.fallbackCopy(value, copiedKey);
                }
            },
            fallbackCopy(value, copiedKey) {
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = value;
                    textArea.setAttribute('readonly', '');
                    textArea.style.position = 'absolute';
                    textArea.style.left = '-9999px';
                    textArea.style.top = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.select();
                    const success = document.execCommand('copy');
                    textArea.remove();
                    
                    if (success) {
                        this[copiedKey] = true;
                        setTimeout(() => { this[copiedKey] = false; }, 2000);
                    }
                } catch (error) {
                    console.error('Fallback copy failed:', error);
                }
            },
        }"
    >
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon name="cpu-chip" class="size-4 text-text-secondary" />
                <flux:heading size="sm" level="3" class="text-text-secondary font-medium">Configuración ESP32</flux:heading>
            </div>
            @if($tieneToken && !$tokenGenerado)
                <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">
                    <flux:icon name="check-circle" class="size-3" />
                    Token activo
                </span>
            @endif
        </div>

        {{-- gabinete_id --}}
        <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
            <span class="text-xs text-text-secondary shrink-0 sm:w-24">gabinete_id</span>
            <code class="w-full flex-1 rounded bg-bg-main px-3 py-1.5 text-sm font-mono text-text-primary select-all break-all">{{ $gabinete['id'] ?? '' }}</code>
            <flux:button
                type="button"
                size="sm"
                variant="ghost"
                icon="clipboard"
                class="w-full sm:w-auto"
                @click="copyValue('{{ $gabinete['id'] ?? '' }}', 'copiedId')"
            >
                <span x-show="!copiedId" x-cloak>Copiar</span>
                <span x-show="copiedId" x-cloak class="text-success">Copiado</span>
            </flux:button>
        </div>

        {{-- api_token --}}
        @if($tokenGenerado)
            <div class="space-y-1.5">
                <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                    <span class="text-xs text-text-secondary shrink-0 sm:w-24">api_token</span>
                    <code class="w-full flex-1 rounded bg-bg-main px-3 py-1.5 text-sm font-mono text-text-primary select-all break-all">{{ $tokenGenerado }}</code>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="clipboard"
                        class="w-full sm:w-auto"
                        @click="copyValue('{{ $tokenGenerado }}', 'copiedToken')"
                    >
                        <span x-show="!copiedToken" x-cloak>Copiar</span>
                        <span x-show="copiedToken" x-cloak class="text-success">Copiado</span>
                    </flux:button>
                </div>
                <flux:callout variant="warning" icon="exclamation-triangle">
                    Guarda este token ahora. No se volverá a mostrar.
                </flux:callout>
            </div>
        @else
            <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                <span class="text-xs text-text-secondary shrink-0 sm:w-24">api_token</span>
                <span class="w-full flex-1 text-sm text-text-secondary italic">
                    @if($tieneToken) Token existente (no se muestra por seguridad) @else Sin token @endif
                </span>
                <flux:button
                    size="sm"
                    variant="{{ $tieneToken ? 'ghost' : 'primary' }}"
                    class="w-full sm:w-auto"
                    wire:click="generarToken"
                    wire:loading.attr="disabled"
                    wire:confirm="{{ $tieneToken ? '¿Revocar el token actual y generar uno nuevo? El ESP32 dejará de funcionar hasta que flashees el nuevo token.' : null }}"
                >
                    <span wire:loading.remove wire:target="generarToken">
                        {{ $tieneToken ? 'Regenerar token' : 'Generar token' }}
                    </span>
                    <span wire:loading wire:target="generarToken">Generando…</span>
                </flux:button>
            </div>
        @endif
    </div>

    @if($successMessage)
        <flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>
    @endif

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="p-4 space-y-4">
            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">Ranuras</flux:heading>

            <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(2.75rem, 1fr))">
                @foreach($ranuras as $ranura)
                    <x-inventariogestioncoleccion::seguimiento-fisico.ranura-slot
                        :ranura="$ranura"
                        :caja="$ranura['cajaActual'] ?? null"
                    />
                @endforeach
            </div>
        </div>

        {{-- Tabla (desktop) --}}
        <div class="hidden md:block border-t border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-white">Ranura</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Caja Actual</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Estado</th>
                        <th class="px-4 py-3 text-left font-medium text-white">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($ranuras as $ranura)
                        <tr class="hover:bg-bg-main transition-colors">
                            <td class="px-4 py-3 font-medium text-text-primary">Ranura {{ $ranura['numeroRanura'] }}</td>
                            <td class="px-4 py-3 text-text-primary">
                                @if(isset($ranura['cajaActual']) && $ranura['cajaActual'])
                                    <span class="font-mono text-xs">{{ $ranura['cajaActual']['codigo'] }}</span>
                                @else
                                    <span class="text-text-secondary">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($ranura['activa'])
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activa</span>
                                @else
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="abrirEditRanura('{{ $ranura['id'] }}')"
                                >
                                    Editar
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tarjetas (móvil) --}}
        <div class="md:hidden border-t border-border p-4 space-y-3">
            @foreach($ranuras as $ranura)
                <div class="rounded-lg border border-border bg-surface p-4 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-medium text-text-primary">Ranura {{ $ranura['numeroRanura'] }}</span>
                        @if($ranura['activa'])
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-success text-white">Activa</span>
                        @else
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-border text-text-primary">Inactiva</span>
                        @endif
                    </div>
                    <dl class="space-y-1.5 text-sm">
                        <x-inventariogestioncoleccion::seguimiento-fisico.campo-movil etiqueta="Caja actual">
                            @if(isset($ranura['cajaActual']) && $ranura['cajaActual'])
                                <span class="font-mono text-xs">{{ $ranura['cajaActual']['codigo'] }}</span>
                            @else
                                —
                            @endif
                        </x-inventariogestioncoleccion::seguimiento-fisico.campo-movil>
                    </dl>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <flux:button
                            variant="ghost"
                            icon="pencil"
                            wire:click="abrirEditRanura('{{ $ranura['id'] }}')"
                        >
                            Editar
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Modal: Editar ranura --}}
    <flux:modal wire:model="showEditRanura" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar ranura</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Estado</flux:label>
                <flux:select wire:model="editActiva">
                    <option value="1">Activa</option>
                    <option value="0">Inactiva</option>
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditRanura', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="actualizarRanura" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="actualizarRanura">Guardar</span>
                    <span wire:loading wire:target="actualizarRanura">Guardando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
