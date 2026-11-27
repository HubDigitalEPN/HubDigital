<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

final class LoginPorPerfilResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        $usuario = $request->user();
        $ingreso = $request->session()->pull('url.intended');
        $volverAIngreso = is_string($ingreso) && parse_url($ingreso, PHP_URL_HOST) === $request->getHost()
            && str_starts_with((string) parse_url($ingreso, PHP_URL_PATH), '/depositos/ingreso/');
        $curador = in_array($usuario?->rolActivo(), [\App\Enums\RolUsuario::CURADOR, \App\Enums\RolUsuario::AYUDANTE_CURADOR], true);
        $destino = route($curador ? 'portal.catalogo' : 'dashboard', $curador ? ['vista' => 'mapa'] : []);
        if ($volverAIngreso) $destino = $ingreso;

        if ($usuario && ! $usuario->hasVerifiedEmail()) {
            $enlaceVerificacion = route('verification.verify', [
                'id' => $usuario->getKey(),
                'hash' => sha1($usuario->getEmailForVerification()),
            ]);
            $request->session()->put('url.intended', $destino);
            $destino = is_string($ingreso) && str_starts_with($ingreso, $enlaceVerificacion.'?')
                ? $ingreso
                : route('verification.notice');
        } elseif ($usuario?->must_change_password) {
            $request->session()->put('url.intended', $destino);
            $destino = route('password.initial.edit');
        }

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => $destino, 'csrf' => csrf_token()]);
        }
        return redirect()->to($destino);
    }
}
