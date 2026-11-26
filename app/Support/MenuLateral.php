<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RolUsuario;
use App\Models\PerfilPersonalizado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final class MenuLateral
{
    public function destinos(?Collection $perfiles = null): array
    {
        $destinos = [];
        $accesosDelegables = config('menu-lateral.accesos-delegables', []);
        $permisosMenu = config('permisos-menu', []);
        $perfiles ??= PerfilPersonalizado::perfiles();
        foreach ($this->definiciones() as $ruta => $opcion) {
            $delegable = $accesosDelegables[$ruta] ?? [];
            $rolesDelSistema = $this->valoresRoles($opcion['roles']);
            $rolesDisponibles = $perfiles->values()->all();
            $opcion['roles'] = $rolesDisponibles;
            $destinos[$ruta] = $opcion + [
                'rolesPermitidos' => $this->valoresRoles($rolesDisponibles),
                'rolesDelSistema' => $rolesDelSistema,
                'rolesDelegables' => array_values(array_diff($this->valoresRoles($rolesDisponibles), $rolesDelSistema)),
                'accesoDelegado' => $delegable['acceso'] ?? $permisosMenu[$ruta]['acceso'] ?? 'Abrir la pantalla',
                'accionesDelegables' => ($delegable['acciones'] ?? []) + array_map(
                    fn (array $funcion): string => $funcion['etiqueta'], $permisosMenu[$ruta]['funciones'] ?? []),
            ];
        }

        return $destinos;
    }

    public function accesoDelegado(string $ruta, RolUsuario|PerfilPersonalizado $rol, ?string $accion = null): bool
    {
        if (!isset($this->definiciones()[$ruta])) {
            return false;
        }
        $acciones = config('menu-lateral.accesos-delegables', [])[$ruta]['acciones'] ?? [];
        $acciones += config('permisos-menu', [])[$ruta]['funciones'] ?? [];
        if ($accion !== null && !array_key_exists($accion, $acciones)) {
            return false;
        }

        foreach ($this->opciones() as $opcion) {
            if ($opcion['ruta'] === $ruta && in_array($rol->value, $opcion['roles'], true)
                && ($accion === null || in_array($accion, $opcion['permisos'][$rol->value] ?? [], true))) {
                return true;
            }
        }

        return false;
    }

    public function definiciones(): array
    {
        $destinos = [...config('menu-lateral.opciones'), ...config('menu-lateral.destinos-adicionales')];

        return array_column(array_filter($destinos, fn (array $opcion): bool =>
            $opcion['ruta'] !== null && Route::has($opcion['ruta'])), null, 'ruta');
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
                'todosLosPerfiles' => $opcion['todosLosPerfiles'] ?? false,
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

    public function visiblePara(RolUsuario|PerfilPersonalizado $rol, ?array $opciones = null): array
    {
        $destinos = $this->destinos(collect([...RolUsuario::cases(), $rol])->unique('value'));
        $opciones ??= $this->opciones();
        $arbol = [];
        foreach ($opciones as $clave => $opcion) {
            $ruta = $opcion['ruta'];
            if (!$opcion['visible']) {
                continue;
            }
            if ($ruta !== null) {
                if (!isset($destinos[$ruta])
                    || (!($opcion['todosLosPerfiles'] ?? false) && !in_array($rol->value, $opcion['roles'], true))) {
                    continue;
                }
                $opcion += $destinos[$ruta];
            }
            $padre = $opcion['grupo'];
            if ($padre !== null && (!isset($opciones[$padre]) || !$opciones[$padre]['visible']
                || (!in_array($rol->value, $opciones[$padre]['roles'], true)
                    && in_array($rol->value, $destinos[$ruta]['rolesDelSistema'] ?? [], true)))) {
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
        return array_map(static fn (RolUsuario|PerfilPersonalizado $rol): string => $rol->value, $roles);
    }
}
