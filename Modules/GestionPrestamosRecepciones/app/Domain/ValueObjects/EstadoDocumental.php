<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado de evaluar la completitud documental de una solicitud: válido o
 * requiere corrección. Lo devuelve {@see \Modules\GestionPrestamosRecepciones\Domain\Services\ReglaPermisoMovilizacion}.
 */
enum EstadoDocumental: string
{
    case Valido = 'Válido';
    case RequiereCorreccion = 'Requiere Corrección';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
