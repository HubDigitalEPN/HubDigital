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
    tablas_catalogos: {sources:['app/Livewire/Administracion/TablasCatalogos.php','app/Livewire/Concerns/EditaRegistroCatalogo.php','app/Support/RegistrosAdministracionCatalogos.php','resources/views/livewire/administracion/tablas-catalogos.blade.php','resources/views/livewire/administracion/tablas-catalogos-datos.blade.php'], checks:['menu','selector','editors','navigation','action','fit']},
    instituciones_unificadas: {sources:['app/Services/DirectorioInstituciones.php','app/Livewire/Administracion/InstitucionesCatalogo.php','resources/views/livewire/administracion/instituciones-catalogo.blade.php','Modules/InventarioGestionColeccion/routes/web.php'],checks:['canonical','menu','labels','saved','edited','duplicate','disabled','acta','responsive']},
    catalogo_taxones: {sources:['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/TaxonIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/taxones/index.blade.php'],checks:['parents','created','search','edited','inactive','empty','responsive']},
    catalogo_alta: {sources:['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/index.blade.php','Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/ColumnasCatalogo.php'], checks:['open','ecuador','invalid','labels','dateLabels','saved','edited','responsive']},
    depositos_selector_anidado: {sources:['Modules/GestionPrestamosRecepciones/resources/views/components/lov-popup.blade.php'], checks:['nestedOpen','mouseKeepsEditor','saved','keyboardKeepsEditor','focus','responsive']},
    depositos_matriz: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-matriz.blade.php'], checks:['compiled','pendingBlocked','original','suggestion','ready','responsive']},
    depositos_origen: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-origen.blade.php','Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Investigador/RegistroSolicitudDeposito.php'], checks:['purpose','required','policy','inec','focus','saved','responsive']},
    depositos_rechazo: {sources:['Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/RecepcionQrDeposito.php'], checks:['reason','refused','blocked','history','technicalCannotRetry','responsive']},
    depositos_reintento: {sources:['Modules/GestionPrestamosRecepciones/app/Infrastructure/Notifications/EstadoEntregaNotification.php'], checks:['curator','authorized','history','receiveEnabled','responsive']},
    depositos_cerrado: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/detalle-deposito.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Investigador/DetalleDeposito.php','Modules/GestionPrestamosRecepciones/app/Infrastructure/Services/FlujoIngresoDeposito.php'], checks:['closed','noIntake','documents','status','responsive']},
    depositos_documento: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/documento-ingreso-listo.blade.php','Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/DocumentoIngreso.php'], checks:['ready','automatic','fallback','status','responsive']},
    depositos_firma: {sources:['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-solicitud.blade.php','resources/js/hubdigital-firmador.js'], checks:['left','label','signedPreview','frame','responsive']},
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
    'app/Http/Controllers/RedirigirTablaCatalogo.php':['tests/Feature/TablasCatalogosTest.php'],
    'app/Livewire/Administracion/TablasCatalogos.php':['tests/Feature/TablasCatalogosTest.php'],
    'app/Livewire/Concerns/EditaRegistroCatalogo.php':['tests/Feature/TablasCatalogosTest.php'],
    'app/Support/RegistrosAdministracionCatalogos.php':['tests/Feature/TablasCatalogosTest.php'],
    'Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/Providers/InventarioGestionColeccionServiceProvider.php':['tests/Feature/CatalogoAltaManualTest.php'],
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

