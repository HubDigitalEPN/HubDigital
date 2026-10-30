export function crearInstaladorPwa(entorno) {
    const estado = entorno.hubPwaInstallState ??= {pendiente: null, instalada: false, escuchando: false, enProceso: false};
    estado.instalada ||= Boolean(entorno.matchMedia?.('(display-mode: standalone)').matches || entorno.navigator.standalone);
    const actualizar = () => entorno.dispatchEvent(new entorno.Event('hub-pwa-install-state'));
    if (!estado.escuchando) {
        estado.escuchando = true;
        entorno.addEventListener('beforeinstallprompt', evento => {
            evento.preventDefault(); estado.pendiente = evento; actualizar();
        });
        entorno.addEventListener('appinstalled', () => {
            estado.instalada = true; estado.pendiente = null;
            entorno.dispatchEvent(new entorno.Event('hub-pwa-installed')); actualizar();
        });
    }
    return {
        get instalada() { return estado.instalada; },
        get disponible() { return !estado.instalada && !estado.enProceso && estado.pendiente !== null; },
        async instalar() {
            if (estado.instalada || estado.enProceso || !estado.pendiente) return '';
            const evento = estado.pendiente;
            estado.pendiente = null; estado.enProceso = true;
            try {
                // Invocar en el mismo gesto del clic: ningún await anterior.
                const solicitud = evento.prompt();
                actualizar();
                await solicitud;
                await evento.userChoice;
                // La confirmación de instalación procede de appinstalled.
                return '';
            } catch {
                return 'El navegador no pudo abrir la instalación. Vuelve a intentarlo cuando reaparezca el botón.';
            } finally {
                estado.enProceso = false; actualizar();
            }
        },
    };
}

if (typeof window !== 'undefined') window.hubPwaInstall = crearInstaladorPwa(window);
