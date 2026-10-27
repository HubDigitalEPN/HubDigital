<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/** No admite accesos automáticos mediante antiguas cookies «Recordarme». */
final class PreventRememberedAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $guard->user();
        if ($guard->viaRemember()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        if ($request->routeIs('login.store')) $request->merge(['remember' => false]);
        if ($request->hasCookie($guard->getRecallerName()) || $request->routeIs('login.store')) {
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
        }

        return $next($request);
    }
}