// Reorganización de curaduría: contratos propios de sus entradas y pantallas.
Object.assign(definitions, {
    "menu_curaduria": {
        "sources": [
            "app/Livewire/Dashboard.php",
            "resources/views/livewire/dashboard/curador-panel.blade.php",
            "resources/views/layouts/app/sidebar.blade.php",
            "resources/views/components/app-breadcrumbs.blade.php",
            "routes/web.php",
            "Modules/InventarioGestionColeccion/routes/web.php",
            "Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/BandejaDepositos.php",
            "Modules/GestionPrestamosRecepciones/resources/views/curador/bandeja-depositos.blade.php",
            "Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ConfiguracionRecordatorios.php",
            "Modules/GestionPrestamosRecepciones/resources/views/curador/configuracion-recordatorios.blade.php",
            "app/Livewire/Administracion/ConfiguracionSistema.php",
            "resources/views/livewire/administracion/configuracion-sistema.blade.php",
            "resources/views/livewire/administracion/gestion-botones.blade.php"
        ],
        "checks": [
            "unique",
            "retired",
            "labels",
            "shortcuts",
            "locality",
            "account",
            "responsive"
        ]
    },
    "publicacion_unificada": {
        "sources": [
            "Modules/CatalogoPublico/app/Presentation/Http/Controllers/PublicacionCatalogo.php",
            "Modules/CatalogoPublico/resources/views/livewire/publicacion-catalogo.blade.php",
            "Modules/CatalogoPublico/resources/views/livewire/tabla-especimenes-divulgados.blade.php",
            "Modules/CatalogoPublico/resources/views/livewire/sincronizar-especimenes.blade.php",
            "Modules/CatalogoPublico/routes/web.php"
        ],
        "checks": [
            "page",
            "pending",
            "disclosed",
            "visibility",
            "responsive"
        ]
    },
    "contenidos_unificados": {
        "sources": [
            "app/Livewire/Administracion/ContenidosTextos.php",
            "resources/views/livewire/administracion/contenidos-textos.blade.php",
            "resources/views/livewire/administracion/configuracion-textos-portal.blade.php",
            "resources/views/livewire/administracion/configuracion-textos-wizard.blade.php",
            "resources/views/livewire/administracion/configuracion-textos-sistema.blade.php"
        ],
        "checks": [
            "page",
            "portal",
            "depositos",
            "sistema",
            "responsive"
        ]
    }
});
for (const id of ["menu_curaduria", "publicacion_unificada", "contenidos_unificados"]) for (const file of definitions[id].sources) owner[file] = [id];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/CentroRevisionIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/DuplicadosCatalogNumberIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/FechasRevisionIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/LocalidadesRevisionIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/MuestrasColectaIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/TaxaRevisionIndex.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/duplicados/index.blade.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/fechas/revision.blade.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/localidades/revision.blade.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/muestras/index.blade.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/revision/centro.blade.php"] = [];
owner["Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/taxones/revision.blade.php"] = [];
owner["app/Livewire/Administracion/CentroAdministracion.php"] = [];
owner["resources/views/livewire/administracion/centro-administracion.blade.php"] = [];
Object.assign(contracts, {
    "Modules/CatalogoPublico/resources/views/livewire/sincronizar-especimenes.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/CatalogoPublico/resources/views/livewire/tabla-especimenes-divulgados.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/CatalogoPublico/routes/web.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/BandejaDepositos.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/GestionPrestamosRecepciones/app/Presentation/Http/Controllers/Curador/ConfiguracionRecordatorios.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/GestionPrestamosRecepciones/resources/views/curador/bandeja-depositos.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/GestionPrestamosRecepciones/resources/views/curador/configuracion-recordatorios.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/GestionPrestamosRecepciones/resources/views/investigador/portal-depositos.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Domain/SeguimientoFisico/Services/RegistroColumnasEspecimen.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/CatalogoAltaManualTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/CentroRevisionIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/ConfiguracionColumnasIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/DuplicadosCatalogNumberIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/CatalogoAltaManualTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/FechasRevisionIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/LocalidadesRevisionIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/MuestrasColectaIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/TaxaRevisionIndex.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/duplicados/index.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/index.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/CatalogoAltaManualTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/fechas/revision.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/importar/actualizacion.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/importar/index.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/localidades/revision.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/muestras/index.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/revision/centro.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/taxones/revision.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/InventarioGestionColeccion/routes/web.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "app/Livewire/Administracion/CentroAdministracion.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "app/Livewire/Administracion/ConfiguracionSistema.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "app/Livewire/Dashboard.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/components/app-breadcrumbs.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/layouts/app/sidebar.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/livewire/administracion/centro-administracion.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/livewire/administracion/configuracion-sistema.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/livewire/administracion/configuracion-textos-portal.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/PortalTextosAdministracionTest.php"
    ],
    "resources/views/livewire/administracion/configuracion-textos-sistema.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/PortalTextosAdministracionTest.php"
    ],
    "resources/views/livewire/administracion/configuracion-textos-wizard.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php",
        "tests/Feature/PortalTextosAdministracionTest.php"
    ],
    "resources/views/livewire/administracion/gestion-botones.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/livewire/dashboard/curador-panel.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "routes/web.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/CatalogoPublico/app/Presentation/Http/Controllers/PublicacionCatalogo.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "Modules/CatalogoPublico/resources/views/livewire/publicacion-catalogo.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "app/Livewire/Administracion/ContenidosTextos.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ],
    "resources/views/livewire/administracion/contenidos-textos.blade.php": [
        "tests/Feature/NavegacionCuraduriaUnificadaTest.php"
    ]
});

