<div class="hub-transactional-ui hub-depositos-ui hub-workspace mx-auto max-w-3xl space-y-5 p-4 sm:p-6" data-validar-ingreso-qr>
    <header><h1 class="text-2xl font-semibold">Validar documento de ingreso</h1><p class="text-text-secondary">{{ $solicitud->numero }} · {{ $solicitud->codigo_qr }}</p></header>
    @if($aviso)
        <p class="text-sm" role="status">Documento y aviso: {{ str_replace('_', ' ', $aviso->estado) }}. Correo: {{ str_replace('_', ' ', $aviso->correo_estado) }}. Intentos: {{ $aviso->intentos }}/3.</p>
        @if(in_array($aviso->estado, ['fallido', 'revision_necesaria'], true) && auth()->user()->tieneAlgunRol(\App\Enums\RolUsuario::CURADOR, \App\Enums\RolUsuario::ADMIN))
            <form wire:submit="reintentarAviso" class="space-y-3 rounded-lg border border-border p-4">
                <flux:textarea wire:model="motivoReintento" label="Motivo del reintento del documento o correo" required maxlength="3000" />
                @if($aviso->estado === 'revision_necesaria')
                    <flux:checkbox wire:model="aceptarPosibleDuplicado" label="Verifiqué el correo y acepto que el reenvío podría duplicarlo" />
                @endif
                <flux:button type="submit" wire:loading.attr="disabled">Programar reintento</flux:button>
            </form>
        @endif
    @endif
    @if($solicitud->recepcion_qr_rechazada_en)
        <flux:callout variant="warning" heading="Entrega no recibida">{{ $solicitud->recepcion_qr_motivo }}. Se requiere autorización del Curador para otro intento.</flux:callout>
        @if(auth()->user()->tieneAlgunRol(\App\Enums\RolUsuario::CURADOR, \App\Enums\RolUsuario::ADMIN) && $solicitud->estado === 'Aprobada Documentalmente')
            <form wire:submit="autorizarNuevoIntento" class="space-y-3">
                <flux:textarea wire:model="motivo" label="Motivo para autorizar otro intento" required maxlength="3000" />
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Autorizar nuevo intento</flux:button>
            </form>
        @endif
    @elseif($solicitud->flujoIngreso()->cerrado())
        <flux:callout variant="success" heading="Finalizada · acta firmada">Consulta los documentos y el historial del expediente.</flux:callout>
    @elseif($solicitud->recepcion_qr_en)
        <flux:callout variant="success" :heading="$solicitud->contenido_validado_en ? 'Recibida y validada' : 'Recibida · pendiente de validar contenido'">Recepción registrada. El registro detallado del contenido es la siguiente etapa.</flux:callout>
        @if($solicitud->flujoIngreso()->puedeValidarContenido())
            <flux:button variant="primary" :href="route('depositos.ingreso.contenido', $solicitud->id)" wire:navigate>Registrar y validar contenido</flux:button>
        @endif
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
    @if($solicitud->flujoIngreso()->puedeRecibir())
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:button variant="primary" wire:click="aprobar" wire:loading.attr="disabled">Recibir material</flux:button>
            <flux:button variant="danger" wire:click="$set('mostrarRechazo', true)" wire:loading.attr="disabled">No recibir entrega</flux:button>
        </div>
        @if($mostrarRechazo)
            <form wire:submit="rechazar" class="space-y-3 rounded-lg border border-border p-4">
                <flux:textarea wire:model="motivo" label="Motivo de no recepción" required maxlength="3000" />
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">Confirmar entrega no recibida</flux:button>
                <flux:button wire:click="$set('mostrarRechazo', false)">Cancelar</flux:button>
            </form>
        @endif
    @endif
    @if($solicitud->historial_entregas)
        <details class="rounded-lg border border-border p-4"><summary>Historial de entregas</summary>
            <ul class="mt-3 space-y-2">@foreach($solicitud->historial_entregas as $evento)
                <li>@fechaEc(\Carbon\CarbonImmutable::parse($evento['fecha_utc'])) · {{ str_replace('_', ' ', $evento['decision']) }} · {{ $evento['motivo'] }}</li>
            @endforeach</ul>
        </details>
    @endif
</div>
