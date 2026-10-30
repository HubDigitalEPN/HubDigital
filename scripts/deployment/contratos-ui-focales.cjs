// Contratos de cambios de UI ejecutados exclusivamente mediante MCP opera_neon.
// No acreditan ni renuevan ninguno de los nueve recorridos históricos.
const fs = require('node:fs');
const path = require('node:path');
const {createHash} = require('node:crypto');
const hash = value => createHash('sha256').update(value).digest('hex');
const definitions = {
    laboratorio: {sources: ['config/pagina-laboratorio.php','public/css/pagina-laboratorio.css','public/js/laboratorio-layout.js','public/images/laboratorio/personal-referencia.png','resources/views/portal-laboratorio.blade.php','resources/views/components/laboratorio-foto.blade.php','resources/views/components/laboratorio-texto.blade.php','resources/views/partials/laboratorio-libre.blade.php','resources/views/livewire/administracion/configuracion-laboratorio.blade.php','resources/views/layouts/portal.blade.php'], checks:['navigation','copy','photos','responsive']},
    tarjetas: {sources: ['Modules/CatalogoPublico/app/Presentation/Http/Controllers/PortalCatalogo.php','Modules/CatalogoPublico/resources/views/livewire/portal-catalogo.blade.php','Modules/CatalogoPublico/resources/views/components/fotografia-tarjeta.blade.php','resources/js/portal-record-scroll.js','public/js/card-scroll.js'], checks:['filtered','noSiblingRail','noPageButtons','scroll','unique']},
    registros: {sources: ['Modules/CatalogoPublico/app/Presentation/Http/Controllers/PortalCatalogo.php','Modules/CatalogoPublico/resources/views/livewire/portal-catalogo.blade.php','resources/js/portal-record-scroll.js','public/js/interactive-grid.js','public/css/interactive-grid.css','resources/views/components/grid-frame.blade.php','resources/views/components/grid-tools.blade.php','resources/views/components/grid-column-menu.blade.php','resources/css/portal-taxonomy-dialogs.css'], checks:['edge','scroll','preservedPosition','columnMenu','columnMove','noPageButtons']},
    fichas: {sources: ['Modules/CatalogoPublico/resources/views/components/ficha-registro-publico.blade.php','Modules/CatalogoPublico/resources/views/components/registro-mapa.blade.php','Modules/CatalogoPublico/resources/views/components/atribuciones-imagen.blade.php','Modules/CatalogoPublico/resources/views/components/visor-imagen.blade.php','resources/js/portal-image-model.js','resources/js/portal-dashboard.js','resources/css/portal-record-dialog.css','resources/css/portal-estadisticas.css'], checks:['summary','labels','closeRight','recordClose','photoCreditsFit','photoClose']},
    chat: {sources: ['Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php','public/js/chat-shell.js','public/css/chat-shell.css','resources/views/components/platform-footer.blade.php','resources/views/components/pwa-install-button.blade.php','resources/views/components/pwa-notification-button.blade.php','resources/views/partials/head.blade.php','resources/js/app.js','resources/css/app.css'], checks:['install','hide','stored','restore','focus']},
};
const owner = {};
definitions.laboratorio.checks.push('branches','originals');
definitions.tarjetas.checks.push('uniform','start');
definitions.fichas.checks.push('markerContrast');
definitions.chat.checks.push('catalogRestore','bothHidden');
definitions.laboratorio.sources.push(...['Adrian.jpg','vladimir_carvajal.jpg','David_Donoso_2.jpg','asistente.jpg','edith_montalvo.jpg','Edith_M.jpg','Dayana_T1.jpg','Monica_Guerra.png','Diego_Almeida.png','Michelle_Velez.jpg','Pablo_Arguello.png','Selene_Baez.png'].map(file=>'public/images/laboratorio/equipo/'+file));
for (const [id, value] of Object.entries(definitions)) for (const source of value.sources) (owner[source] ||= []).push(id);
Object.assign(owner, {
    'tests/Browser/contratos-ui-focales.mjs':Object.keys(definitions),
    'Modules/CatalogoPublico/resources/views/components/filtro-investigacion.blade.php':['tarjetas'],
    'resources/js/portal-map-model.js':[],
    'resources/js/portal-photos.js':['tarjetas','fichas'],
    'resources/js/portal-runtime.js':['tarjetas','registros','fichas','chat'],
    'resources/js/portal-universal-search.js':[], 'resources/js/pwa-install.js':[], 'resources/js/pwa-notifications.js':[],
    'Modules/CatalogoPublico/resources/views/components/busqueda-universal.blade.php':['tarjetas'],
});
const contracts = {
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/EstadisticasCatalogoChat.php':['tests/Feature/PortalChatFilosYBiologiaTest.php'],
    'database/migrations/2026_10_30_000001_completar_perfiles_laboratorio.php':['tests/Feature/PaginaLaboratorioTest.php'],
    'Modules/CatalogoPublico/app/Presentation/Http/Controllers/ServirImagenCatalogo.php':['tests/Feature/RutasObjetosR2Test.php'],
    'app/Console/Commands/ActualizarDescripcionInstitucional.php':['tests/Feature/BusquedaUniversalPublicaTest.php'],
    'app/Http/Controllers/FotoPaginaLaboratorio.php':['tests/Feature/PaginaLaboratorioTest.php'],
    'app/Livewire/Concerns/WithInteractiveGrid.php':['tests/Feature/Auth/AdminInteractiveGridTest.php'],
    'app/Notifications/CanalesCampana.php':['tests/Feature/CanalesCampanaTest.php'],
    'config/webpush.php':['tests/Feature/CanalesCampanaTest.php'],
    'app/Services/DisenoLaboratorio.php':['tests/Feature/PaginaLaboratorioTest.php'],
    'config/pagina-laboratorio.php':['tests/Feature/PaginaLaboratorioTest.php'],
    'config/portal-copy.php':['tests/Feature/PaginaLaboratorioTest.php','tests/Feature/BusquedaUniversalPublicaTest.php'],
    'routes/web.php':['tests/Feature/PaginaLaboratorioTest.php','tests/Feature/GridFormulariosTest.php'],
};
function inputs(root, id) {
    if (!definitions[id]) throw Error('Contrato UI desconocido.');
    const sources=[...new Set([...definitions[id].sources,...Object.keys(owner).filter(file=>owner[file].includes(id))])];
    return Object.fromEntries([__filename,path.join(root,'tests/Browser/contratos-ui-focales.mjs'),...sources.map(file=>path.join(root,file)),path.join(root,'public/build/manifest.json')].map(file=>[path.relative(root,file).replaceAll('\\','/'),hash(fs.readFileSync(file))]));
}
function status(root, id) {
    let receipt;try {receipt=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/ui-focal',id+'.json'),'utf8'));} catch {return false;}
    return receipt.root===fs.realpathSync(root) && receipt.provider==='opera_neon' && receipt.profile==='NeonCodexQA'
        && Number.isFinite(Date.parse(receipt.executed_at)) && definitions[id].checks.every(check=>receipt.checks?.[check]===true)
        && JSON.stringify(receipt.inputs)===JSON.stringify(inputs(root,id));
}
function verify(root) { for (const id of Object.keys(definitions)) if (!status(root,id)) throw Error(`Falta ejecutar el contrato UI focal ${id} en localhost mediante MCP opera_neon sobre las fuentes/build actuales.`); }
function approve(root,id,checks,before) {
    const current=inputs(root,id);
    if (JSON.stringify(before)!==JSON.stringify(current)) throw Error('Cambió una entrada durante el contrato UI.');
    if (!definitions[id].checks.every(check=>checks[check]===true)) throw Error('Contrato UI incompleto o fallido.');
    const dir=path.join(root,'.local/validation/ui-focal');fs.mkdirSync(dir,{recursive:true});
    fs.writeFileSync(path.join(dir,id+'.json'),JSON.stringify({root:fs.realpathSync(root),provider:'opera_neon',profile:'NeonCodexQA',executed_at:new Date().toISOString(),checks,inputs:current},null,2)+'\n');
}
module.exports={definitions,owner,contracts,inputs,status,verify,approve};