definitions.catalogo_importacion = {sources:['Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/importar/actualizacion.blade.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/importar/index.blade.php'], checks:['page','canonical','noReview','input','responsive']};
for(const file of definitions.catalogo_importacion.sources) owner[file]=['catalogo_importacion'];
definitions.mensajes_sistema = {
    sources:['app/Livewire/Administracion/GestionMensajesSistema.php','resources/views/livewire/administracion/gestion-mensajes-sistema.blade.php'],
    checks:['navegacion','grilla','editor','ajuste','cancelar'],
};
for (const file of definitions.mensajes_sistema.sources) (owner[file] ??= []).push('mensajes_sistema');
owner['Modules/GestionPrestamosRecepciones/resources/views/investigador/portal-depositos.blade.php']=['contenidos_unificados'];
owner['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/ConfiguracionColumnasIndex.php']=['menu_curaduria'];
definitions.catalogo_alta.checks.push('noRevision');
definitions.catalogo_alta.checks.push('openWithoutRequest','empty','lovs','freeText','saveWithoutReload','generated','rowMenuLeft');

// Los encabezados y rutas comunes tienen cobertura de navegación propia.
definitions.depositos_contenido.sources = definitions.depositos_contenido.sources.filter(file => file !== 'Modules/GestionPrestamosRecepciones/resources/views/curador/bandeja-depositos.blade.php');
definitions.instituciones_unificadas.sources = definitions.instituciones_unificadas.sources.filter(file => file !== 'Modules/InventarioGestionColeccion/routes/web.php');

definitions.publicacion_unificada.checks.push('noReview','contrast');
definitions.contenidos_unificados.checks.push('contrast','laboratorio','fichas');
definitions.contenidos_unificados.sources=definitions.contenidos_unificados.sources.filter(file=>!file.endsWith('/configuracion-textos-portal.blade.php'));
definitions.contenidos_unificados.sources.push('resources/views/livewire/administracion/fichas-taxonomicas-portal.blade.php');
Object.assign(definitions, {
    portal_editor_visual:{sources:['app/Support/EditorPortal.php','app/Livewire/Administracion/ConfiguracionTextosPortal.php','public/js/portal-editor.js','public/js/textos-sistema.js','resources/views/livewire/administracion/editor-visual-portal.blade.php','resources/views/components/portal-information-text.blade.php'],checks:['realPage','selected','saved','reloaded','restored','fit','noNavigation']},
    laboratorio_editor:{sources:['app/Livewire/Administracion/ConfiguracionLaboratorio.php','resources/views/livewire/administracion/configuracion-laboratorio.blade.php','public/js/laboratorio-layout.js'],checks:['page','textEditor','fixedLayout','saved','reloaded','restored','fit']},
    catalogo_borrado:{sources:['Modules/InventarioGestionColeccion/app/Infrastructure/SeguimientoFisico/EliminarEspecimenes.php','Modules/InventarioGestionColeccion/database/migrations/2026_11_18_220000_create_eliminaciones_especimenes.php'],checks:['individual','cancelled','deleted','batch','selectionCleared','fit']},
});
for(const id of ['portal_editor_visual','laboratorio_editor','catalogo_borrado'])for(const file of definitions[id].sources)(owner[file]??=[]).push(id);
definitions.portal_editor_visual.checks.push('sectionSwitch');
for(const file of ['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/index.blade.php']){owner[file].push('catalogo_borrado');contracts[file].push('tests/Feature/EspecimenBorradoDefinitivoTest.php');}
for(const file of definitions.portal_editor_visual.sources)contracts[file]=['tests/Feature/EditorVisualPortalesTest.php','tests/Feature/PortalTextosAdministracionTest.php'];
for(const file of definitions.laboratorio_editor.sources)contracts[file]=['tests/Feature/PaginaLaboratorioTest.php'];
contracts['app/Livewire/Administracion/ConfiguracionLaboratorio.php'].push('tests/Feature/EditorVisualPortalesTest.php');
contracts['resources/views/livewire/administracion/configuracion-laboratorio.blade.php'].push('tests/Feature/EditorVisualPortalesTest.php');
owner['app/Support/TextosSistema.php']=['portal_editor_visual','contenidos_unificados'];
contracts['app/Support/TextosSistema.php']=['tests/Feature/EditorVisualPortalesTest.php','tests/Unit/TextosSistemaFuentesTest.php'];
for(const file of definitions.catalogo_borrado.sources)contracts[file]=['tests/Feature/EspecimenBorradoDefinitivoTest.php'];
contracts['config/botones.php'].push('tests/Feature/EspecimenBorradoDefinitivoTest.php');
contracts['routes/web.php'].push('tests/Feature/EditorVisualPortalesTest.php','tests/Feature/PortalFichasTaxonomicasTest.php','tests/Feature/PaginaLaboratorioTest.php');
owner['resources/views/portal-inicio.blade.php']=['portal_editor_visual'];
contracts['resources/views/portal-inicio.blade.php']=['tests/Feature/EditorVisualPortalesTest.php'];
// La cabecera conserva sus consumidores históricos. El editor es además un contrato propio.
owner['resources/views/partials/head.blade.php'].push('portal_editor_visual');
contracts['resources/views/partials/head.blade.php']=['tests/Feature/EditorVisualPortalesTest.php'];
definitions.catalogo_alta.checks.push('help','helpFocus','helpFits','helpPosition','errorVisible','errorFocus','createdLink');
definitions.catalogo_importacion.checks.push('help','helpFocus','helpFits','helpPosition','guide','guideFits','errorVisible','separator','preview');
definitions.catalogo_importacion.sources.push('Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/ActualizarCatalogoIndex.php');
owner['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/ActualizarCatalogoIndex.php']=['catalogo_importacion'];
for (const file of ['resources/views/components/catalogo-ayuda.blade.php','Modules/InventarioGestionColeccion/app/Presentation/Support/AyudasCapturaCatalogo.php','resources/js/hub-notifications.js']) owner[file]=['catalogo_alta','catalogo_importacion'];
for (const file of ['resources/views/components/catalogo-campo.blade.php','resources/views/components/catalogo-errores.blade.php']) owner[file]=['catalogo_alta'];
const specimenRowMenu='Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/especimenes/boton-acciones-fila.blade.php';
definitions.catalogo_alta.sources.push(specimenRowMenu,'resources/views/components/catalogo-campo.blade.php',
    'Modules/GestionPrestamosRecepciones/resources/views/components/lov-popup.blade.php','public/js/lov-origen.js','resources/js/hubdigital-lov.js');
