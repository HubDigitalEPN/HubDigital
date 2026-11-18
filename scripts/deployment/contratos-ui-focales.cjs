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
    chat: {sources: ['Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php','public/js/chat-shell.js','public/css/chat-shell.css','resources/views/components/platform-footer.blade.php','resources/views/components/pwa-install-button.blade.php','resources/views/components/pwa-notification-button.blade.php','resources/views/partials/head.blade.php','resources/js/app.js','resources/css/app.css','resources/views/layouts/portal.blade.php','Modules/CatalogoPublico/app/Presentation/Http/Controllers/ChatBotWidget.php'], checks:['install','hide','fresh','catalogOnly','restore','focus']},
};
Object.assign(definitions, {
    instituciones_unificadas: {sources:['app/Services/DirectorioInstituciones.php','app/Livewire/Administracion/InstitucionesCatalogo.php','resources/views/livewire/administracion/instituciones-catalogo.blade.php','resources/views/layouts/app/sidebar.blade.php','Modules/InventarioGestionColeccion/routes/web.php'],checks:['redirect','menu','labels','saved','edited','duplicate','disabled','acta','responsive']},
    catalogo_taxones: {sources:['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/TaxonIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/taxones/index.blade.php'],checks:['parents','created','search','edited','inactive','empty','responsive']},
    catalogo_alta: {sources:['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/index.blade.php','Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/ColumnasCatalogo.php'], checks:['open','ecuador','invalid','labels','dateLabels','saved','edited','responsive']},
    depositos_selector_anidado: {sources:['Modules/GestionPrestamosRecepciones/resources/views/components/lov-popup.blade.php'], checks:['nestedOpen','mouseKeepsEditor','saved','keyboardKeepsEditor','focus','responsive']},
    depositos_matriz: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-matriz.blade.php'], checks:['compiled','pendingBlocked','original','suggestion','ready','responsive']},
    depositos_origen: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-origen.blade.php','Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Investigador/RegistroSolicitudDeposito.php'], checks:['purpose','required','policy','inec','focus','saved','responsive']},
    depositos_rechazo: {sources:['Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/RecepcionQrDeposito.php'], checks:['reason','refused','blocked','history','technicalCannotRetry','responsive']},
    depositos_reintento: {sources:['Modules/GestionPrestamosRecepciones/app/Infrastructure/Notifications/EstadoEntregaNotification.php'], checks:['curator','authorized','history','receiveEnabled','responsive']},
    depositos_cerrado: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/detalle-deposito.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Investigador/DetalleDeposito.php','Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/FlujoIngresoDeposito.php'], checks:['closed','noIntake','documents','status','responsive']},
    depositos_documento: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/documento-ingreso-listo.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/DocumentoIngreso.php'], checks:['ready','automatic','fallback','status','responsive']},
    depositos_firma: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-solicitud.blade.php'], checks:['left','label','signedPreview','frame','responsive']},
    depositos_ingreso: {sources:['Modules/GestionPrestamosRecepciones/resources/views/curador/validar-ingreso-qr.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ValidarIngresoQr.php'], checks:['authentic','characteristics','reason','received','noDuplicate','responsive']},
    depositos_contenido: {sources:['Modules/GestionPrestamosRecepciones/resources/views/curador/contenido-recepcion.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ContenidoRecepcion.php'], checks:['tabulated','validated','closed','responsive']},
    captura_dwc: {sources:['resources/views/livewire/captura-darwin-core.blade.php','app/Livewire/CapturaDarwinCore.php'], checks:['reserved','saved','labels','responsive']},
});
definitions.depositos_contenido.sources.push('Modules/GestionPrestamosRecepciones/app/Infrastructure/Persistence/Models/SolicitudDepositoEloquentModel.php','Modules/GestionPrestamosRecepciones/resources/views/investigador/mis-depositos/_card.blade.php','Modules/GestionPrestamosRecepciones/resources/views/curador/revisar-deposito.blade.php','Modules/GestionPrestamosRecepciones/resources/views/curador/bandeja-depositos.blade.php','Modules/GestionPrestamosRecepciones/resources/views/receptor/bandeja-recepciones.blade.php','Modules/GestionPrestamosRecepciones/resources/views/components/deposito-status-badge.blade.php');
definitions.depositos_documento.sources.push('Modules/GestionPrestamosRecepciones/resources/views/pdf/documento-ingreso.blade.php','Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/DocumentoIngresoDeposito.php','Modules/GestionPrestamosRecepciones/app/Infrastructure/Notifications/CodigoQrDisponibleNotification.php');
for (const id of ['depositos_ingreso','depositos_rechazo','depositos_reintento']) definitions[id].sources.push(
    'Modules/GestionPrestamosRecepciones/resources/views/curador/validar-ingreso-qr.blade.php',
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ValidarIngresoQr.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/RecepcionQrDeposito.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/FlujoIngresoDeposito.php',
);
const owner = {};

