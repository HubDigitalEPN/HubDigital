<?php

use App\Livewire\Administracion\ConfiguracionLaboratorio;
use App\Livewire\Administracion\ConfiguracionTextosPortal;
use App\Livewire\Administracion\FichasTaxonomicasPortal;
use Modules\CatalogoPublico\Presentation\Http\Controllers\SincronizarEspecimenes;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\DetallePrestamo as DetallePrestamoCurador;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleActa;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\DetalleDeposito;

$funcion = static fn (string $etiqueta, array $metodos, array $propiedades = []): array =>
    compact('etiqueta', 'metodos', 'propiedades');
$consulta = static fn (array $metodos = [], array $propiedades = []): array => [
    'metodos' => [...$metodos, '$refresh', 'gotoPage', 'previousPage', 'nextPage', 'setPage'],
    'propiedades' => $propiedades,
];
$bandeja = $consulta(
    ['toggleOrden', 'limpiarFiltros', 'cambiarVista'],
    ['busqueda', 'estado', 'vista', 'filtroEstado', 'filtroTipo', 'fechaDesde', 'fechaHasta'],
);
$grilla = $consulta(
    ['buscar', 'restablecer', 'filtrarColumnaGrilla', 'ordenarCampoGrilla', 'restablecerGrilla',
        'cargarGrillaContinua', 'seleccionarTabla', 'irAPagina', 'limpiarFiltros'],
    ['busqueda', 'tabla', 'filtroRol', 'gridPerPage', 'vista', 'busquedaCatalogo', 'busquedaTaxonomia',
        'fechaDesde', 'fechaHasta', 'colector', 'publicacion', 'regionCoordenadas'],
);

