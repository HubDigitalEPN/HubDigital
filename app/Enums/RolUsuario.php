<?php

namespace App\Enums;

enum RolUsuario: string
{
    case PRESTAMISTA = 'PRESTAMISTA';
    case DEPOSITANTE = 'DEPOSITANTE';
    case CURADOR = 'CURADOR';
    case AYUDANTE_CURADOR = 'AYUDANTE_CURADOR';
    case RECEPTOR = 'RECEPTOR';
    case ADMIN = 'ADMIN';

    /** @return list<self> */
    public static function rolesInternos(): array
    {
        return [self::CURADOR, self::AYUDANTE_CURADOR, self::RECEPTOR, self::ADMIN];
    }

    /**
     * Nombre legible por humanos del rol (para la interfaz).
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PRESTAMISTA => 'Solicitante',
            self::DEPOSITANTE => 'Depositante',
            self::CURADOR => 'Curador',
            self::AYUDANTE_CURADOR => 'Ayudante de curador',
            self::RECEPTOR => 'Recepción EPN',
            self::ADMIN => 'Administración del sistema',
        };
    }
}
