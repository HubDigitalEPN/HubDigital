<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Laravel\Sanctum\PersonalAccessToken;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GestorTokenEsp32Port;
use RuntimeException;

/**
 * Implementa la gestión del token del ESP32 sobre Laravel Sanctum. Cada gabinete
 * usa un token nombrado «esp32-{gabineteId}» con la ability «esp32»; regenerarlo
 * revoca el anterior con ese nombre antes de emitir el nuevo.
 */
final class SanctumTokenEsp32Adapter implements GestorTokenEsp32Port
{
    public function existeToken(string $gabineteId): bool
    {
        return PersonalAccessToken::where('name', $this->nombreToken($gabineteId))->exists();
    }

    public function regenerarToken(string $gabineteId): string
    {
        $nombre = $this->nombreToken($gabineteId);

        PersonalAccessToken::where('name', $nombre)->delete();

        $usuario = auth()->user();
        if ($usuario === null) {
            throw new RuntimeException('No hay un usuario autenticado para emitir el token del ESP32.');
        }

        return $usuario->createToken($nombre, ['esp32'])->plainTextToken;
    }

    private function nombreToken(string $gabineteId): string
    {
        return "esp32-{$gabineteId}";
    }
}
