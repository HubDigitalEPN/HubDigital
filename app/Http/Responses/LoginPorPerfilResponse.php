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
        $ingreso = $request->session()->pull('url.intended');
        $volverAIngreso = is_string($ingreso) && parse_url($ingreso, PHP_URL_HOST) === $request->getHost()
            && str_starts_with((string) parse_url($ingreso, PHP_URL_PATH), '/depositos/ingreso/');
        $curador = in_array($request->user()?->rolActivo(), [\App\Enums\RolUsuario::CURADOR, \App\Enums\RolUsuario::AYUDANTE_CURADOR, \App\Enums\RolUsuario::ADMIN], true);
        $destino = route($curador ? 'portal.catalogo' : 'dashboard', $curador ? ['vista' => 'mapa'] : []);
        if ($volverAIngreso) $destino = $ingreso;
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => $destino, 'csrf' => csrf_token()]);
        }
        return redirect()->to($destino);
    }
}
