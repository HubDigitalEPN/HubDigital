<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

final class LoginPorPerfilResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        // Cada perfil entra a su área. El middleware conserva verificación y cambio inicial.
        $request->session()->forget('url.intended');
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $curador = $request->user()?->esCurador();

        return redirect()->route($curador ? 'portal.catalogo' : 'dashboard',
            $curador ? ['vista' => 'mapa'] : []);
    }
}
