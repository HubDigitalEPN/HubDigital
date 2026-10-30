@props(['oscuro' => false])
<span class="hub-install" x-cloak x-show="disponible || mensaje"
    x-data="{ mensaje: '', disponible: Boolean(window.hubPwaInstall?.disponible) }"
    x-on:hub-pwa-install-state.window="disponible = Boolean(window.hubPwaInstall?.disponible)"
    x-on:hub-pwa-installed.window="disponible = false; mensaje = ''">
    <button type="button" x-show="disponible" @class(['hub-install-button', 'hub-install-button-dark' => $oscuro]) x-on:click="mensaje = ''; mensaje = await window.hubPwaInstall.instalar()" title="Instalar HubDigital en este dispositivo">
        <svg width="15" height="15" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Instalar sistema</span>
    </button>
    <span x-show="mensaje" x-text="mensaje" role="status" class="text-xs"></span>
</span>
