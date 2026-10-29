<div class="hub-pwa-opt-in" x-data="{ estado: '', mensaje: '', ocupada: false }" x-init="window.hubPwaNotifications?.status()" x-on:hub-pwa-status.window="estado = $event.detail.status; mensaje = $event.detail.message || ''">
    <button type="button" class="hub-pwa-enable" :disabled="ocupada || estado === 'enabled'" x-on:click="ocupada = true; await window.hubPwaNotifications.enable(); ocupada = false">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M9 21h6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
        <span x-text="ocupada ? 'Solicitando permiso…' : estado === 'enabled' ? 'Notificaciones activadas' : 'Activar notificaciones del sistema'">Activar notificaciones del sistema</span>
    </button>
    <p x-show="mensaje" x-text="mensaje" role="status"></p>
</div>
