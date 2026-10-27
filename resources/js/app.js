import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './hubdigital-firmador';
import { crearLov } from './hubdigital-lov';
import './pwa-notifications';
import './connectivity-status';
import { portalSessionReady } from './portal-pwa';
import './portal-theme';
import { bootNotifications } from './hub-notifications';

window.hubDigitalLov = crearLov;
bootNotifications(Livewire);

// El mapa, los gráficos y el explorador no participan en la portada ni en el
// acceso. Cargarlos antes de Alpine sólo en las rutas que los necesitan.
const necesitaPortal = url => /^\/(portal\/catalogo|divulgacion\/analisis-diversidad)(?:\/|$)/.test(new URL(url, location.origin).pathname);
let portal;
const cargarPortal = () => portal ??= import('./portal-runtime');
if (necesitaPortal(location.href)) await cargarPortal();
document.addEventListener('livewire:navigate', event => {
    if (!necesitaPortal(event.detail.url) || window.hubPortalReady) return;
    event.preventDefault();
    cargarPortal().then(() => Livewire.navigate(event.detail.url.href)).catch(() => {
        location.assign(event.detail.url.href);
    });
});

// Los componentes propios y Flux ya están registrados cuando Alpine recorre el DOM.
await portalSessionReady;
Livewire.start();
