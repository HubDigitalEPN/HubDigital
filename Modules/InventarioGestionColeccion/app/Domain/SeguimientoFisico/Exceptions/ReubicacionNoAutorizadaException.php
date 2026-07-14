<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

final class ReubicacionNoAutorizadaException extends \DomainException
{
    public function __construct(string $motivo = 'El actor no está autorizado para reubicar.')
    {
        parent::__construct($motivo);
    }

    public static function visitanteSinHabilitacion(string $visitanteId): self
    {
        return new self("El visitante '{$visitanteId}' no está habilitado para reubicar.");
    }
}
