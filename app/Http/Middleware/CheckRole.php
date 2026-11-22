<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RolUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        $esperados = array_map(
            static fn (string $role): RolUsuario => RolUsuario::desdeHistorico($role) ?? throw new \InvalidArgumentException('Rol desconocido.'),
            $roles,
        );
        // La declaración curador admite al equipo. La declaración admin
        // conserva el requisito de pertenecer al perfil con permisos completos.
        if (in_array(RolUsuario::CURADOR->value, array_map('strtoupper', $roles), true)) {
            $esperados[] = RolUsuario::AYUDANTE_CURADOR;
        }

        // Administración es un rol interno asignado fuera del registro público
        // y puede operar todas las áreas. El resto se autoriza por membresía
        // persistida, nunca por un valor enviado por el navegador.
        if (! $user->esAdministrador() && ! $user->tieneAlgunRol(...$esperados)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
