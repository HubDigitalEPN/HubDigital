<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Security\DummyPasswordHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly DummyPasswordHash $dummyPasswordHash) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email_normalizado', User::normalizarEmail($request->email))->first();
        $dummyHash = $this->dummyPasswordHash->value();
        $hash = $user?->password ?? $dummyHash;

        if (! Hash::check($request->password, $hash) || ! $user) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Debes cambiar la contraseña inicial en el portal web antes de utilizar la API.',
                'must_change_password' => true,
            ], 403);
        }

        // No se emiten tokens de larga duración saltando el segundo factor.
        // Las cuentas con 2FA usan el flujo web interactivo de Fortify.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'message' => 'Esta cuenta requiere autenticación de dos factores en el portal web.',
                'two_factor_required' => true,
            ], 409);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($request->password)])->save();
        }

        $tokenLifetime = max(5, (int) config('auth.api_token_lifetime', 480));
        $token = $user->createToken(
            'api',
            $user->habilidadesApiInteractiva(),
            now()->addMinutes($tokenLifetime),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Rol primario: se conserva por compatibilidad con clientes existentes.
                'rol' => $user->rol,
                // Membresía completa de roles (aditivo).
                'roles' => $user->rolesAsignados()->map(fn ($rol) => $rol->value)->values(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Se conserva la forma existente (compat) y se añade la membresía de roles.
        return response()->json(array_merge($user->toArray(), [
            'roles' => $user->rolesAsignados()->map(fn ($rol) => $rol->value)->values(),
        ]));
    }
}
