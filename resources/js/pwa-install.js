export function crearInstaladorPwa(entorno) {
    let pendiente = null;
    let instalada = Boolean(entorno.matchMedia?.('(display-mode: standalone)').matches || entorno.navigator.standalone);
    entorno.addEventListener('beforeinstallprompt', evento => { evento.preventDefault(); pendiente = evento; });
    entorno.addEventListener('appinstalled', () => { instalada = true; pendiente = null; entorno.dispatchEvent(new entorno.Event('hub-pwa-installed')); });
    return {
        get instalada() { return instalada; },
        async instalar() {
            if (instalada) return 'El sistema ya está instalado en este dispositivo.';
            if (pendiente) {
                const evento = pendiente; pendiente = null;
                await evento.prompt();
                const eleccion = await evento.userChoice;
                return eleccion.outcome === 'accepted' ? 'Instalación aceptada. El navegador completará el proceso.' : 'Puedes instalar el sistema más adelante desde este botón.';
            }
            return /iPad|iPhone|iPod/.test(entorno.navigator.userAgent)
                ? 'En Safari, pulsa Compartir y luego Añadir a pantalla de inicio. Después abre HubDigital desde ese icono para activar las notificaciones.'
                : 'Abre el menú del navegador y elige Instalar aplicación o Instalar este sitio como aplicación. Si esa opción no aparece, puedes seguir usando HubDigital desde este navegador.';
        },
    };
}

if (typeof window !== 'undefined') window.hubPwaInstall = crearInstaladorPwa(window);
