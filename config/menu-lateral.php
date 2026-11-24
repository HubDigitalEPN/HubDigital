<?php

use App\Enums\RolUsuario as Rol;

$equipo = [Rol::CURADOR, Rol::AYUDANTE_CURADOR];
$admin = [Rol::CURADOR];
$depositante = [Rol::DEPOSITANTE];
$grupo = static fn (string $texto, string $icono, array $roles): array => compact('texto', 'icono', 'roles') + ['ruta' => null, 'grupo' => null, 'activas' => []];
$opcion = static fn (string $texto, string $icono, string $ruta, array $roles, ?string $grupo = null, array $activas = []): array =>
    compact('texto', 'icono', 'ruta', 'roles', 'grupo') + ['activas' => $activas ?: [$ruta]];
$creacion = 'Creación de especímenes';

return [
    'opciones' => [
        'dashboard' => $opcion('Dashboard', 'home', 'dashboard', Rol::cases()),
        'prestamos-depositante' => $grupo('Préstamos', 'document-text', $depositante),
        'mis-solicitudes' => $opcion('Mis solicitudes', 'document-text', 'prestamos.investigador.mis-solicitudes', $depositante, 'prestamos-depositante', ['prestamos.investigador.mis-solicitudes', 'prestamos.investigador.solicitud.*']),
        'mis-actas' => $opcion('Mis actas', 'clipboard-document', 'prestamos.investigador.mis-actas', $depositante, 'prestamos-depositante'),
        'mis-prestamos' => $opcion('Mis préstamos', 'archive-box', 'prestamos.investigador.mis-prestamos', $depositante, 'prestamos-depositante', ['prestamos.investigador.mis-prestamos', 'prestamos.investigador.prestamo.*']),
        'depositos-depositante' => $grupo('Depósitos', 'archive-box', $depositante),
        'mis-depositos' => $opcion('Mis depósitos', 'archive-box', 'prestamos.investigador.mis-depositos', $depositante, 'depositos-depositante'),
        'nueva-solicitud' => $opcion('Nueva solicitud', 'plus-circle', 'prestamos.investigador.deposito.crear', $depositante, 'depositos-depositante'),
        'prestamos-equipo' => $grupo('Gestión de préstamos', 'document-text', $equipo),
        'solicitudes' => $opcion('Solicitudes', 'document-text', 'prestamos.curador.solicitudes', $equipo, 'prestamos-equipo', ['prestamos.curador.solicitudes', 'prestamos.curador.solicitud.*']),
        'actas' => $opcion('Actas', 'clipboard-document', 'prestamos.curador.actas', $equipo, 'prestamos-equipo', ['prestamos.curador.actas', 'prestamos.curador.acta.*']),
        'prestamos' => $opcion('Préstamos', 'archive-box', 'prestamos.curador.prestamos', $equipo, 'prestamos-equipo', ['prestamos.curador.prestamos', 'prestamos.curador.prestamo.*']),
        'recordatorios' => $opcion('Recordatorios de préstamos', 'cog-6-tooth', 'prestamos.curador.configuracion', $equipo, 'prestamos-equipo'),
        'depositos-equipo' => $grupo('Gestión de depósitos', 'archive-box', $equipo),
        'revision-documental' => $opcion('Revisión documental y actas', 'inbox-arrow-down', 'prestamos.curador.depositos', $equipo, 'depositos-equipo', ['prestamos.curador.depositos', 'prestamos.curador.deposito.*']),
        'recepcion-fisica' => $opcion('Recepción física', 'clipboard-document-check', 'prestamos.receptor.depositos', $equipo, 'depositos-equipo', ['prestamos.receptor.*']),
        'catalogo' => $grupo('Catálogo', 'rectangle-stack', [...$equipo, Rol::PASANTE]),
        'especimenes' => $opcion($creacion, 'magnifying-glass', 'inventario.taxonomia.especimenes', $equipo, 'catalogo'),
        'captura-dwc' => $opcion($creacion, 'pencil-square', 'inventario.captura-dwc', [Rol::PASANTE], 'catalogo'),
        'importar-catalogo' => $opcion('Importar catálogo', 'arrow-up-tray', 'inventario.taxonomia.importar', $equipo, 'catalogo'),
        'etiquetado-qr' => $opcion('Etiquetado QR', 'qr-code', 'inventario.taxonomia.etiquetas', $equipo, 'catalogo'),
        'divulgacion' => $grupo('Divulgación', 'megaphone', $equipo),
        'publicacion' => $opcion('Publicación del catálogo', 'table-cells', 'divulgacion.index', $equipo, 'divulgacion'),
        'imagenes' => $opcion('Imágenes', 'photo', 'divulgacion.imagenes', $equipo, 'divulgacion'),
        'administracion' => $grupo('Administración', 'cog-6-tooth', $equipo),
        'catalogos' => $opcion('Tablas de catálogos', 'table-cells', 'admin.catalogos', $equipo, 'administracion'),
        'matrices' => $opcion('Matrices', 'document-text', 'admin.matrices', $equipo, 'administracion'),
        'contenidos' => $opcion('Contenidos y textos', 'pencil-square', 'admin.contenidos', $equipo, 'administracion'),
        'mensajes-sistema' => $opcion('Mensajes del Sistema', 'document-text', 'admin.mensajes', $admin, 'administracion'),
        'usuarios' => $opcion('Usuarios y perfiles', 'users', 'admin.usuarios', $admin, 'administracion'),
        'botones' => $opcion('Botones y permisos', 'key', 'admin.botones', $admin, 'administracion'),
        'administrar-menu' => $opcion('Menú lateral', 'bars-3', 'admin.menu-lateral', $admin, 'administracion'),
    ],
    'destinos-adicionales' => [
        $opcion('Nueva solicitud de préstamo', 'plus-circle', 'prestamos.investigador.solicitud.crear', $depositante),
        $opcion('Análisis de diversidad', 'chart-bar', 'divulgacion.analisis-diversidad', $equipo),
        $opcion('Explorar la colección', 'magnifying-glass', 'portal.catalogo', Rol::cases()),
        $opcion('El Laboratorio', 'academic-cap', 'laboratorio', Rol::cases()),
    ],
];
