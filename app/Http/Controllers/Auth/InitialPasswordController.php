<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Responses\LoginPorPerfilResponse;
use App\Services\Security\UpdateAuthenticatedPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitialPasswordController
{
    use PasswordValidationRules;

    public function show(Request $request, LoginPorPerfilResponse $loginResponse): View|Response
    {
        if (! $request->user()->must_change_password) {
            return $loginResponse->toResponse($request);
        }

        return view('pages::auth.initial-password');
    }

    public function update(Request $request, UpdateAuthenticatedPassword $updatePassword, LoginPorPerfilResponse $loginResponse): Response
    {
        $user = $request->user();
        if ($user->must_change_password) {
            $validated = $request->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ], attributes: ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

            $updatePassword->update($user, $validated['password']);
        }

        return $loginResponse->toResponse($request);
    }
}
