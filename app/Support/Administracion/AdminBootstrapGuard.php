<?php

declare(strict_types=1);

namespace App\Support\Administracion;

use App\Enums\RolUsuario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminBootstrapGuard
{
    public function disponible(Request $request): bool
    {
        $configuracion = config('administracion.bootstrap', []);

        if (! ($configuracion['enabled'] ?? false)) {
            return false;
        }

        if (app()->isProduction() || ! in_array(app()->environment(), $configuracion['allowed_environments'] ?? [], true)) {
            return false;
        }

        if (! in_array(strtolower($request->getHost()), $configuracion['allowed_hosts'] ?? [], true)) {
            return false;
        }

        if (! $this->configuracionVigente($configuracion)) {
            return false;
        }

        return ! DB::table('usuarios.admin_bootstrap')->where('id', 'administrador-inicial')->exists()
            && ! User::query()
            ->whereHas('roles', fn ($consulta) => $consulta->where('rol', RolUsuario::CURADOR->value))
            ->exists();
    }

    /** Debe invocarse dentro de la misma transacción que crea la cuenta. */
    public function reclamar(Request $request): void
    {
        abort_unless($this->disponible($request), 404);

        // La clave única arbitra solicitudes simultáneas, incluso en procesos
        // distintos. Un rollback libera la reserva; un alta confirmada la
        // conserva y evita reabrir el acceso si luego se elimina al administrador.
        $insertados = DB::table('usuarios.admin_bootstrap')->insertOrIgnore([
            'id' => 'administrador-inicial',
            'created_at' => now(),
        ]);

        abort_unless($insertados === 1, 404);
    }

    public function tokenValido(#[\SensitiveParameter] string $token): bool
    {
        $esperado = (string) config('administracion.bootstrap.token', '');

        return $esperado !== '' && hash_equals($esperado, $token);
    }

    public function emailPermitido(string $email): bool
    {
        $permitido = User::normalizarEmail((string) config('administracion.bootstrap.email', ''));

        return $permitido !== '' && hash_equals($permitido, User::normalizarEmail($email));
    }

    /** @param array<string, mixed> $configuracion */
    private function configuracionVigente(array $configuracion): bool
    {
        $token = (string) ($configuracion['token'] ?? '');
        $vence = (string) ($configuracion['expires_at'] ?? '');

        if ($token === '' || $vence === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($vence)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
