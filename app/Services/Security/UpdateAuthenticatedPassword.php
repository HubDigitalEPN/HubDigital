<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateAuthenticatedPassword
{
    public function __construct(private readonly InvalidateUserAccess $invalidateUserAccess) {}

    public function update(User $user, #[\SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
            ])->save();

            $this->invalidateUserAccess->keepingCurrentRequest($user);
        });
    }
}
