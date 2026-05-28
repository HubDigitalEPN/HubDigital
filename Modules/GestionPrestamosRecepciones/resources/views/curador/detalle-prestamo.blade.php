<div class="p-6 space-y-6">

    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:navigate href="{{ route('prestamos.curador.panel') }}">
            Panel
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Préstamo {{ $prestamo->numeroPrestamo }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Info principal --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <flux:heading size="xl" level="1" class="font-display">
                        Préstamo {{ $prestamo->numeroPrestamo }}
                    </flux:heading>
                    <x-gestionprestamosrecepciones::prestamo-status-badge :estado="$prestamo->estado" />
                </div>
                <flux:separator />

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-secondary">Fecha de inicio</dt>
                        <dd class="text-text-primary">{{ $prestamo->iniciadoEn->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-secondary">Fecha de vencimiento</dt>
                        <dd class="text-text-primary">{{ $prestamo->fechaFin->format('d/m/Y') }}</dd>
                    </div>
                    @php
                        $hoy = new \DateTimeImmutable('today');
                        $diasRestantes = (int) $hoy->diff($prestamo->fechaFin)->days;
                        $vencido = $prestamo->fechaFin < $hoy;
                    @endphp
                    <div class="col-span-2">
                        <dt class="text-text-secondary">Días restantes</dt>
                        <dd class="font-medium {{ $vencido ? 'text-error' : 'text-text-primary' }}">
                            {{ $vencido ? 'Vencido hace ' . $diasRestantes . ' días' : $diasRestantes . ' días' }}
                        </dd>
                    </div>
                </dl>

                <flux:separator />

                <flux:button variant="ghost" icon="document-text" size="sm" wire:navigate
                    href="{{ route('prestamos.acta.ver', $prestamo->actaPrestamoId) }}">
                    Ver acta de préstamo
                </flux:button>
            </div>

        </div>

        {{-- Timeline historial --}}
        <div class="rounded-lg border border-border bg-surface shadow-sm p-5 space-y-2 h-fit">
            <flux:heading size="lg" level="2" class="font-display">Historial</flux:heading>
            <flux:separator />
            <div class="mt-3">
                @forelse($historial->eventos as $i => $evento)
                    @php
                        $etiquetas = [
                            'PrestamoIniciado'              => 'Préstamo iniciado',
                            'ActaEnviada'                   => 'Acta enviada',
                            'ActaFirmadaSubida'             => 'Acta firmada subida',
                            'ActaValidada'                  => 'Acta validada',
                            'ActaDevueltaPorFirmaInvalida'  => 'Acta devuelta por el curador',
                        ];
                        $titulo = $etiquetas[$evento->tipo] ?? $evento->tipo;
                        $esUltimo = $i === count($historial->eventos) - 1;
                    @endphp
                    <x-gestionprestamosrecepciones::timeline-event
                        :fecha="$evento->ocurridoEn->format('d/m/Y H:i')"
                        :titulo="$titulo"
                        :ultimo="$esUltimo" />
                @empty
                    <flux:text class="text-xs text-text-secondary">Sin eventos registrados.</flux:text>
                @endforelse
            </div>
        </div>

    </div>

</div>
