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
            static fn (string $role): RolUsuario => RolUsuario::from(strtoupper($role)),
            $roles,
        );

        if (! $user->tieneAlgunRol(...$esperados)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
