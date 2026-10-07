import {crearExploradorTaxonomico} from './portal-taxonomy-explorer-model';

function registrarExplorador() { window.Alpine.data('portalExploradorTaxonomico', crearExploradorTaxonomico); }
if (window.Alpine) registrarExplorador();
else document.addEventListener('alpine:init', registrarExplorador, {once: true});
