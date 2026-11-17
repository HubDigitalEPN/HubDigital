<div class="hub-transactional-ui hub-depositos-ui hub-workspace mx-auto max-w-3xl space-y-5 p-4 sm:p-6" data-validar-ingreso-qr>
    <header><h1 class="text-2xl font-semibold">Validar documento de ingreso</h1><p class="text-text-secondary">{{ $solicitud->numero }} · {{ $solicitud->codigo_qr }}</p></header>
    @if($solicitud->recepcion_qr_rechazada_en)
        <flux:callout variant="danger" heading="Solicitud rechazada">{{ $solicitud->recepcion_qr_motivo }}</flux:callout>
    @elseif($solicitud->recepcion_qr_en)
        <flux:callout variant="success" :heading="$solicitud->contenido_validado_en ? 'Recibida y validada' : 'Recibida · pendiente de validar contenido'">Recepción registrada. El registro detallado del contenido es la siguiente etapa.</flux:callout>
        <flux:button variant="primary" :href="route('depositos.ingreso.contenido', $solicitud->id)" wire:navigate>Registrar y validar contenido</flux:button>
    @elseif($solicitud->estado !== 'Aprobada Documentalmente')
        <flux:callout variant="warning" heading="Documento sin aprobación vigente">Estado del expediente: {{ $solicitud->estado }}. No puede recibirse el material.</flux:callout>
    @else
        <flux:callout variant="success" heading="QR auténtico · solicitud aprobada">El enlace está autenticado por HubDigital y corresponde a este expediente. Compara estas características con el documento y el material presentado.</flux:callout>
    @endif
    <dl class="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
        @foreach(['Titular' => $solicitud->solicitud_nombre_permiso ?: $solicitud->nombre_investigador_documento, 'Proyecto' => $solicitud->solicitud_proyecto, 'Institución' => $solicitud->solicitud_institucion, 'Grupo' => $solicitud->grupo_animal ?: $solicitud->solicitud_grupo, 'Lotes' => $solicitud->nro_lotes, 'Individuos' => $solicitud->nro_individuos, 'Localidad' => $solicitud->localidad, 'Permiso recolección' => $solicitud->nro_permiso_recoleccion, 'Permiso movilización' => $solicitud->nro_permiso_movilizacion] as $nombre => $valor)
            <div><dt class="text-sm text-text-secondary">{{ $nombre }}</dt><dd class="break-words font-semibold">{{ $valor ?? 'No declarado' }}</dd></div>
        @endforeach
    </dl>
    @if($solicitud->documento_ingreso_ruta && !$solicitud->recepcion_qr_rechazada_en)
        <a class="text-science-blue underline" href="{{ route('depositos.ingreso.documento', ['id' => $solicitud->id, 'ver' => 1]) }}" target="_blank" rel="noopener">Ver PDF de ingreso</a>
    @endif
    @if($solicitud->estado === 'Aprobada Documentalmente' && !$solicitud->recepcion_qr_en && !$solicitud->recepcion_qr_rechazada_en)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:button variant="primary" wire:click="aprobar" wire:loading.attr="disabled">Aprobar recepción</flux:button>
            <flux:button variant="danger" wire:click="$set('mostrarRechazo', true)" wire:loading.attr="disabled">Rechazar solicitud</flux:button>
        </div>
        @if($mostrarRechazo)
            <form wire:submit="rechazar" class="space-y-3 rounded-lg border border-border p-4">
                <flux:textarea wire:model="motivo" label="Motivo de rechazo" required maxlength="3000" />
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">Confirmar rechazo</flux:button>
                <flux:button wire:click="$set('mostrarRechazo', false)">Cancelar</flux:button>
            </form>
        @endif
    @endif
</div>
