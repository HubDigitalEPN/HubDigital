<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

final class AccesoMenu
{
    public function __construct(private readonly MenuLateral $menu) {}

    public function autorizar(string $ruta): void
    {
        abort_unless($this->permite($ruta), 403, 'Tu perfil activo no tiene acceso a esta pantalla.');
    }

    public function permite(string $ruta, ?string $accion = null, ?User $usuario = null): bool
    {
        $usuario ??= auth()->user();
        if (!$usuario || !$usuario->hasVerifiedEmail() || $usuario->must_change_password) {
            return false;
        }

        if ($this->tieneAccesoDelSistema($ruta, $usuario)) {
            return true;
        }

        $rol = $usuario->rolActivo();

        return $usuario->tieneRol($rol) && $this->menu->accesoDelegado($ruta, $rol, $accion);
    }

    public function permiteRuta(string $ruta, User $usuario): bool
    {
        foreach (config('permisos-menu', []) as $pantalla => $alcance) {
            if (array_key_exists($ruta, $alcance['rutas'] ?? [])) {
                return $this->permite($pantalla, $alcance['rutas'][$ruta], $usuario);
            }
        }

        return $this->permite($ruta, usuario: $usuario);
    }

    public function autorizarLlamada(Component $componente, string $metodo): void
    {
        $this->autorizarOperacion($componente, 'metodos', $metodo);
    }

    public function autorizarCambio(Component $componente, string $propiedad): void
    {
        $this->autorizarOperacion($componente, 'propiedades', explode('.', $propiedad, 2)[0]);
    }

    private function autorizarOperacion(Component $componente, string $tipo, string $valor): void
    {
        $pantallas = $this->pantallasPorComponente()[$componente::class] ?? [];
        if ($pantallas === []) {
            return;
        }

        $usuario = auth()->user();
        $configuraciones = config('permisos-menu', []);
        foreach ($pantallas as $ruta) {
            if (!$usuario || !$this->permite($ruta, usuario: $usuario)) {
                continue;
            }

            if ($this->tieneAccesoDelSistema($ruta, $usuario)) {
                return;
            }

            $alcance = $configuraciones[$ruta];
            if ($this->incluye($alcance['consulta'][$tipo] ?? [], $valor)) {
                return;
            }
            foreach ($alcance['funciones'] ?? [] as $accion => $funcion) {
                if ($this->incluye($funcion[$tipo] ?? [], $valor) && $this->permite($ruta, $accion, $usuario)) {
                    return;
                }
            }
        }

        abort(403, 'Tu perfil activo no tiene permiso para ejecutar esta función.');
    }

    private function incluye(array $opciones, string $valor): bool
    {
        return in_array($valor, $opciones, true) || in_array('*', $opciones, true);
    }

    private function tieneAccesoDelSistema(string $ruta, User $usuario): bool
    {
        $destino = $this->menu->definiciones()[$ruta] ?? null;

        return $destino !== null && ($usuario->esAdministrador() || $usuario->tieneAlgunRol(...$destino['roles']));
    }

    private function pantallasPorComponente(): array
    {
        return once(function (): array {
            $pantallas = [];
            foreach (config('permisos-menu', []) as $ruta => $alcance) {
                $componente = Route::getRoutes()->getByName($ruta)?->getControllerClass();
                foreach (array_filter([$componente, ...($alcance['componentes'] ?? [])]) as $clase) {
                    $pantallas[$clase][] = $ruta;
                }
            }

            return $pantallas;
        });
    }
}
