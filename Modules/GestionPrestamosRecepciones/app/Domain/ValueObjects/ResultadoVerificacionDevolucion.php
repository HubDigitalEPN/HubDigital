<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado de la verificación del curador al recibir los especímenes devueltos.
 *
 * Encapsula la regla de negocio que determina el estado final del préstamo
 * y la condición del espécimen según el resultado de la inspección.
 */
enum ResultadoVerificacionDevolucion: string
{
    case SinNovedades = 'sin_novedades';
    case ConObservacion = 'con_observacion';

    public function estadoResultante(): EstadoPrestamo
    {
        return match ($this) {
            self::SinNovedades  => EstadoPrestamo::Cerrado,
            self::ConObservacion => EstadoPrestamo::CerradoConObservacion,
        };
    }

    public function condicionResultante(): CondicionEspecimen
    {
        return match ($this) {
            self::SinNovedades  => CondicionEspecimen::Apto,
            self::ConObservacion => CondicionEspecimen::NoApto,
        };
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
