import {crearFiltroFilo} from './portal-phylum-model';
const registrar = () => window.Alpine.data('portalFiltroFilo', crearFiltroFilo);
if (window.Alpine) registrar();
else document.addEventListener('alpine:init', registrar, {once: true});
