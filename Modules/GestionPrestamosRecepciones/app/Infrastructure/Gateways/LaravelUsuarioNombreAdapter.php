<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use App\Models\User;
use Modules\GestionPrestamosRecepciones\Application\Ports\DatosDepositante;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;

/**
 * Adaptador para obtener el nombre de un usuario desde el modelo User de la aplicación.
 *
 * Implementa {@see UsuarioNombrePort} consultando la tabla de usuarios principal.
 */
final class LaravelUsuarioNombreAdapter implements UsuarioNombrePort
{
    /**
     * Recupera el nombre de un usuario por su identificador.
     */
    public function obtenerNombre(string $usuarioId): ?string
    {
        $usuario = User::find($usuarioId);

        return $usuario === null ? null : self::componerNombre($usuario->first_name, $usuario->last_name);
    }

    /**
     * Recupera los datos profesionales de un depositante por su identificador.
     */
    public function obtenerDatosDepositante(string $usuarioId): ?DatosDepositante
    {
        $usuario = User::find($usuarioId);

        if ($usuario === null) {
            return null;
        }

        return new DatosDepositante(
            nombre: self::componerNombre($usuario->first_name, $usuario->last_name),
            cargo: $usuario->cargo,
            institucion: $usuario->institucion,
        );
    }

    /**
     * Recupera un mapa identificador→nombre para un conjunto de usuarios.
     *
     * @param  array<int, string>  $usuarioIds
     * @return array<string, string>
     */
    public function obtenerNombres(array $usuarioIds): array
    {
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $idsValidos = array_values(array_filter(
            $usuarioIds,
            static fn (string $id): bool => preg_match($uuidRegex, $id) === 1,
        ));

        if ($idsValidos === []) {
            return [];
        }

        return User::whereIn('id', $idsValidos)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(static fn (User $usuario): array => [
                (string) $usuario->id => self::componerNombre($usuario->first_name, $usuario->last_name),
            ])
            ->all();
    }

    /**
     * Recupera los identificadores de los usuarios cuyo nombre coincide con el término.
     *
     * @return array<int, string>
     */
    public function buscarIdsPorNombre(string $termino): array
    {
        return User::where('first_name', 'ilike', "%{$termino}%")
            ->orWhere('last_name', 'ilike', "%{$termino}%")
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * Combina nombre y apellido en una sola cadena legible.
     */
    private static function componerNombre(?string $firstName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([$firstName, $lastName])));
    }
}
