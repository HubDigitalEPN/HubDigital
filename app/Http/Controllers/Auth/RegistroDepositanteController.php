<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroDepositanteRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegistroDepositanteController extends Controller
{
    public function store(RegistroDepositanteRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'rol' => RolUsuario::DEPOSITANTE,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user], 201);
    }
}