definitions.laboratorio.sources.push('app/Services/PaginaLaboratorio.php');
definitions.chat.sources.push('app/Support/DisponibilidadChatPortal.php','Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TablaRespuestaCatalogoChat.php');
definitions.laboratorio.checks.push('branches','originals');
definitions.laboratorio.checks.push('uniform','noAnimals','summaries','modal','wrap','modalClose');
definitions.laboratorio.sources.push('resources/views/components/laboratorio-retrato.blade.php','resources/views/components/laboratorio-perfil-texto.blade.php',...['adrian-retrato.jpg','joseluisroman.jpg'].map(file=>'public/images/laboratorio/equipo/'+file));
definitions.tarjetas.checks.push('uniform','start');
definitions.fichas.checks.push('markerContrast');
definitions.chat.checks.push('catalogRestore','bothHidden');
definitions.chat.sources.push('public/pwa-cache.js','public/service-worker.js');
definitions.laboratorio.sources.push(...['Adrian.jpg','vladimir_carvajal.jpg','David_Donoso_2.jpg','asistente.jpg','edith_montalvo.jpg','Edith_M.jpg','Dayana_T1.jpg','Monica_Guerra.png','Diego_Almeida.png','Michelle_Velez.jpg','Pablo_Arguello.png','Selene_Baez.png'].map(file=>'public/images/laboratorio/equipo/'+file));
for (const [id, value] of Object.entries(definitions)) for (const source of value.sources) (owner[source] ||= []).push(id);
Object.assign(owner, {
    // Las pantallas retiradas se acreditan por el contrato de redirección y
    // ausencia de la pantalla; no tienen un cuerpo DOM que volver a abrir.
    'Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EntidadDepositanteIndex.php':[],
    'Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/entidades/index.blade.php':[],
    'tests/Browser/contratos-ui-focales.mjs':Object.keys(definitions),
    'Modules/CatalogoPublico/resources/views/components/filtro-investigacion.blade.php':['tarjetas'],
    'resources/js/portal-map-model.js':[],
    'resources/js/portal-photos.js':['tarjetas','fichas'],
    'resources/js/portal-runtime.js':['tarjetas','registros','fichas','chat'],
    'resources/js/portal-universal-search.js':[], 'resources/js/pwa-install.js':[], 'resources/js/pwa-notifications.js':[],
    'Modules/CatalogoPublico/resources/views/components/busqueda-universal.blade.php':['tarjetas'],
});
const contracts = {
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/DescargarActaRecepcion.php':['tests/Feature/DepositosPlantillaCierreTest.php'],
    'Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/Importers/ActualizacionCatalogo.php':['tests/Feature/ImportacionCatalogoMatricesTest.php'],
    'app/Services/DirectorioInstituciones.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'app/Livewire/Administracion/InstitucionesCatalogo.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'resources/views/livewire/administracion/instituciones-catalogo.blade.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'resources/views/layouts/app/sidebar.blade.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'Modules/InventarioGestionColeccion/routes/web.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EntidadDepositanteIndex.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/entidades/index.blade.php':['tests/Feature/InstitucionesUnificadasTest.php'],
    'Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/TaxonIndex.php':['tests/Feature/TaxonesCicloCuradorTest.php'],
    'Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/taxones/index.blade.php':['tests/Feature/TaxonesCicloCuradorTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/ContenidoRecibidoDeposito.php':['tests/Feature/DepositosContenidoColeccionTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Adapters/InventarioIngresoColeccionAdapter.php':['tests/Feature/DepositosContenidoColeccionTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Documentos/DomPdfGeneradorActaRecepcionAdapter.php':['tests/Feature/DepositosContenidoColeccionTest.php'],
    'Modules/GestionPrestamosRecepciones/resources/views/pdf/acta-recepcion.blade.php':['tests/Feature/DepositosContenidoColeccionTest.php'],
    'Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/ColumnasCatalogo.php':['tests/Feature/CatalogoAltaManualTest.php'],
    'Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php':['tests/Feature/CatalogoAltaManualTest.php'],
    'Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/index.blade.php':['tests/Feature/CatalogoAltaManualTest.php'],
    'Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/Importers/LectorActualizacionCatalogo.php':['tests/Unit/LectorCatalogoNamespacesTest.php','tests/Feature/ImportacionCatalogoMatricesTest.php'],
    'Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-matriz.blade.php':['tests/Feature/DepositosMatrizInteraccionTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Presentation/Support/TextoSolicitudDeposito.php':['tests/Feature/DepositosMatrizInteraccionTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/IngresoPendienteDeposito.php':['tests/Feature/DepositosArquitecturaTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/FlujoIngresoDeposito.php':['tests/Feature/DepositosArquitecturaTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Jobs/ProcesarIngresoDeposito.php':['tests/Feature/DepositosArquitecturaTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Console/Commands/ProcesarIngresosPendientesCommand.php':['tests/Feature/DepositosArquitecturaTest.php'],
    'Modules/GestionPrestamosRecepciones/database/migrations/2026_11_17_200000_add_deposito_entrega_y_outbox.php':['tests/Feature/DepositosArquitecturaTest.php'],
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Notifications/EstadoEntregaNotification.php':['tests/Feature/PerfilesRecepcionDepositoTest.php'],
    'app/Support/DisponibilidadChatPortal.php':['tests/Feature/PortalChatDisponibilidadTest.php'],
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TablaRespuestaCatalogoChat.php':['tests/Feature/PortalChatTablasConsultasTest.php'],
    'Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/EstadisticasCatalogoChat.php':['tests/Feature/PortalChatFilosYBiologiaTest.php'],
    'database/migrations/2026_10_30_000001_completar_perfiles_laboratorio.php':['tests/Feature/PaginaLaboratorioTest.php'],
    'database/migrations/2026_10_30_000002_ampliar_perfil_institucional_adrian.php':['tests/Feature/PaginaLaboratorioTest.php'],
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
Object.assign(contracts, Object.fromEntries([
    'app/Casts/RolUsuarioCast.php',
    'app/Enums/RolUsuario.php',
    'app/Livewire/CapturaDarwinCore.php',
    'database/migrations/2026_10_30_210000_consolidate_staff_roles.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/DocumentoIngresoDeposito.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/RecepcionQrDeposito.php',
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/DocumentoIngreso.php',
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ValidarIngresoQr.php',
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ContenidoRecepcion.php',
    'Modules/GestionPrestamosRecepciones/app/Infrastructure/Notifications/SolicitudEnviadaNotification.php',
    'Modules/GestionPrestamosRecepciones/database/migrations/2026_10_30_210001_add_ingreso_and_content_validation.php',
    'config/botones.php',
].map(source => [source,['tests/Feature/PerfilesRecepcionDepositoTest.php']])));
Object.assign(contracts, {
    'Modules/CatalogoPublico/routes/web.php':['tests/Feature/PerfilesRecepcionDepositoTest.php','tests/Feature/PortalAccesoPorPerfilTest.php'],
    'Modules/GestionPrestamosRecepciones/routes/web.php':['tests/Feature/PerfilesRecepcionDepositoTest.php','tests/Feature/FlujoDepositoPersistenciaE2ETest.php'],
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Receptor/BandejaRecepciones.php':['tests/Feature/FlujoDepositoPersistenciaE2ETest.php'],
    'Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/ResolverLoteQr.php':['tests/Feature/FlujoDepositoPersistenciaE2ETest.php'],
    'app/Actions/Fortify/CreateNewUser.php':['tests/Feature/Auth/RegistrationTest.php'],
    'app/Livewire/Auth/Register.php':['tests/Feature/Auth/RegistrationTest.php'],
    'app/Console/Commands/CrearUsuarioInternoCommand.php':['tests/Feature/Auth/InternalUserProvisioningTest.php'],
    'app/Http/Middleware/CheckRole.php':['tests/Feature/PerfilesRecepcionDepositoTest.php'],
    'app/Http/Responses/LoginPorPerfilResponse.php':['tests/Feature/PerfilesRecepcionDepositoTest.php'],
    'app/Livewire/ActivarRol.php':['tests/Feature/PerfilesRecepcionDepositoTest.php'],
});
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
