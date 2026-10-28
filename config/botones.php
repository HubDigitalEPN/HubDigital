<?php

use App\Enums\RolUsuario as Rol;

$equipo = [Rol::ADMIN->value, Rol::CURADOR->value, Rol::AYUDANTE_CURADOR->value];
$cuentas = array_map(fn (Rol $rol): string => $rol->value, Rol::cases());

return [
    'catalogo.completo' => ['nombre' => 'Descargar todo el catálogo', 'grupo' => 'Catálogo', 'roles' => $equipo, 'elegibles' => $equipo],
    'catalogo.ipt' => ['nombre' => 'Descargar formato IPT (GBIF)', 'grupo' => 'Catálogo', 'roles' => $equipo, 'elegibles' => $cuentas],
    'catalogo.resultados' => ['nombre' => 'Descargar resultados CSV', 'grupo' => 'Catálogo', 'roles' => $cuentas, 'elegibles' => $cuentas],
    'catalogo.analisis' => ['nombre' => 'Descargar análisis CSV', 'grupo' => 'Catálogo', 'roles' => $cuentas, 'elegibles' => $cuentas],
    'catalogo.especies' => ['nombre' => 'Descargar lista de especies', 'grupo' => 'Catálogo', 'roles' => $cuentas, 'elegibles' => $cuentas],
    'inventario.exportar' => ['nombre' => 'Exportar Excel del inventario', 'grupo' => 'Curaduría', 'roles' => $equipo, 'elegibles' => $equipo],
    'panel.curador' => ['nombre' => 'Abrir panel del curador', 'grupo' => 'Curaduría', 'roles' => $equipo, 'elegibles' => $equipo],
    'depositos.reporte' => ['nombre' => 'Descargar reporte de depósitos', 'grupo' => 'Curaduría', 'roles' => $equipo, 'elegibles' => $equipo],
];
