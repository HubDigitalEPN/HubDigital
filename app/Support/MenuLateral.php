<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final class MenuLateral
{
    public function destinos(): array
    {
        $destinos = [];
        foreach ([...config('menu-lateral.opciones'), ...config('menu-lateral.destinos-adicionales')] as $opcion) {
            if ($opcion['ruta'] !== null && Route::has($opcion['ruta'])) {
                $destinos[$opcion['ruta']] = $opcion + ['rolesPermitidos' => $this->valoresRoles($opcion['roles'])];
            }
        }

        return $destinos;
    }

    public function opciones(): array
    {
        $opciones = [];
        foreach (config('menu-lateral.opciones') as $clave => $opcion) {
            $opciones[$clave] = [
                'texto' => $opcion['texto'],
                'ruta' => $opcion['ruta'],
                'grupo' => $opcion['grupo'],
                'roles' => $this->valoresRoles($opcion['roles']),
                'visible' => true,
            ];
        }

        foreach (DB::table('usuarios.menu_lateral')->orderBy('created_at')->orderBy('clave')->pluck('configuracion', 'clave') as $clave => $json) {
            $configuracion = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if ($configuracion['eliminada'] ?? false) {
                unset($opciones[$clave]);
            } else {
                $opciones[$clave] = $configuracion;
            }
        }

        return $opciones;
    }

    public function guardar(string $clave, array $opcion): void
    {
        DB::table('usuarios.menu_lateral')->upsert([
            'clave' => $clave,
            'configuracion' => json_encode($opcion, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ], ['clave'], ['configuracion', 'updated_at']);
    }

    public function visiblePara(RolUsuario $rol, ?array $opciones = null): array
    {
        $destinos = $this->destinos();
        $opciones ??= $this->opciones();
        $arbol = [];
        foreach ($opciones as $clave => $opcion) {
            if (!$opcion['visible'] || !in_array($rol->value, $opcion['roles'], true)) {
                continue;
            }
            $ruta = $opcion['ruta'];
            if ($ruta !== null) {
                if (!in_array($rol->value, $destinos[$ruta]['rolesPermitidos'] ?? [], true)) {
                    continue;
                }
                $opcion += $destinos[$ruta];
            }
            $padre = $opcion['grupo'];
            if ($padre !== null && (!isset($opciones[$padre]) || !$opciones[$padre]['visible'] || !in_array($rol->value, $opciones[$padre]['roles'], true))) {
                continue;
            }
            if ($padre !== null) {
                $arbol[$padre]['hijos'][$clave] = $opcion;
            } else {
                $arbol[$clave] = ($arbol[$clave] ?? []) + $opcion + [
                    'icono' => config('menu-lateral.opciones.'.$clave.'.icono', 'folder'),
                    'hijos' => [],
                ];
            }
        }

        return array_filter($arbol, static fn (array $opcion): bool => ($opcion['ruta'] ?? null) !== null || $opcion['hijos'] !== []);
    }

    private function valoresRoles(array $roles): array
    {
        return array_map(static fn (RolUsuario $rol): string => $rol->value, $roles);
    }
}
