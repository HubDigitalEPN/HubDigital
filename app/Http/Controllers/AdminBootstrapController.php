<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Support\Administracion\AdminBootstrapGuard;
use App\Support\Administracion\CreadorUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AdminBootstrapController extends Controller
{
    public function create(Request $request, AdminBootstrapGuard $guard): View
    {
        abort_unless($guard->disponible($request), 404);

        return view('auth.admin-bootstrap', [
            'email' => User::normalizarEmail((string) config('administracion.bootstrap.email')),
        ]);
    }

    public function store(
        Request $request,
        AdminBootstrapGuard $guard,
        CreadorUsuario $creador,
    ): RedirectResponse {
        $request->attributes->set('administracion_sensible', true);
        abort_unless($guard->disponible($request), 404);

        $claveIntento = 'admin-bootstrap:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($claveIntento, 5), 429);
        RateLimiter::hit($claveIntento, 60);

        $autorizacion = $request->validate([
            'bootstrap_token' => ['required', 'string', 'max:512'],
        ]);

        if (! $guard->tokenValido($autorizacion['bootstrap_token'])
            || ! is_string($request->input('email'))
            || ! $guard->emailPermitido($request->input('email'))) {
            return back()->withErrors([
                'bootstrap_token' => 'No fue posible autorizar la instalación.',
            ])->onlyInput('first_name', 'last_name', 'email');
        }

        $request->merge(['email' => User::normalizarEmail($request->input('email'))]);

        $datos = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email_normalizado')],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        DB::transaction(function () use ($request, $guard, $creador, $datos): void {
            $guard->reclamar($request);
            $creador->crear([
                ...$datos,
                'cargo' => 'Curador y administrador del sistema',
                'institucion' => 'Escuela Politécnica Nacional',
            ], RolUsuario::CURADOR);
        });

        RateLimiter::clear($claveIntento);

        return redirect()->route('login')->with(
            'status',
            'Administrador creado. Inicia sesión y verifica tu correo mediante el enlace enviado para continuar.',
        );
    }
}
