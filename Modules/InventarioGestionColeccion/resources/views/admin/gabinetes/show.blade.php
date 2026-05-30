<div class="space-y-6 p-6">
    <div class="flex items-center gap-3">
        <flux:button
            icon="arrow-left"
            variant="ghost"
            size="sm"
            :href="route('inventario.gabinetes')"
            wire:navigate
        />
        <div>
            <flux:heading size="xl" level="1" class="font-display text-blue-navy font-semibold">
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
        <div class="flex items-center gap-3">
            <span class="text-xs text-text-secondary shrink-0 w-24">gabinete_id</span>
            <code class="flex-1 rounded bg-bg-main px-3 py-1.5 text-sm font-mono text-text-primary select-all">{{ $gabinete['id'] ?? '' }}</code>
            <flux:button
                type="button"
                size="sm"
                variant="ghost"
                icon="clipboard"
                @click="copyValue('{{ $gabinete['id'] ?? '' }}', 'copiedId')"
            >
                <span x-show="!copiedId" x-cloak>Copiar</span>
                <span x-show="copiedId" x-cloak class="text-success">Copiado</span>
            </flux:button>
        </div>

        {{-- api_token --}}
        @if($tokenGenerado)
            <div class="space-y-1.5">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-text-secondary shrink-0 w-24">api_token</span>
                    <code class="flex-1 rounded bg-bg-main px-3 py-1.5 text-sm font-mono text-text-primary select-all break-all">{{ $tokenGenerado }}</code>
                    <flux:button
                        type="button"
                        size="sm"
                        variant="ghost"
                        icon="clipboard"
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
            <div class="flex items-center gap-3">
                <span class="text-xs text-text-secondary shrink-0 w-24">api_token</span>
                <span class="flex-1 text-sm text-text-secondary italic">
                    @if($tieneToken) Token existente (no se muestra por seguridad) @else Sin token @endif
                </span>
                <flux:button
                    size="sm"
                    variant="{{ $tieneToken ? 'ghost' : 'primary' }}"
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

    <div class="rounded-lg border border-border bg-surface shadow-sm p-4 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2" class="font-display text-blue-navy font-semibold">Ranuras</flux:heading>
            @if(count($ranuras) < ($gabinete['totalRanuras'] ?? 0))
                <flux:button
                    icon="plus"
                    size="sm"
                    variant="primary"
                    wire:click="$set('showAgregarRanura', true)"
                >
                    Agregar ranura
                </flux:button>
            @endif
        </div>

        @if(count($ranuras) > 0)
            <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(2.75rem, 1fr))">
                @foreach($ranuras as $ranura)
                    <x-inventariogestioncoleccion::seguimiento-fisico.ranura-slot
                        :ranura="$ranura"
                        :caja="$ranura['cajaActual'] ?? null"
                    />
                @endforeach
            </div>
        @else
            <p class="text-sm text-text-primary py-4 text-center">
                No hay ranuras configuradas. Agrega la primera ranura.
            </p>
        @endif
    </div>

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
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
                @forelse($ranuras as $ranura)
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
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-text-primary">Sin ranuras.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal: Agregar ranura --}}
    <flux:modal wire:model="showAgregarRanura" class="w-full max-w-md">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Agregar ranura</flux:heading>

            @if($errorMessage)
                <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
            @endif

            <flux:field>
                <flux:label>Número de ranura</flux:label>
                <flux:input type="number" wire:model="numeroRanura" min="1" :max="$gabinete['totalRanuras'] ?? 25" />
                <flux:error name="numeroRanura" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showAgregarRanura', false)">
                    Cancelar
                </flux:button>
                <flux:button variant="primary" wire:click="agregarRanura" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="agregarRanura">Agregar</span>
                    <span wire:loading wire:target="agregarRanura">Agregando...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
