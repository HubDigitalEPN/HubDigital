<?php

namespace App\Services\Admin;

class AdminAuthService
{
    public function attempt(string $username, string $password): bool
    {
        // TODO: reemplazar con consulta a AdminUser model cuando exista
        return $username === 'admin' && $password === 'admin123';
    }
}
