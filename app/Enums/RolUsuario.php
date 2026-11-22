<?php

namespace App\Enums;

enum RolUsuario: string
{
    case DEPOSITANTE = 'DEPOSITANTE';
    case CURADOR = 'CURADOR';
    case AYUDANTE_CURADOR = 'AYUDANTE_CURADOR';
    case PASANTE = 'PASANTE';

    // Alias de código para conservar los flujos históricos de préstamos y recepción.
    public const PRESTAMISTA = self::DEPOSITANTE;
    public const RECEPTOR = self::AYUDANTE_CURADOR;
    public const ADMIN = self::CURADOR;

    public static function desdeHistorico(string $valor): ?self
    {
        return match (strtoupper($valor)) {
            'PRESTAMISTA' => self::DEPOSITANTE,
            'RECEPTOR', 'TECNICO' => self::AYUDANTE_CURADOR,
            'ADMIN' => self::CURADOR,
            default => self::tryFrom(strtoupper($valor)),
        };
    }

    /** @return list<self> */
    public static function rolesInternos(): array
    {
        return [self::CURADOR, self::AYUDANTE_CURADOR, self::PASANTE];
    }

    /**
     * Nombre legible por humanos del rol (para la interfaz).
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::DEPOSITANTE => 'Depositante',
            self::CURADOR => 'Administrador-Curador',
            self::AYUDANTE_CURADOR => 'Técnico',
            self::PASANTE => 'Pasante',
        };
    }
}
