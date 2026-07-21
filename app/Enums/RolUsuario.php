<?php

namespace App\Enums;

enum RolUsuario: string
{
    case PRESTAMISTA = 'PRESTAMISTA';
    case DEPOSITANTE = 'DEPOSITANTE';
    case CURADOR = 'CURADOR';

    /**
     * Nombre legible por humanos del rol (para la interfaz).
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PRESTAMISTA => 'Solicitante',
            self::DEPOSITANTE => 'Depositante',
            self::CURADOR => 'Curador',
        };
    }
}
