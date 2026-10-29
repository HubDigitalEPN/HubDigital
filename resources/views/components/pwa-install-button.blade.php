@props(['oscuro' => false])
<span class="hub-install" x-data="{ mensaje: '', instalada: window.hubPwaInstall?.instalada || false }" x-on:hub-pwa-installed.window="instalada = true">
    <button type="button" @class(['hub-install-button', 'hub-install-button-dark' => $oscuro]) :disabled="instalada" x-on:click="mensaje = await window.hubPwaInstall.instalar(); $refs.ayuda.showModal()" title="Instalar HubDigital en este dispositivo">
        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span x-text="instalada ? 'Sistema instalado' : 'Instalar sistema'">Instalar sistema</span>
    </button>
    <template x-teleport="body"><dialog class="hub-install-help" x-ref="ayuda" aria-label="Instalar HubDigital"><h2>Instalar HubDigital</h2><p x-text="mensaje"></p><button type="button" x-on:click="$refs.ayuda.close()">Entendido</button></dialog></template>
</span>
