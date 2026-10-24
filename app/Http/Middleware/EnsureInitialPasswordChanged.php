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

        if ($request->is('logout', 'api/logout', 'settings/security', 'user/confirm-password', 'livewire-*/livewire.js', 'livewire-*/livewire.min.js')) {
            return $next($request);
        }

        if ($request->is('livewire-*/update')) {
            foreach ((array) $request->input('components', []) as $component) {
                $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
                if (($snapshot['memo']['name'] ?? null) !== 'pages::settings.security') {
                    abort(403, 'Debes cambiar la contrasena inicial antes de continuar.');
                }
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Debes cambiar la contrasena inicial antes de continuar.'], 403);
        }

        return redirect()->route('security.edit');
    }
}