return [
    'prestamos.investigador.mis-solicitudes' => [
        'consulta' => $bandeja,
        'funciones' => [
            'enviar' => $funcion('Enviar solicitudes propias', ['prepararEnvioSolicitud', 'enviarSolicitud'], ['modalEnvio']),
        ],
        'rutas' => ['prestamos.investigador.solicitud.detalle' => null],
    ],
    'prestamos.investigador.mis-actas' => [
        'componentes' => [DetalleActa::class],
        'consulta' => $bandeja,
        'funciones' => [
            'firmar' => $funcion('Firmar y entregar actas propias', [
                '_startUpload', '_finishUpload', '_removeUpload', '_uploadErrored',
                'subirActa', 'cancelarUploadActa', 'limpiarPdfFirmado', 'limpiarDocumentoIdentidad',
                'cancelarUploadIdentidad', 'limpiarDocumentoIdentidadSolo', 'firmarDigitalmente', 'subirDocumentoIdentidad',
            ], ['showUploadModal', 'showFirmaCanvasModal', 'showIdentidadModal', 'firmaBase64', 'pdfFirmado',
                'documentoIdentidad', 'documentoIdentidadSolo']),
        ],
        'rutas' => ['prestamos.investigador.acta.detalle' => null],
    ],
    'prestamos.investigador.mis-prestamos' => [
        'consulta' => $bandeja,
        'funciones' => [
            'entrega' => $funcion('Confirmar la entrega de préstamos propios', []),
            'devolver' => $funcion('Registrar devoluciones de préstamos propios', []),
            'prorrogar' => $funcion('Solicitar prórrogas de préstamos propios', []),
        ],
        'rutas' => [
            'prestamos.investigador.prestamo.detalle' => null,
            'prestamos.investigador.prestamo.verificacion-entrega' => 'entrega',
            'prestamos.investigador.prestamo.registrar-devolucion' => 'devolver',
            'prestamos.investigador.prestamo.solicitar-prorroga' => 'prorrogar',
        ],
    ],
    'prestamos.investigador.mis-depositos' => [
        'componentes' => [DetalleDeposito::class],
        'consulta' => $bandeja,
        'funciones' => ['anunciar' => $funcion('Anunciar la entrega de depósitos propios', ['anunciarEntrega'])],
        'rutas' => ['prestamos.investigador.deposito.detalle' => null],
    ],
    'prestamos.investigador.solicitud.crear' => [
        'acceso' => 'Abrir el formulario de préstamo',
        'funciones' => [
            'tramitar' => $funcion('Preparar y enviar solicitudes de préstamo propias', ['*'], ['*']),
        ],
        'rutas' => ['prestamos.investigador.solicitud.editar' => 'tramitar'],
    ],
    'prestamos.investigador.deposito.crear' => [
        'acceso' => 'Abrir el formulario de depósito',
        'funciones' => [
            'tramitar' => $funcion('Preparar, firmar y enviar solicitudes de depósito propias', ['*'], ['*']),
        ],
        'rutas' => [
            'prestamos.investigador.deposito.corregir' => 'tramitar',
            'depositos.solicitud.crear' => 'tramitar',
            'depositos.solicitud.vista-previa' => 'tramitar',
            'depositos.solicitud.firmar' => 'tramitar',
            'depositos.firmador.token.kit' => 'tramitar',
            'depositos.solicitud.estado-firma' => 'tramitar',
            'depositos.solicitud.documento' => 'tramitar',
        ],
    ],
    'prestamos.curador.solicitudes' => [
        'consulta' => $bandeja,
        'funciones' => [
            'revisar' => $funcion('Revisar solicitudes de préstamo', [], []),
        ],
        'rutas' => ['prestamos.curador.solicitud.revisar' => 'revisar'],
    ],
    'prestamos.curador.actas' => [
        'consulta' => $bandeja,
        'funciones' => [
            'enviar' => $funcion('Enviar actas', ['prepararEnvioActa', 'enviarActa']),
            'validar' => $funcion('Validar actas', [], []),
        ],
        'rutas' => ['prestamos.curador.acta.validar' => 'validar'],
    ],
    'prestamos.curador.prestamos' => [
        'componentes' => [DetallePrestamoCurador::class],
        'consulta' => $bandeja,
        'funciones' => [
            'habilitar' => $funcion('Habilitar el envío internacional', [
                'habilitarEnvio', '_startUpload', '_finishUpload', '_removeUpload', '_uploadErrored',
            ], ['documentoExportacion']),
            'auditar' => $funcion('Auditar préstamos', []),
            'verificar' => $funcion('Aprobar verificaciones de entrega', []),
            'prorrogar' => $funcion('Gestionar prórrogas', []),
            'cerrar' => $funcion('Cerrar préstamos', []),
        ],
        'rutas' => [
            'prestamos.curador.prestamo.detalle' => null,
            'prestamos.curador.prestamo.auditar' => 'auditar',
            'prestamos.curador.prestamo.aprobar-verificacion' => 'verificar',
            'prestamos.curador.prestamo.gestionar-prorroga' => 'prorrogar',
            'prestamos.curador.prestamo.cerrar' => 'cerrar',
        ],
    ],
    'prestamos.curador.configuracion' => [
        'funciones' => [
            'programar' => $funcion('Editar y programar recordatorios', ['habilitarEdicion', 'cancelarEdicion', 'toggleDia', 'agregarDia', 'quitarDia', 'guardar'], ['*']),
            'patente' => $funcion('Guardar la patente anual', ['guardarPatente'], ['patente', 'anioPatente']),
        ],
    ],
    'prestamos.curador.depositos' => [
        'consulta' => $bandeja,
        'funciones' => [
            'priorizar' => $funcion('Cambiar prioridad de expedientes', ['priorizar']),
            'revisar' => $funcion('Revisar y resolver expedientes de depósito', []),
            'actas' => $funcion('Gestionar y firmar actas de recepción', []),
        ],
        'rutas' => [
            'prestamos.curador.deposito.revisar' => 'revisar',
            'prestamos.curador.deposito.acta' => 'actas',
            'prestamos.curador.deposito.acta.firmar' => 'actas',
        ],
    ],
    'prestamos.receptor.depositos' => [
        'consulta' => $bandeja,
        'funciones' => [
            'recibir' => $funcion('Registrar la recepción física', ['abrirPorCodigo'], ['codigo', 'codigoQr']),
        ],
        'rutas' => [
            'prestamos.receptor.deposito.recepcion' => 'recibir',
            'depositos.ingreso.validar' => 'recibir',
            'depositos.ingreso.contenido' => 'recibir',
        ],
    ],
    'inventario.captura-dwc' => [
        'consulta' => $consulta(['seleccionar'], ['busqueda']),
        'funciones' => [
            'capturar' => $funcion('Crear y corregir capturas Darwin Core', ['nuevo', 'seleccionar', 'guardar'], ['datos', 'id']),
        ],
    ],
    'inventario.taxonomia.etiquetas' => [
        'consulta' => $consulta(['buscar', 'cerrarQr'], ['busqueda']),
        'funciones' => [
            'generar' => $funcion('Generar etiquetas QR', ['mostrarQr']),
        ],
    ],
    'divulgacion.index' => [
        'componentes' => [SincronizarEspecimenes::class, TablaEspecimenesDivulgados::class],
        'consulta' => $grilla,
        'funciones' => [
            'publicar' => $funcion('Divulgar registros pendientes', [
                'toggleSeleccion', 'seleccionarTodos', 'deseleccionarTodos', 'setEspecimenActivo', 'toggleCampo',
                'habilitarTodo', 'copiarConfigDeActivo', 'copiarConfigATodos', 'avanzarPaso', 'retrocederPaso', 'sincronizar',
            ], ['seleccionados', 'especimenActivo']),
            'configurar' => $funcion('Cambiar la visibilidad de campos divulgados', [
                'abrirConfiguracion', 'cerrarConfiguracion', 'toggleFlag', 'guardarConfiguracion',
            ], ['configuracion', 'modalConfiguracion']),
        ],
    ],
    'divulgacion.imagenes' => [
        'consulta' => $consulta(['seleccionarEspecie', 'cancelarEdicion'], ['busqueda', 'busquedaTaxon', 'vista']),
        'funciones' => [
            'subir' => $funcion('Subir fotografías', ['subir', '_startUpload', '_finishUpload', '_removeUpload', '_uploadErrored'], ['*']),
            'atribuir' => $funcion('Cambiar autoría y licencia', ['editarAtribuciones', 'guardarAtribuciones'], ['*']),
            'portada' => $funcion('Elegir fotografías de portada', ['usarComoPortada']),
        ],
    ],
    'divulgacion.analisis-diversidad' => [],
    'admin.catalogos' => [
        'consulta' => $grilla,
        'funciones' => [
            'editar' => $funcion('Crear y editar valores de las tablas', ['nuevo', 'editar', 'buscarOpcionesRelacion', 'guardar'], ['datos']),
            'eliminar' => $funcion('Eliminar valores de las tablas', ['solicitarEliminar', 'eliminar']),
        ],
    ],
    'admin.matrices' => [
        'consulta' => $grilla,
        'funciones' => [
            'editar' => $funcion('Editar columnas de plantillas', ['nuevo', 'editarColumna', 'guardarColumna', 'cancelar'], ['datos', 'columna']),
            'eliminar' => $funcion('Eliminar columnas de plantillas', ['solicitarEliminar', 'confirmarCambio', 'cancelar']),
            'cargar' => $funcion('Cargar plantillas', [
                'mostrarCarga', 'subirPlantilla', 'cancelar', '_startUpload', '_finishUpload', '_removeUpload', '_uploadErrored',
            ], ['archivo']),
            'descargar' => $funcion('Descargar plantillas internas', ['descargarPlantillaDepositos']),
        ],
    ],
    'admin.contenidos' => [
        'componentes' => [ConfiguracionLaboratorio::class, ConfiguracionTextosPortal::class, FichasTaxonomicasPortal::class],
        'consulta' => $grilla,
        'funciones' => [
            'editar' => $funcion('Editar contenidos del portal', ['*'], ['*']),
        ],
    ],
    'admin.mensajes' => [
        'consulta' => $grilla,
        'funciones' => [
            'editar' => $funcion('Crear y editar mensajes', ['nuevo', 'editar', 'guardar', 'cancelar'], ['texto', 'clave', 'textoEditado', 'contenido']),
            'eliminar' => $funcion('Eliminar mensajes', ['eliminar']),
        ],
    ],
    'admin.usuarios' => ['consulta' => $grilla],
    'admin.botones' => [],
    'admin.menu-lateral' => ['consulta' => $consulta(['seleccionarRol'], ['rolVista'])],
];
