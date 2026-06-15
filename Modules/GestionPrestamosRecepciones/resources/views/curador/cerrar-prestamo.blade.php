<div class="space-y-5">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
            Préstamo {{ $prestamo->numeroPrestamo }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Cerrar préstamo</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl" level="1" class="font-display">Cierre de préstamo</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Registra el resultado de la inspección para cerrar formalmente el préstamo.
        </flux:text>
    </div>

    {{-- Sección 1: Información del préstamo --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                1
            </div>
            <flux:heading size="base" level="2" class="font-display flex-1">Información del préstamo</flux:heading>
            <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado->value" />
        </div>
        <div class="p-5">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">N.º préstamo</dt>
                    <dd class="font-mono font-medium text-text-primary mt-1">{{ $prestamo->numeroPrestamo }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Fecha límite</dt>
                    <dd class="font-medium text-text-primary mt-1">{{ $prestamo->fechaFin->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-secondary uppercase tracking-wide">Inicio del préstamo</dt>
                    <dd class="font-medium text-text-primary mt-1">{{ $prestamo->iniciadoEn->format('d/m/Y') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Sección 2: Resultado de la verificación --}}
    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-navy text-white text-sm font-bold shrink-0">
                2
            </div>
            <flux:heading size="base" level="2" class="font-display">Resultado de la devolución</flux:heading>
        </div>
        <div class="p-5">
            <p class="text-sm text-text-secondary mb-4">
                Selecciona el resultado tras inspeccionar los especímenes devueltos.
            </p>

            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-4 cursor-pointer rounded-lg border-2 p-4 transition-all
                    {{ $resultado === 'sin novedades'
                        ? 'border-bio-green bg-bio-green/5 ring-1 ring-bio-green/20'
                        : 'border-border hover:border-bio-green/40' }}">
                    <input type="radio" wire:model.live="resultado" value="sin novedades"
                        class="mt-0.5 accent-bio-green shrink-0" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-text-primary">Sin novedades</p>
                        <p class="text-xs text-text-secondary mt-0.5">Los especímenes fueron devueltos en condiciones óptimas. El préstamo cerrará como <strong>cerrado</strong>.</p>
                    </div>
                    @if($resultado === 'sin novedades')
                        <flux:icon name="check-circle" class="size-5 text-bio-green shrink-0" />
                    @endif
                </label>

                <label class="flex items-start gap-4 cursor-pointer rounded-lg border-2 p-4 transition-all
                    {{ $resultado === 'con observación'
                        ? 'border-warning bg-warning/5 ring-1 ring-warning/20'
                        : 'border-border hover:border-warning/40' }}">
                    <input type="radio" wire:model.live="resultado" value="con observación"
                        class="mt-0.5 accent-warning shrink-0" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-text-primary">Con observación</p>
                        <p class="text-xs text-text-secondary mt-0.5">Se detectaron anomalías o daños en los especímenes. El préstamo cerrará como <strong>cerrado con observación</strong>.</p>
                    </div>
                    @if($resultado === 'con observación')
                        <flux:icon name="exclamation-triangle" class="size-5 text-warning shrink-0" />
                    @endif
                </label>
            </div>

            @error('resultado')
                <p class="text-xs text-error mt-2">{{ $message }}</p>
            @enderror

            {{-- Comentario obligatorio cuando hay observaciones --}}
            @if($resultado === 'con observación')
                <div class="mt-4">
                    <flux:field>
                        <flux:label>Descripción de las observaciones <span class="text-error">*</span></flux:label>
                        <flux:textarea
                            wire:model.live="observacion"
                            placeholder="Describe las anomalías o daños detectados en los especímenes devueltos…"
                            rows="4" />
                        <flux:error name="observacion" />
                    </flux:field>
                </div>
            @endif

            <div class="flex items-center gap-3 mt-6">
                <flux:button variant="primary" wire:click="cerrar" wire:loading.attr="disabled">
                    <flux:icon wire:loading wire:target="cerrar" name="arrow-path" class="animate-spin" />
                    <flux:icon name="check-circle" class="size-4" />
                    Cerrar préstamo
                </flux:button>
                <flux:button variant="ghost" wire:navigate
                    href="{{ route('prestamos.curador.prestamo.detalle', $prestamo->prestamoId) }}">
                    Cancelar
                </flux:button>
            </div>
        </div>
    </div>

</div>