definitions.catalogo_borrado.sources.push(specimenRowMenu);
owner[specimenRowMenu]=['catalogo_alta','catalogo_borrado'];
owner['resources/views/components/catalogo-guia-columnas.blade.php']=['catalogo_importacion'];
for (const file of ['resources/views/components/catalogo-ayuda.blade.php','resources/views/components/catalogo-campo.blade.php','resources/views/components/catalogo-errores.blade.php','resources/views/components/catalogo-guia-columnas.blade.php','Modules/InventarioGestionColeccion/app/Presentation/Support/AyudasCapturaCatalogo.php']) contracts[file]=['tests/Feature/CatalogoAyudasCapturaTest.php'];
contracts['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/EspecimenIndex.php'].push('tests/Feature/CatalogoAyudasCapturaTest.php');
contracts['Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/ActualizarCatalogoIndex.php']=['tests/Feature/ImportacionCatalogoMatricesTest.php','tests/Feature/CatalogoAyudasCapturaTest.php'];

// Contratos propios de la edición contextual y de la ventana continua compartida.
definitions.editor_wizard={sources:['app/Support/VistaPreviaWizard.php','app/Livewire/Administracion/ConfiguracionTextosWizard.php','resources/views/livewire/administracion/configuracion-textos-wizard.blade.php','public/js/portal-editor.js'],checks:['selected','saved','reloaded','restored','steps','inert','apex','fit']};
definitions.textos_contexto={sources:['app/Support/TextosSistema.php','app/Livewire/Administracion/ConfiguracionTextosSistema.php','resources/views/livewire/administracion/configuracion-textos-sistema.blade.php','public/js/formularios-apex.js'],checks:['apex','aligned','location','saved','reloaded','restored','fit']};
definitions.grillas_continuas={sources:['app/Livewire/Concerns/WithContinuousGrid.php','app/Support/PaginadorGrillaContinua.php','app/Livewire/Concerns/WithInteractiveGrid.php','public/js/interactive-grid.js','public/css/interactive-grid.css','resources/views/components/grid-frame.blade.php','resources/views/components/interactive-grid.blade.php','resources/views/components/grid-pages.blade.php','resources/views/components/grid-pagination.blade.php','resources/views/components/grid-column-menu.blade.php'],checks:['wheel','keyboard','bounded','previous','menu','sorted','filtered','fit']};
for(const id of ['editor_wizard','textos_contexto','grillas_continuas'])for(const file of definitions[id].sources)(owner[file]??=[]).push(id);
for(const file of definitions.editor_wizard.sources)(contracts[file]??=[]).push('tests/Feature/EditorWizardContextoTest.php');
for(const file of definitions.textos_contexto.sources)(contracts[file]??=[]).push('tests/Feature/EditorWizardContextoTest.php');
for(const file of definitions.grillas_continuas.sources)(contracts[file]??=[]).push('tests/Feature/GrillasContinuasTest.php');
for(const name of ['CargosCatalogo','LocalidadesEcuadorCatalogo','InstitucionesCatalogo','GruposAnimalesCatalogo','GestionUsuarios','FuentesRevocacionFirma','FichasTaxonomicasPortal']){
    const file=`app/Livewire/Administracion/${name}.php`;(owner[file]??=[]).push('grillas_continuas');(contracts[file]??=[]).push('tests/Feature/GrillasContinuasTest.php');
}
for(const name of ['EspecimenIndex','TaxonIndex','LocalidadIndex','ExportarEspecimenesGbif']){
    const file=`Modules/InventarioGestionColeccion/app/Presentation/Http/Controllers/SeguimientoFisico/GestionRegistrosTaxonomicos/${name}.php`;(owner[file]??=[]).push('grillas_continuas');(contracts[file]??=[]).push('tests/Feature/GrillasContinuasTest.php');
}
for(const file of ['Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/dataset/exportar-seleccion.blade.php','Modules/InventarioGestionColeccion/resources/views/admin/taxonomia/localidades/index.blade.php','Modules/InventarioGestionColeccion/resources/views/components/paginacion-tabla.blade.php','Modules/CatalogoPublico/app/Presentation/Http/Controllers/TablaEspecimenesDivulgados.php','Modules/CatalogoPublico/resources/views/livewire/tabla-especimenes-divulgados.blade.php']){
    (owner[file]??=[]).push('grillas_continuas');(contracts[file]??=[]).push('tests/Feature/GrillasContinuasTest.php');
}
for(const file of ['Modules/CatalogoPublico/app/Presentation/Http/Controllers/RegistrosTaxonExplorador.php','Modules/CatalogoPublico/resources/views/livewire/registros-taxon-explorador.blade.php']){
    (owner[file]??=[]).push('grillas_continuas');(contracts[file]??=[]).push('tests/Feature/PortalExploradorTaxonomicoTest.php');
}
for(const file of ['Modules/CatalogoPublico/app/Presentation/Http/Controllers/ExploraCeldaMapa.php','Modules/CatalogoPublico/resources/views/components/detalle-celda-mapa.blade.php']){
    (owner[file]??=[]).push('grillas_continuas');(contracts[file]??=[]).push('tests/Feature/PortalCatalogoSeleccionTest.php');
}
contracts['Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php']=['tests/Feature/PortalChatMatrizDatosTest.php'];
definitions.menu_curaduria.checks.push('administration');
definitions.portal_editor_visual.checks.push('immediate','fixedLayout','quiet','noOutline','noChat','legalLinks');
definitions.editor_wizard.checks.push('fixedLayout','scroll','fieldText','stepText','actions','quiet');
definitions.textos_contexto.checks.push('infinite','noActions');
definitions.contenidos_unificados.checks.push('fichaClarity','fichaInfinite','fichaSaved','guide','noActions');
for(const file of ['app/Support/DisposicionContenido.php','public/js/contenido-disposicion.js','public/css/contenidos-editor.css','resources/views/livewire/administracion/partials/herramientas-disposicion.blade.php']){
    owner[file]=['portal_editor_visual','editor_wizard'];contracts[file]=['tests/Feature/EditorVisualPortalesTest.php','tests/Feature/EditorWizardContextoTest.php'];
}
owner['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito.blade.php']=['editor_wizard'];
contracts['Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito.blade.php']=['tests/Feature/EditorWizardContextoTest.php'];
for(const file of ['config/wizard-copy.php', ...['solicitud','origen','documentos','datos','matriz','envio'].map(paso=>`Modules/GestionPrestamosRecepciones/resources/views/investigador/registro-solicitud-deposito/paso-${paso}.blade.php`), 'Modules/GestionPrestamosRecepciones/resources/views/components/dropzone.blade.php']){
    definitions.editor_wizard.sources.push(file);
    owner[file]=[...new Set([...(owner[file]||[]),'editor_wizard'])];
    contracts[file]=[...new Set([...(contracts[file]||[]),'tests/Feature/EditorWizardContextoTest.php'])];
}
contracts['app/Support/DisposicionContenido.php']=['tests/Feature/EditorVisualPortalesTest.php','tests/Feature/EditorWizardContextoTest.php'];
owner['Modules/GestionPrestamosRecepciones/resources/views/components/sum-cell.blade.php']=['editor_wizard'];
contracts['Modules/GestionPrestamosRecepciones/resources/views/components/sum-cell.blade.php']=['tests/Feature/EditorWizardContextoTest.php'];
contracts['resources/views/livewire/administracion/fichas-taxonomicas-portal.blade.php']=['tests/Feature/PortalFichasTaxonomicasTest.php'];
owner['public/css/contenidos-editor.css']=['portal_editor_visual','editor_wizard','textos_contexto','contenidos_unificados','laboratorio_editor'];
definitions.posicion_accion_inicio={
    sources:['app/Support/DisposicionContenido.php','public/js/contenido-disposicion.js','public/css/contenidos-editor.css','resources/views/portal-inicio.blade.php','resources/views/layouts/portal.blade.php'],
    checks:['fixedLayout','savedLayout','public','preload'],
};
for(const file of definitions.posicion_accion_inicio.sources) {
    owner[file]=[...new Set([...(owner[file]||[]),'posicion_accion_inicio'])];
}
definitions.portal_editor_visual.checks.push('direct');
definitions.editor_wizard.checks.push('direct','modality');
definitions.laboratorio_editor.checks.push('photoEditor','inlineCreate','direct');
definitions.chat_tablas_simples={sources:['Modules/CatalogoPublico/resources/views/livewire/chat-bot-widget.blade.php','public/css/chat-shell.css','Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/MatrizDatosCatalogoChat.php','Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TablaRespuestaCatalogoChat.php','Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/AsistentePortal.php'],checks:['empty','simple','guidance','fit']};
for(const file of definitions.chat_tablas_simples.sources){
    owner[file]=['chat_tablas_simples'];
    contracts[file]=[...new Set([...(contracts[file]||[]),'tests/Feature/PortalChatMatrizDatosTest.php','tests/Feature/PortalChatTablasConsultasTest.php','tests/Feature/PortalChatDisenoExperimentalTest.php','tests/Feature/PortalChatConsultasComplejasTest.php'])];
}

