<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInitialPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        if ($request->is('logout', 'api/logout', 'livewire-*/livewire.js', 'livewire-*/livewire.min.js')
            || $request->routeIs('verification.notice', 'verification.verify', 'verification.send', 'password.initial.*')) {
            return $next($request);
        }

        if ($request->is('livewire-*/update')) {
            abort(403, 'Debes cambiar la contrasena inicial antes de continuar.');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Debes cambiar la contrasena inicial antes de continuar.'], 403);
        }

        return redirect()->route($user->hasVerifiedEmail() ? 'password.initial.edit' : 'verification.notice');
    }
}
