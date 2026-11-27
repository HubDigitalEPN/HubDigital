<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Date;
use Laravel\Fortify\Features;

final class TwoFactorAccess
{
    public function allowed(): bool
    {
        $user = auth()->user();

        if (! Features::canManageTwoFactorAuthentication() || ! $user?->hasVerifiedEmail() || $user->must_change_password) {
            return false;
        }

        $timeout = (int) config('auth.password_timeout') ?: 10800;

        return ! Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            || Date::now()->unix() - (int) session('auth.password_confirmed_at', 0) <= $timeout;
    }

    public function authorize(): void
    {
        abort_unless($this->allowed(), 403);
    }
}