function inputs(root, id) {
    if (!definitions[id]) throw Error('Contrato UI desconocido.');
    const sources=[...new Set([...definitions[id].sources,...Object.keys(owner).filter(file=>owner[file].includes(id))])];
    return Object.fromEntries([__filename,path.join(root,'tests/Browser/contratos-ui-focales.mjs'),...sources.map(file=>path.join(root,file)),path.join(root,'public/build/manifest.json')].map(file=>[path.relative(root,file).replaceAll('\\','/'),fs.existsSync(file)?hash(fs.readFileSync(file)):'eliminado']));
}
function status(root, id) {
    let receipt;try {receipt=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/ui-focal',id+'.json'),'utf8'));} catch {return false;}
    const valid=receipt.root===fs.realpathSync(root) && receipt.provider==='opera_neon' && receipt.profile==='NeonCodexQA'
        && Number.isFinite(Date.parse(receipt.executed_at)) && definitions[id].checks.every(check=>receipt.checks?.[check]===true)
    ;
    if(!valid)return false;
    const current=inputs(root,id);if(JSON.stringify(receipt.inputs)===JSON.stringify(current))return true;
    try{
        const witness=JSON.parse(fs.readFileSync(path.join(root,'.local/validation/ui-focal/compatibilidad',id+'.json'),'utf8'));
        return witness.root===fs.realpathSync(root)&&witness.caso===id&&witness.original_executed_at===receipt.executed_at
            &&witness.receipt_sha256===hash(fs.readFileSync(path.join(root,'.local/validation/ui-focal',id+'.json')))
            &&witness.current_inputs_sha256===hash(JSON.stringify(current))
            &&witness.fragment_sha256===hash(runnerFragment(fs.readFileSync(path.join(root,'tests/Browser/contratos-ui-focales.mjs'),'utf8'),id));
    }catch{return false;}
}
function runnerFragment(source,id){
    source=source.replace(/\r\n/g,'\n');const marks=[...source.matchAll(/^    (?:} else )?if\(caso==='([^']+)'/gm)],index=marks.findIndex(m=>m[1]===id);
    if(index<0)throw Error('Falta el cuerpo completo del caso UI.');
    const body=source.slice(marks[index].index,marks[index+1]?.index??source.length);
    let common=source.slice(0,marks[0].index);
    // Esta fábrica nueva solo es invocada por los dos editores. No modifica
    // la ejecución ni las aserciones de los otros recorridos.
    if(!/comprobarOrden|sinMovimiento/.test(body))common=common.replace(/    const (?:comprobarOrden|sinMovimiento)\b[\s\S]*?(?=^    const (?:abrirAccionesInventario|comprobarAyudas)=)/m,'');
    if(!/abrirAccionesInventario|buscarEnInventario|comprobarOrden|sinMovimiento/.test(body))common=common.replace(/    const abrirAccionesInventario=[\s\S]*?(?=    const comprobarAyudas=)/,'');
    return common+'\n'+body;
}
function preserveCompatible(root,id,originalRunner){
    const file=path.join(root,'.local/validation/ui-focal',id+'.json'),bytes=fs.readFileSync(file),receipt=JSON.parse(bytes),current=inputs(root,id);
    if(receipt.root!==fs.realpathSync(root)||receipt.provider!=='opera_neon'||receipt.profile!=='NeonCodexQA'||!Number.isFinite(Date.parse(receipt.executed_at))||!definitions[id].checks.every(c=>receipt.checks[c]===true))throw Error('Falta una aprobación completa original.');
    if(hash(Buffer.from(originalRunner))!==receipt.inputs['tests/Browser/contratos-ui-focales.mjs'])throw Error('El runner original no coincide con la huella de la ejecución.');
    const controls=['scripts/deployment/contratos-ui-focales.cjs','tests/Browser/contratos-ui-focales.mjs'];
    if(Object.entries(current).some(([file,value])=>!controls.includes(file)&&receipt.inputs[file]!==value))throw Error('Cambió una fuente propia o el build de este caso.');
    const actualRunner=fs.readFileSync(path.join(root,'tests/Browser/contratos-ui-focales.mjs'),'utf8'),fragment=runnerFragment(actualRunner,id);
    if(fragment!==runnerFragment(originalRunner,id))throw Error('Cambió el cuerpo o una ayuda compartida del recorrido.');
    const witness={root:fs.realpathSync(root),caso:id,reused_at:new Date().toISOString(),original_executed_at:receipt.executed_at,receipt_sha256:hash(bytes),original_runner_sha256:hash(Buffer.from(originalRunner)),fragment_sha256:hash(fragment),current_inputs_sha256:hash(JSON.stringify(current)),reason:'Mismas fuentes propias, build, cuerpo completo y aserciones; solo cambió el selector u otros recorridos. No se repitió este caso ni se renovó su fecha.'};
    const dir=path.join(root,'.local/validation/ui-focal/compatibilidad');fs.mkdirSync(dir,{recursive:true});fs.writeFileSync(path.join(dir,id+'.json'),JSON.stringify(witness,null,2)+'\n');return witness;
}
function verify(root) { for (const id of Object.keys(definitions)) if (!status(root,id)) throw Error(`Falta ejecutar el contrato UI focal ${id} en localhost mediante MCP opera_neon sobre las fuentes/build actuales.`); }
function approve(root,id,checks,before) {
    const current=inputs(root,id);
    if (JSON.stringify(before)!==JSON.stringify(current)) throw Error('Cambió una entrada durante el contrato UI.');
    if (!definitions[id].checks.every(check=>checks[check]===true)) throw Error('Contrato UI incompleto o fallido.');
    const dir=path.join(root,'.local/validation/ui-focal');fs.mkdirSync(dir,{recursive:true});
    fs.writeFileSync(path.join(dir,id+'.json'),JSON.stringify({root:fs.realpathSync(root),provider:'opera_neon',profile:'NeonCodexQA',executed_at:new Date().toISOString(),checks,inputs:current},null,2)+'\n');
}
definitions.laboratorio_editor.checks.push('reconnected','allPhotos','headerPhoto','inlinePublish','inlineRemove','quiet');
definitions.editor_wizard.checks.push('portable');
definitions.portal_editor_visual.checks.push('allSections','fixed','fixedText','inert','photos','mapNotice');
definitions.ficha_editor_directo={sources:['app/Livewire/Administracion/FichasTaxonomicasPortal.php','resources/views/livewire/administracion/fichas-taxonomicas-portal.blade.php','resources/views/livewire/administracion/contenidos-textos.blade.php','Modules/CatalogoPublico/app/Presentation/Http/Controllers/FichaTaxonomicaPublica.php','resources/js/portal-taxonomy-branch-viewer.js','public/js/contenido-disposicion.js','public/css/contenidos-editor.css'],checks:['fixedLayout','photo','text','noResize','fit','help','scientificName','wideForm']};
for(const f of definitions.ficha_editor_directo.sources){owner[f]=[...new Set([...(owner[f]||[]).filter(id=>id!=='grillas_continuas'),'ficha_editor_directo'])];(contracts[f]??=[]).push('tests/Feature/PortalFichasTaxonomicasTest.php');}
definitions.inventario_continuo={
    sources:[...definitions.catalogo_alta.sources.slice(0,2),'public/js/interactive-grid.js','public/css/interactive-grid.css',
        'resources/views/components/grid-frame.blade.php','resources/views/components/grid-tools.blade.php',
        'resources/views/components/catalogo-excel-menu.blade.php','app/Livewire/Concerns/WithContinuousGrid.php'],
    checks:['sinPaginacion','scroll','teclado','menu','filtros','columnas','busqueda','restaurado'],
};
for(const f of definitions.inventario_continuo.sources){(owner[f]??=[]).push('inventario_continuo');(contracts[f]??=[]).push('tests/Feature/GrillasContinuasTest.php');}
for(const f of ['app/Support/FotosEditoriales.php','app/Livewire/Concerns/WithFotosEditoriales.php','app/Livewire/Concerns/WithTextosNavegacion.php','app/Support/VistaEditorialInerte.php','app/Http/Controllers/FotoEditorialPortal.php','app/Http/Middleware/CachePublicPortal.php','resources/views/livewire/administracion/partials/foto-editorial.blade.php','resources/views/livewire/administracion/partials/texto-navegacion.blade.php']){
    definitions.portal_editor_visual.sources.push(f);owner[f]=['portal_editor_visual'];contracts[f]=['tests/Feature/EditorPresentacionDirectaTest.php'];
}
for(const f of ['resources/views/layouts/portal.blade.php','config/portal-copy.php','routes/web.php']){(contracts[f]??=[]).push('tests/Feature/EditorPresentacionDirectaTest.php');}
for(const f of ['Modules/CatalogoPublico/app/Presentation/Http/Controllers/PortalCatalogo.php', 'Modules/CatalogoPublico/resources/views/dashboard-coleccion.blade.php',
    ...['filtro-investigacion','explorador-taxonomico','tabla-registros-mapa','panel-investigacion','menu-analisis'].map(nombre=>`Modules/CatalogoPublico/resources/views/components/${nombre}.blade.php`),
]) {
    definitions.portal_editor_visual.sources.push(f);
    (owner[f]??=[]).push('portal_editor_visual');
    (contracts[f]??=[]).push('tests/Feature/EditorPresentacionDirectaTest.php');
}
owner['public/css/pagina-laboratorio.css']=['laboratorio_editor'];
definitions.perfiles_catalogo = {
    sources: ['config/menu-lateral.php', 'config/permisos-menu.php', 'app/Support/AccesoMenu.php', 'app/Support/MenuLateral.php', 'app/Models/PerfilPersonalizado.php',
        'app/Livewire/Administracion/GestionMenuLateral.php', 'resources/views/livewire/administracion/gestion-menu-lateral.blade.php',
        'resources/views/livewire/administracion/menu-lateral-nodo.blade.php'],
    checks: ['pantalla', 'indicador', 'consulta', 'edicion', 'validacion', 'importacion', 'rolesActuales', 'dialogo', 'registroPrestamos', 'administracion', 'cancelado'],
};
for(const file of definitions.perfiles_catalogo.sources) {
    (owner[file] ??= []).push('perfiles_catalogo');
    (contracts[file] ??= []).push('tests/Unit/AccesoDelegadoMenuLateralTest.php');
}
module.exports={definitions,owner,contracts,inputs,status,verify,approve,preserveCompatible,runnerFragment};
