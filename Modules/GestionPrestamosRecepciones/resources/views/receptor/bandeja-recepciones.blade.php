<div class="hub-transactional-ui hub-depositos-ui hub-workspace p-4 sm:p-6 space-y-4">
    <div>
        <flux:heading size="xl" level="1" class="font-display">Recepción física de lotes</flux:heading>

    </div>

    <section class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="font-display text-lg font-semibold text-blue-navy">Ingreso de ventanilla</h2>

            </div>
            <form wire:submit="abrirPorCodigo" class="flex w-full gap-2 lg:w-[28rem]">
                <flux:input wire:model="codigoQr" class="flex-1" icon="qr-code" placeholder="LOTE-ABC123" aria-label="Código QR del lote" autocomplete="off" />
                <flux:button type="submit" variant="primary" icon="arrow-right">Abrir</flux:button>
            </form>
        </div>
        <flux:error name="codigoQr" class="mt-2" />
    </section>

    <section class="grid gap-2 rounded-lg border border-border bg-surface p-2 shadow-sm lg:grid-cols-[auto_minmax(18rem,1fr)] lg:items-center" aria-label="Filtros de recepciones">
    <div class="inline-flex max-w-full overflow-x-auto p-1" role="tablist" aria-label="Estado de recepciones">
        @foreach (['pendientes' => 'Por recibir', 'verificacion' => 'En constatación', 'historial' => 'Historial'] as $valor => $etiqueta)
            <button type="button" wire:click="cambiarVista('{{ $valor }}')"
                @class([
                    'inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    'bg-blue-navy text-white' => $vista === $valor,
                    'text-text-secondary hover:text-text-primary' => $vista !== $valor,
                ])>
                {{ $etiqueta }}
                <span @class([
                    'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                    'bg-white/20 text-white' => $vista === $valor,
                    'bg-bg-main text-text-secondary' => $vista !== $valor,
                ])>{{ $contadores[$valor] }}</span>
            </button>
        @endforeach
    </div>

    <flux:input wire:model.live.debounce.300ms="busqueda" icon="magnifying-glass" placeholder="Buscar por número, QR o nombre del consultor" aria-label="Buscar lotes" />
    </section>

    <div class="grid gap-3">
        @forelse($solicitudes as $solicitud)
            @php $recepcion = $recepciones->get($solicitud->id); @endphp
            <div class="rounded-xl border border-border bg-surface p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs text-text-secondary">{{ $solicitud->numero }}</span>
                        <flux:badge size="sm" color="sky">{{ $solicitud->tipo_tramite }}</flux:badge>
                        @if($solicitud->entrega_programada_para)
                            <flux:badge size="sm" color="indigo" icon="calendar-days">Entrega anunciada</flux:badge>
                        @endif
                        @if($recepcion)
                            <x-gestionprestamosrecepciones::recepcion-status-badge :estado="$recepcion->estado" />
                        @else
                            <flux:badge size="sm" color="amber">Pendiente de constatar</flux:badge>
                        @endif
                    </div>
                    <p class="mt-2 font-medium text-text-primary">{{ $nombres[$solicitud->investigador_id] ?? $solicitud->nombre_investigador_documento }}</p>
                    <p class="mt-1 text-xs text-text-secondary">Lote {{ $solicitud->codigo_qr }} Â· {{ $solicitud->grupo_animal ?? 'Grupo por confirmar' }}@if($solicitud->entrega_programada_para) · Prevista: @fechaEc($solicitud->entrega_programada_para, 'd/m H:i')@endif</p>
                </div>
                <flux:button class="mt-3 w-full sm:mt-0 sm:w-auto" variant="primary" icon="clipboard-document-check" wire:navigate
                    href="{{ route('prestamos.receptor.deposito.recepcion', $solicitud->id) }}">
                    {{ $recepcion?->estado === 'En Verificación' ? 'Continuar constatación' : ($recepcion ? 'Ver recepción' : 'Iniciar constatación') }}
                </flux:button>
            </div>
        @empty
            <div class="hub-compact-empty rounded-lg border border-border bg-surface text-sm text-text-secondary shadow-sm">
                <flux:icon name="inbox" class="size-6 text-text-secondary/50" />
                <span>No hay lotes en esta bandeja.</span>
            </div>
        @endforelse
    </div>
</div>
