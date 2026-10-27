if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.addEventListener('message', event => {
        if (event.data?.type === 'portal-taxonomy-updated') window.dispatchEvent(new CustomEvent('portal-taxonomy-updated'));
    });

    // La primera navegación sucede antes de que la PWA la controle. Guardar
    // después de pintar también ese HTML y sus recursos, sin esperar otra visita.
    let warmedController;
    const warmHome = () => {
        const controller = navigator.serviceWorker.controller;
        if (!window.hubPortalWarmHome || window.hubPortalCachedHome || location.pathname !== '/' || location.search
            || document.readyState !== 'complete' || !navigator.serviceWorker.controller) return;
        if (warmedController === controller) return;
        // Una versión anterior puede ignorar el mensaje. Volver a preparar
        // cuando la actualización activa reclame esta misma página.
        warmedController = controller;
        controller.postMessage({type: 'portal-cache-home', assets:
            [...document.querySelectorAll('link[rel="stylesheet"][href], script[src], img[src]')]
                .map(node => node.href || node.src),
        });
    };
    window.addEventListener('load', warmHome, {once: true});
    navigator.serviceWorker.addEventListener('controllerchange', warmHome);
    warmHome();
}

export const portalSessionReady = window.hubPortalCachedHome
    ? fetch('/portal/pwa/sesion', {credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(5000), headers: {Accept: 'application/json'}})
        .then(async response => {
            if (!response.ok) throw new Error('No se pudo renovar la sesión del portal.');
            const session = await response.json();
            if (session.authenticated) {
                // La respuesta privada ya invalidó el respaldo anónimo antes
                // de recargar; la cabecera vuelve a mostrar la cuenta vigente.
                location.reload();
                return new Promise(() => {});
            }
            document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', session.csrf);
            if (window.livewireScriptConfig) window.livewireScriptConfig.csrf = session.csrf;
        })
        .catch(() => {
            // Mantener visible el contenido público, sin habilitar acciones con
            // el token de otra sesión. El reintento funciona sin Alpine/Livewire.
            const status = document.getElementById('hub-connectivity-status');
            if (status) {
                status.dataset.sessionUnavailable = 'true';
                status.hidden = false;
                const message = status.querySelector('[data-hub-connectivity-message]');
                if (message) message.textContent = 'No se pudo conectar. Reconecta para usar el menú y el asistente.';
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.textContent = 'Reintentar';
                retry.style.textDecoration = 'underline';
                retry.addEventListener('click', () => location.reload());
                status.append(retry);
            }
            window.addEventListener('online', () => location.reload(), {once: true});
            return new Promise(() => {});
        })
    : Promise.resolve();
